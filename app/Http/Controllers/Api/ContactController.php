<?php

namespace App\Http\Controllers\Api;

use App\Application\DTOs\Contact\ContactCreateData;
use App\Http\Controllers\Controller;
use App\Application\UseCases\Contact\CreateContactUseCase;
use App\Application\UseCases\Contact\ListContactsUseCase;
use App\Application\UseCases\Contact\GetContactUseCase;
use App\Application\UseCases\Contact\UpdateContactUseCase;
use App\Application\UseCases\Contact\DeleteContactUseCase;
use App\Application\UseCases\Contact\SearchContactsUseCase;
use App\Application\DTOs\Contact\ContactDto;
use App\Application\DTOs\Contact\ContactUpdateData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use InvalidArgumentException;

class ContactController extends Controller
{
    public function __construct(
        private readonly CreateContactUseCase $createContactUseCase,
        private readonly ListContactsUseCase $listContactsUseCase,
        private readonly GetContactUseCase $getContactUseCase,
        private readonly UpdateContactUseCase $updateContactUseCase,
        private readonly DeleteContactUseCase $deleteContactUseCase,
        private readonly SearchContactsUseCase $searchContactsUseCase,
    ) {}

    /**
     * Listar contactos con paginación
     * 
     * GET /api/contacts
     */
    public function index(Request $request): JsonResponse
    {
        $filters = [
            'page' => $request->query('page', 1),
            'limit' => $request->query('limit', 20),
            'search' => $request->query('search'),
            'accountId' => $request->query('account_id'),
            'assignedTo' => $request->query('assigned_to'),
            'status' => $request->query('status'),
            'sortBy' => $request->query('sort_by'),
            'sortOrder' => $request->query('sort_order'),
        ];

        $paginator = $this->listContactsUseCase->execute($filters);

        // ✅ Usar items() en lugar de getCollection()
        $contacts = array_map(
            fn($contact) => ContactDto::fromEntity($contact)->toArray(),
            $paginator->items()
        );

        return response()->json([
            'data' => $contacts,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
            'links' => [
                'first' => $paginator->url(1),
                'last' => $paginator->url($paginator->lastPage()),
                'prev' => $paginator->previousPageUrl(),
                'next' => $paginator->nextPageUrl(),
            ],
        ]);
    }

    /**
     * Obtener detalle de un contacto
     * 
     * GET /api/contacts/{id}
     */
    public function show(int $id): JsonResponse
    {
        $contact = $this->getContactUseCase->execute($id);

        if (!$contact) {
            return response()->json(['error' => 'Contact not found'], Response::HTTP_NOT_FOUND);
        }

        return response()->json([
            'data' => ContactDto::fromEntity($contact)->toArray()
        ]);
    }

    /**
     * Crear nuevo contacto
     * 
     * POST /api/contacts
     * 
     * Body: {
     *   "firstname": "Juan",
     *   "lastname": "Pérez",
     *   "email": "juan@example.com",
     *   "accountid": 123,
     *   ...otros campos opcionales
     * }
     */
    public function store(Request $request): JsonResponse
    {
        try {
            // get authenticated user from middleware JWT
            $authenticatedUser = $request->attributes->get('auth_user');
            if (!$authenticatedUser) {
                return response()->json(
                    ['error' => 'User not authenticated'],
                    Response::HTTP_UNAUTHORIZED
                );
            }

            $authenticatedUserId = $authenticatedUser->getId();

            $contactCreateData = ContactCreateData::fromRequest(
                $request->all(),
                $authenticatedUserId
            );

            $contactId = $this->createContactUseCase->execute($contactCreateData);

            return response()->json([
                'message' => 'Contact created successfully',
                'data' => ['contactid' => $contactId]
            ], Response::HTTP_CREATED);
        } catch (InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (\Exception $e) {
            Log::error('Error creating contact: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'request' => $request->all()
            ]);
            return response()->json(['error' => 'Error creating contact'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Update an existing contact
     * PUT /api/contacts/{id}
     */
    public function update(Request $request, int $id): JsonResponse
    {
        try {
            // Extract authenticated user from JWT
            $authenticatedUser = $request->attributes->get('auth_user');
            if (!$authenticatedUser) {
                return response()->json(
                    ['error' => 'User not authenticated'],
                    Response::HTTP_UNAUTHORIZED
                );
            }

            $authenticatedUserId = $authenticatedUser->getId();

            // Create DTO from request + auth context
            $contactUpdateData = ContactUpdateData::fromRequest(
                $id,
                $request->all(),
                $authenticatedUserId
            );

            // If no changes, return success without doing anything
            if (!$contactUpdateData->hasChanges()) {
                return response()->json([
                    'message' => 'No changes detected',
                    'data' => ['contactid' => $id]
                ]);
            }

            // Execute Use Case with DTO
            $success = $this->updateContactUseCase->execute($contactUpdateData);

            if (!$success) {
                return response()->json(
                    ['error' => 'Contact not found or already deleted'],
                    Response::HTTP_NOT_FOUND
                );
            }

            return response()->json([
                'message' => 'Contact updated successfully',
                'data' => ['contactid' => $id]
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(
                ['error' => $e->getMessage()],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        } catch (\Exception $e) {
            Log::error('Error updating contact: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'request' => $request->all(),
                'contact_id' => $id
            ]);
            return response()->json(
                ['error' => 'Error updating contact'],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * Eliminar contacto (soft delete)
     * 
     * DELETE /api/contacts/{id}
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $success = $this->deleteContactUseCase->execute($id);

            if (!$success) {
                return response()->json(['error' => 'Contact not found'], Response::HTTP_NOT_FOUND);
            }

            return response()->json(['message' => 'Contact deleted successfully']);
        } catch (InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], Response::HTTP_NOT_FOUND);
        } catch (\Exception $e) {
            Log::error('Error deleting contact: ' . $e->getMessage());
            return response()->json(['error' => 'Error deleting contact'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Buscar contactos para autocomplete
     * 
     * GET /api/contacts/search?q=term&account_id=123
     */
    public function search(Request $request): JsonResponse
    {
        $searchTerm = $request->query('q', '');
        $accountId = $request->query('account_id');

        // ✅ Validación mínima en controller (longitud de búsqueda)
        if (strlen(trim($searchTerm)) < 2) {
            return response()->json(['data' => []]);
        }

        try {
            $results = $this->searchContactsUseCase->execute(
                $searchTerm,
                $accountId ? (int) $accountId : null
            );

            return response()->json(['data' => $results]);
        } catch (\Exception $e) {
            Log::error('Error searching contacts: ' . $e->getMessage());
            return response()->json(['error' => 'Error searching contacts'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Obtener contactos de un Account específico
     * 
     * GET /api/accounts/{accountId}/contacts
     */
    public function byAccount(int $accountId, Request $request): JsonResponse
    {
        $filters = [
            'page' => $request->query('page', 1),
            'limit' => $request->query('limit', 20),
            'accountId' => $accountId,
            'sortBy' => 'lastname',
            'sortOrder' => 'ASC',
        ];

        $paginator = $this->listContactsUseCase->execute($filters);

        // ✅ Usar items() en lugar de getCollection()
        $contacts = array_map(
            fn($contact) => ContactDto::fromEntity($contact)->toArray(),
            $paginator->items()
        );

        return response()->json([
            'data' => $contacts,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    
}
