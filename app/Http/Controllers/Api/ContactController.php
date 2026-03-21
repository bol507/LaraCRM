<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Application\UseCases\Contact\CreateContactUseCase;
use App\Application\UseCases\Contact\ListContactsUseCase;
use App\Application\UseCases\Contact\GetContactUseCase;
use App\Application\UseCases\Contact\UpdateContactUseCase;
use App\Application\UseCases\Contact\DeleteContactUseCase;
use App\Application\UseCases\Contact\SearchContactsUseCase;
use App\Application\DTOs\Contact\ContactDto;
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
            // ✅ Obtener usuario autenticado desde middleware JWT
            $authenticatedUser = $request->attributes->get('auth_user');
            if (!$authenticatedUser) {
                return response()->json(['error' => 'User not authenticated'], Response::HTTP_UNAUTHORIZED);
            }

            // ✅ Extraer y sanear datos del request (sin FormRequest)
            $contactData = $this->sanitizeContactInput($request->all());

            // ✅ La validación ocurre DENTRO del Use Case
            $contactId = $this->createContactUseCase->execute(
                $contactData,
                $authenticatedUser->getId()
            );

            return response()->json([
                'message' => 'Contact created successfully',
                'data' => ['contactid' => $contactId]
            ], Response::HTTP_CREATED);

        } catch (InvalidArgumentException $e) {
            // ✅ Errores de validación del dominio → 422
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
     * Actualizar contacto existente
     * 
     * PUT /api/contacts/{id}
     */
    public function update(Request $request, int $id): JsonResponse
    {
        try {
            // ✅ Extraer y sanear datos del request
            $contactData = $this->sanitizeContactInput($request->all());

            // ✅ La validación ocurre DENTRO del Use Case
            $success = $this->updateContactUseCase->execute($id, $contactData);

            if (!$success) {
                return response()->json(['error' => 'Contact not found'], Response::HTTP_NOT_FOUND);
            }

            return response()->json(['message' => 'Contact updated successfully']);

        } catch (InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
            
        } catch (\Exception $e) {
            Log::error('Error updating contact: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'request' => $request->all()
            ]);
            return response()->json(['error' => 'Error updating contact'], Response::HTTP_INTERNAL_SERVER_ERROR);
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

    /**
     * Sanear y preparar datos de entrada del contacto
     * 
     * Este método NO valida, solo limpia y normaliza datos.
     * La validación real ocurre en los Use Cases.
     */
    private function sanitizeContactInput(array $input): array
    {
        return array_filter([
            'firstname' => isset($input['firstname']) ? trim($input['firstname']) : null,
            'lastname' => isset($input['lastname']) ? trim($input['lastname']) : null,
            'email' => isset($input['email']) ? strtolower(trim($input['email'])) : null,
            'phone' => $input['phone'] ?? null,
            'mobile' => $input['mobile'] ?? null,
            'title' => $input['title'] ?? null,
            'department' => $input['department'] ?? null,
            'accountid' => isset($input['accountid']) ? (int) $input['accountid'] : null,
            'description' => $input['description'] ?? null,
            'mailingstreet' => $input['mailingstreet'] ?? null,
            'mailingcity' => $input['mailingcity'] ?? null,
            'mailingstate' => $input['mailingstate'] ?? null,
            'mailingcountry' => $input['mailingcountry'] ?? null,
            'mailingzip' => $input['mailingzip'] ?? null,
            'otherphone' => $input['otherphone'] ?? null,
            'fax' => $input['fax'] ?? null,
            'secondaryemail' => isset($input['secondaryemail']) ? strtolower(trim($input['secondaryemail'])) : null,
            'assistant' => $input['assistant'] ?? null,
            'birthdate' => $input['birthdate'] ?? null,
            'reports_to_id' => isset($input['reports_to_id']) ? (int) $input['reports_to_id'] : null,
            'leadsource' => $input['leadsource'] ?? null,
            'contact_status' => $input['contact_status'] ?? null,
        ], fn($value) => $value !== null && $value !== '');
    }
}