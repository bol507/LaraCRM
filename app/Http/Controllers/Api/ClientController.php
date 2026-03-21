<?php

namespace App\Http\Controllers\Api;

use App\Application\DTOs\ClientDto;
use App\Application\DTOs\CreateClientRequest;
use App\Application\DTOs\UpdateClientRequest;
use App\Application\UseCases\Client\GetClientSummaryUseCase;
use App\Application\UseCases\CreateClientUseCase;
use App\Application\UseCases\DeleteClientUseCase;
use App\Application\UseCases\FindClientByAccountNameUseCase;
use App\Application\UseCases\FindClientsByNameOrEmailUseCase;
use App\Http\Controllers\Controller;
use App\Application\UseCases\GetClientsUseCase;
use App\Application\UseCases\GetClientByIdUseCase;
use App\Application\UseCases\UpdateClientUseCase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Class ClientController
 * 
 * REST API controller for managing client (account) resources in the CRM system.
 * 
 * This controller handles HTTP requests for client entity operations including
 * listing, retrieving, creating, updating, deleting, and searching clients.
 * It follows the clean architecture pattern by delegating business logic to
 * application use cases and returning standardized JSON responses.
 * 
 * All endpoints require authentication via JWT bearer token. Authorization
 * rules (e.g., who can create/update/delete clients) are enforced at the
 * use case or repository layer.
 * 
 * Response format:
 * - Success: HTTP 200/201 with JSON body containing 'data' and/or 'meta' keys
 * - Validation error: HTTP 422 with JSON body containing 'error' and 'messages'
 * - Not found: HTTP 404 with JSON body containing 'error'
 * - Server error: HTTP 500 with JSON body containing 'error'
 * 
 * Pagination format (for list endpoints):
 * {
 *   "data": [...],
 *   "meta": {
 *     "current_page": 1,
 *     "last_page": 5,
 *     "per_page": 20,
 *     "total": 95
 *   },
 *   "links": {
 *     "first": "http://.../api/clients?page=1",
 *     "last": "http://.../api/clients?page=5",
 *     "prev": null,
 *     "next": "http://.../api/clients?page=2"
 *   }
 * }
 * 
 * @package App\Http\Controllers\Api
 * @author Bolivar Delgado <bolivar.delagado@gmail.com>
 * @version 1.0.0
 * 
 * @see \App\Application\UseCases\GetClientsUseCase For list operation logic
 * @see \App\Application\UseCases\GetClientByIdUseCase For single client retrieval
 * @see \App\Application\UseCases\CreateClientUseCase For client creation logic
 * @see \App\Application\UseCases\UpdateClientUseCase For client update logic
 * @see \App\Application\UseCases\DeleteClientUseCase For client deletion logic
 * @see \App\Application\DTOs\ClientDto For response data structure
 * @see \App\Application\DTOs\CreateClientRequest For creation request validation
 * @see \App\Application\DTOs\UpdateClientRequest For update request validation
 */
class ClientController extends Controller
{
    

    /**
     * ClientController constructor.
     * 
     * Injects all required use case dependencies via constructor injection.
     * This enables dependency inversion, facilitating unit testing with mock
     * use cases and promoting loose coupling between the controller and
     * business logic implementations.
     * 
     * All dependencies are marked as readonly to ensure immutability after
     * instantiation, promoting predictable behavior and preventing accidental
     * modification of service dependencies.
     * 
     * @param GetClientsUseCase $getClientsUseCase Use case for listing clients
     * @param GetClientByIdUseCase $getClientByIdUseCase Use case for retrieving single client
     * @param CreateClientUseCase $createClientUseCase Use case for creating clients
     * @param UpdateClientUseCase $updateClientUseCase Use case for updating clients
     * @param DeleteClientUseCase $deleteClientUseCase Use case for deleting clients
     * @param FindClientsByNameOrEmailUseCase $findClientsByNameOrEmailUseCase Use case for name/email search
     * @param FindClientByAccountNameUseCase $findClientByAccountNameUseCase Use case for exact name lookup
     * @param GetClientSummaryUseCase $getClientSummaryUseCase Use case for client summary statistics
     * 
     * @return void
     */
    public function __construct(
        private readonly GetClientsUseCase $getClientsUseCase,
        private readonly GetClientByIdUseCase $getClientByIdUseCase,
        private readonly CreateClientUseCase $createClientUseCase,
        private readonly UpdateClientUseCase $updateClientUseCase,
        private readonly DeleteClientUseCase $deleteClientUseCase,
        private readonly FindClientsByNameOrEmailUseCase $findClientsByNameOrEmailUseCase,
        private readonly FindClientByAccountNameUseCase $findClientByAccountNameUseCase,
        private readonly GetClientSummaryUseCase $getClientSummaryUseCase,
    ) {}

    /**
     * Retrieve a paginated list of clients with optional search and filters.
     * 
     * This endpoint returns a list of client accounts with support for pagination,
     * text search, and filtering by industry, rating, or account type. Results
     * are formatted as ClientDto objects for consistent API responses.
     * 
     * Query parameters:
     * - page: Page number (1-based, default: 1)
     * - per_page: Items per page (default: 20, max: 100)
     * - search: Case-insensitive partial match on accountname, account_no, or email1
     * - filters[industry]: Filter by industry value (exact match)
     * - filters[rating]: Filter by rating value (exact match)
     * - filters[account_type]: Filter by account type value (exact match)
     * 
     * Authentication: Requires valid JWT bearer token in Authorization header.
     * Authorization: Access controlled by repository layer based on user permissions.
     * 
     * @param Request $request The HTTP request containing query parameters.
     * 
     * @return JsonResponse JSON response with paginated client data:
     *                      {
     *                        "data": ClientDto[],
     *                        "meta": { "current_page", "last_page", "per_page", "total" },
     *                        "links": { "first", "last", "prev", "next" }
     *                      }
     * 
     * @throws \InvalidArgumentException If pagination parameters are invalid
     * @throws \RuntimeException If database query fails
     * 
     * @api
     * 
     * @endpoint GET /api/clients
     * 
     * @request-param int $page Page number (default: 1)
     * @request-param int $per_page Items per page (default: 20)
     * @request-param string $search Optional search term for partial matching
     * @request-param string $filters[industry] Optional industry filter (exact match)
     * @request-param string $filters[rating] Optional rating filter (exact match)
     * @request-param string $filters[account_type] Optional account type filter (exact match)
     * 
     * @response 200 {
     *   "data": [ClientDto],
     *   "meta": { "current_page": 1, "last_page": 5, "per_page": 20, "total": 95 },
     *   "links": { "first": "...", "last": "...", "prev": null, "next": "..." }
     * }
     * 
     * @response 401 { "error": "Unauthorized" }
     * @response 500 { "error": "Internal server error" }
     * 
     * @example Request
     * GET /api/clients?page=1&per_page=10&search=Acme&filters[industry]=Technology
     * Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...
     * 
     * @example Response 200
     * {
     *   "data": [
     *     {
     *       "accountid": 123,
     *       "accountname": "Acme Corporation",
     *       "account_no": "ACC-000123",
     *       "industry": "Technology",
     *       "email1": "contact@acme.com",
     *       ...
     *     }
     *   ],
     *   "meta": { "current_page": 1, "last_page": 3, "per_page": 10, "total": 25 },
     *   "links": { ... }
     * }
     * 
     * @see GetClientsUseCase::execute() For business logic implementation
     * @see ClientDto::fromEntity() For entity to DTO transformation
     */
    public function index(Request $request)
    {
        $page = (int) $request->get('page', 1);
        $perPage = (int) $request->get('per_page', 20);
        $search = $request->get('search');
        $filters = $request->only(['industry', 'rating', 'account_type']);

        $paginator = $this->getClientsUseCase->execute($page, $perPage, $search, $filters);

        $data = array_map(function ($client) {
            return ClientDto::fromEntity($client);
        }, $paginator->items());

        return response()->json([
            'data' => $data,
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
            ]
        ]);
    }

    /**
     * Retrieve a single client by its unique identifier.
     * 
     * This endpoint returns comprehensive details for a specific client account,
     * including all core fields, address information, audit metadata, and
     * denormalized related entity data where applicable.
     * 
     * Authentication: Requires valid JWT bearer token in Authorization header.
     * Authorization: Access controlled by repository layer; returns 404 if client
     * does not exist or user lacks permission to view.
     * 
     * @param int $id The unique identifier (accountid) of the client to retrieve.
     *                Must be a positive integer corresponding to an existing
     *                client record.
     * 
     * @return JsonResponse JSON response with client data as ClientDto:
     *                      {
     *                        "accountid": 123,
     *                        "accountname": "Acme Corporation",
     *                        "account_no": "ACC-000123",
     *                        ...
     *                      }
     * 
     * @throws NotFoundHttpException If client with specified ID does not exist
     *                               or user lacks permission to view
     * @throws \RuntimeException If database query fails
     * 
     * @api
     * 
     * @endpoint GET /api/clients/{id}
     * 
     * @path-param int $id Client accountid
     * 
     * @response 200 ClientDto
     * @response 401 { "error": "Unauthorized" }
     * @response 404 { "error": "Client not found" }
     * @response 500 { "error": "Internal server error" }
     * 
     * @example Request
     * GET /api/clients/123
     * Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...
     * 
     * @example Response 200
     * {
     *   "accountid": 123,
     *   "accountname": "Acme Corporation",
     *   "account_no": "ACC-000123",
     *   "industry": "Technology",
     *   "annualrevenue": 5000000,
     *   "email1": "contact@acme.com",
     *   "phone": "+1-555-0100",
     *   "bill_street": "123 Main St",
     *   "bill_city": "New York",
     *   "bill_country": "USA",
     *   "createdtime": "2025-01-15 10:00:00",
     *   "modifiedtime": "2026-03-10 14:30:00",
     *   "isActive": true
     * }
     * 
     * @see GetClientByIdUseCase::execute() For business logic implementation
     * @see ClientDto::fromEntity() For entity to DTO transformation
     */
    public function show(int $id)
    {
        $client = $this->getClientByIdUseCase->execute($id);
        return response()->json(ClientDto::fromEntity($client));
    }

    /**
     * Create a new client record in the CRM system.
     * 
     * This endpoint creates a new client account with validated request data.
     * It handles creation across multiple related database tables (vtiger_account,
     * vtiger_crmentity, address tables) within a transaction to ensure data
     * consistency.
     * 
     * Required fields:
     * - accountname: Client organization name (max 100 characters)
     * 
     * Optional fields:
     * - account_no: Account number (auto-generated if not provided)
     * - account_type, industry, rating, ownership: Classification fields
     * - annualrevenue: Annual revenue amount (numeric, min: 0)
     * - employees: Employee count (integer, min: 0)
     * - phone, otherphone, email1, email2, website, fax: Contact information
     * - emailoptout, notify_owner, isconvertedfromlead: Boolean flags ('0' or '1')
     * - tags: Comma-separated tags for categorization
     * - bill_*: Billing address fields
     * - ship_*: Shipping address fields
     * 
     * Authentication: Requires valid JWT bearer token. User ID extracted from
     * request attributes for audit tracking (smcreatorid, smownerid).
     * 
     * @param Request $request The HTTP request containing client creation data
     *                         in JSON format with Content-Type: application/json.
     * 
     * @return JsonResponse JSON response with creation result:
     *                      {
     *                        "message": "Cliente creado exitosamente",
     *                        "client_id": 456
     *                      }
     * 
     * @throws \InvalidArgumentException If required fields are missing or invalid
     * @throws \RuntimeException If database transaction fails
     * 
     * @api
     * 
     * @endpoint POST /api/clients
     * 
     * @request-body {
     *   "accountname": "string (required, max 100)",
     *   "account_no": "string (optional)",
     *   "email1": "string (optional, email format)",
     *   "phone": "string (optional, max 30)",
     *   "annualrevenue": "number (optional, min 0)",
     *   "employees": "integer (optional, min 0)",
     *   "emailoptout": "string '0' or '1' (optional, default '0')",
     *   "notify_owner": "string '0' or '1' (optional, default '0')",
     *   "isconvertedfromlead": "string '0' or '1' (optional, default '0')",
     *   "bill_street": "string (optional)",
     *   "bill_city": "string (optional)",
     *   "bill_country": "string (optional)",
     *   "ship_street": "string (optional)",
     *   ...
     * }
     * 
     * @response 201 {
     *   "message": "Cliente creado exitosamente",
     *   "client_id": 456
     * }
     * @response 401 { "error": "Usuario no autenticado" }
     * @response 422 {
     *   "error": "Validación fallida",
     *   "messages": { "accountname": ["The accountname field is required."] }
     * }
     * @response 500 { "error": "Internal server error" }
     * 
     * @example Request
     * POST /api/clients
     * Content-Type: application/json
     * Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...
     * 
     * {
     *   "accountname": "Acme Corporation",
     *   "industry": "Technology",
     *   "email1": "contact@acme.com",
     *   "phone": "+1-555-0100",
     *   "bill_street": "123 Main St",
     *   "bill_city": "New York",
     *   "bill_country": "USA"
     * }
     * 
     * @example Response 201
     * {
     *   "message": "Cliente creado exitosamente",
     *   "client_id": 456
     * }
     * 
     * @see CreateClientUseCase::execute() For business logic implementation
     * @see CreateClientRequest For request DTO structure and validation
     */
    public function store(Request $request)
    {
        // validate request
        $validator = Validator::make($request->all(), [
            'accountname' => 'required|string|max:100',
            'email1' => 'nullable|email',
            'phone' => 'nullable|string|max:30',
            'annualrevenue' => 'nullable|numeric|min:0',
            'employees' => 'nullable|integer|min:0',
            'emailoptout' => 'in:0,1',
            'notify_owner' => 'in:0,1',
            'isconvertedfromlead' => 'in:0,1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validación fallida',
                'messages' => $validator->errors()
            ], 422);
        }

        $user = $request->attributes->get('auth_user');

        if (!$user) {
            return response()->json(['error' => 'Usuario no autenticado'], 401);
        }

        $requestData = $request->all();

        $createRequest = new CreateClientRequest(
            accountname: $requestData['accountname'],
            account_no: $requestData['account_no'] ?? null,
            account_type: $requestData['account_type'] ?? null,
            industry: $requestData['industry'] ?? null,
            annualrevenue: isset($requestData['annualrevenue']) ? (float)$requestData['annualrevenue'] : null,
            rating: $requestData['rating'] ?? null,
            ownership: $requestData['ownership'] ?? null,
            siccode: $requestData['siccode'] ?? null,
            tickersymbol: $requestData['tickersymbol'] ?? null,
            phone: $requestData['phone'] ?? null,
            otherphone: $requestData['otherphone'] ?? null,
            email1: $requestData['email1'] ?? null,
            email2: $requestData['email2'] ?? null,
            website: $requestData['website'] ?? null,
            fax: $requestData['fax'] ?? null,
            employees: isset($requestData['employees']) ? (int)$requestData['employees'] : null,
            emailoptout: $requestData['emailoptout'] ?? '0',
            notify_owner: $requestData['notify_owner'] ?? '0',
            isconvertedfromlead: $requestData['isconvertedfromlead'] ?? '0',
            tags: $requestData['tags'] ?? null,
            // address of invoice
            bill_street: $requestData['bill_street'] ?? null,
            bill_city: $requestData['bill_city'] ?? null,
            bill_state: $requestData['bill_state'] ?? null,
            bill_code: $requestData['bill_code'] ?? null,
            bill_country: $requestData['bill_country'] ?? null,
            bill_pobox: $requestData['bill_pobox'] ?? null,
            ship_street: $requestData['ship_street'] ?? null,
            ship_city: $requestData['ship_city'] ?? null,
            ship_state: $requestData['ship_state'] ?? null,
            ship_code: $requestData['ship_code'] ?? null,
            ship_country: $requestData['ship_country'] ?? null,
            ship_pobox: $requestData['ship_pobox'] ?? null,
        );

        $clientId = $this->createClientUseCase->execute($createRequest, $user->getId());

        return response()->json([
            'message' => 'Cliente creado exitosamente',
            'client_id' => $clientId
        ], 201);
    }

    /**
     * Update an existing client record in the CRM system.
     * 
     * This endpoint updates a client account with validated request data.
     * It handles updates across multiple related database tables within a
     * transaction to ensure data consistency. Only provided fields are updated;
     * unspecified fields retain their existing values.
     * 
     * Required fields:
     * - accountname: Client organization name (max 100 characters)
     * - account_no: Account number (max 50 characters)
     * 
     * Optional fields: Same as store() endpoint. Null values explicitly clear
     * the corresponding field in the database.
     * 
     * Authentication: Requires valid JWT bearer token. User ID extracted from
     * request attributes for audit tracking (modifiedby field).
     * Authorization: User must have permission to update the specified client.
     * 
     * @param Request $request The HTTP request containing client update data
     *                         in JSON format with Content-Type: application/json.
     * @param int $id The unique identifier (accountid) of the client to update.
     *                Must be a positive integer corresponding to an existing
     *                client record.
     * 
     * @return JsonResponse JSON response with update result:
     *                      { "message": "Cliente actualizado exitosamente" }
     *                      or
     *                      { "error": "Error al actualizar el cliente" }
     * 
     * @throws \InvalidArgumentException If required fields are missing or invalid
     * @throws \RuntimeException If database transaction fails
     * 
     * @api
     * 
     * @endpoint PUT /api/clients/{id}
     * 
     * @path-param int $id Client accountid
     * 
     * @request-body {
     *   "accountname": "string (required, max 100)",
     *   "account_no": "string (required, max 50)",
     *   "email1": "string (optional, email format)",
     *   "phone": "string (optional, max 30)",
     *   "annualrevenue": "number (optional, min 0)",
     *   "employees": "integer (optional, min 0)",
     *   "emailoptout": "string '0' or '1' (optional)",
     *   "notify_owner": "string '0' or '1' (optional)",
     *   "isconvertedfromlead": "string '0' or '1' (optional)",
     *   "bill_street": "string (optional)",
     *   ...
     * }
     * 
     * @response 200 { "message": "Cliente actualizado exitosamente" }
     * @response 401 { "error": "Usuario no autenticado" }
     * @response 404 { "error": "Client not found" }
     * @response 422 {
     *   "error": "Validación fallida",
     *   "messages": { "accountname": ["The accountname field is required."] }
     * }
     * @response 500 { "error": "Error al actualizar el cliente" }
     * 
     * @example Request
     * PUT /api/clients/123
     * Content-Type: application/json
     * Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...
     * 
     * {
     *   "accountname": "Acme Corporation",
     *   "account_no": "ACC-000123",
     *   "phone": "+1-555-0199",
     *   "bill_street": "456 Updated Ave"
     * }
     * 
     * @example Response 200
     * {
     *   "message": "Cliente actualizado exitosamente"
     * }
     * 
     * @see UpdateClientUseCase::execute() For business logic implementation
     * @see UpdateClientRequest For request DTO structure and validation
     */
    public function update(Request $request, int $id)
    {

        $validator = Validator::make($request->all(), [
            'accountname' => 'required|string|max:100',
            'account_no' => 'required|string|max:50',
            'email1' => 'nullable|email',
            'phone' => 'nullable|string|max:30',
            'annualrevenue' => 'nullable|numeric|min:0',
            'employees' => 'nullable|integer|min:0',
            'emailoptout' => 'in:0,1',
            'notify_owner' => 'in:0,1',
            'isconvertedfromlead' => 'in:0,1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validación fallida',
                'messages' => $validator->errors()
            ], 422);
        }

        $user = $request->attributes->get('auth_user');
        if (!$user) {
            return response()->json(['error' => 'Usuario no autenticado'], 401);
        }

        $requestData = $request->all();

        $updateRequest = new UpdateClientRequest(
            id: $id,
            accountname: $requestData['accountname'],
            account_no: $requestData['account_no'],
            account_type: $requestData['account_type'] ?? null,
            industry: $requestData['industry'] ?? null,
            annualrevenue: isset($requestData['annualrevenue']) ? (float)$requestData['annualrevenue'] : null,
            rating: $requestData['rating'] ?? null,
            ownership: $requestData['ownership'] ?? null,
            siccode: $requestData['siccode'] ?? null,
            tickersymbol: $requestData['tickersymbol'] ?? null,
            phone: $requestData['phone'] ?? null,
            otherphone: $requestData['otherphone'] ?? null,
            email1: $requestData['email1'] ?? null,
            email2: $requestData['email2'] ?? null,
            website: $requestData['website'] ?? null,
            fax: $requestData['fax'] ?? null,
            employees: isset($requestData['employees']) ? (int)$requestData['employees'] : null,
            emailoptout: $requestData['emailoptout'] ?? '0',
            notify_owner: $requestData['notify_owner'] ?? '0',
            isconvertedfromlead: $requestData['isconvertedfromlead'] ?? '0',
            tags: $requestData['tags'] ?? null,

            bill_street: $requestData['bill_street'] ?? null,
            bill_city: $requestData['bill_city'] ?? null,
            bill_state: $requestData['bill_state'] ?? null,
            bill_code: $requestData['bill_code'] ?? null,
            bill_country: $requestData['bill_country'] ?? null,
            bill_pobox: $requestData['bill_pobox'] ?? null,
            ship_street: $requestData['ship_street'] ?? null,
            ship_city: $requestData['ship_city'] ?? null,
            ship_state: $requestData['ship_state'] ?? null,
            ship_code: $requestData['ship_code'] ?? null,
            ship_country: $requestData['ship_country'] ?? null,
            ship_pobox: $requestData['ship_pobox'] ?? null,
        );

        $success = $this->updateClientUseCase->execute($updateRequest, $user->getId());

        if ($success) {
            return response()->json(['message' => 'Cliente actualizado exitosamente']);
        }

        return response()->json(['error' => 'Error al actualizar el cliente'], 500);
    }

    /**
     * Soft delete a client record by marking it as deleted.
     * 
     * This endpoint performs a soft delete operation by updating the deleted
     * flag in the vtiger_crmentity table rather than removing the record from
     * the database. This preserves audit history and allows for potential
     * restoration via administrative tools.
     * 
     * After deletion, the client will no longer appear in normal list queries
     * or search results unless explicitly requested with deleted records included.
     * Related entities (opportunities, quotes, projects, contacts) are not
     * automatically deleted and maintain independent lifecycle management.
     * 
     * Authentication: Requires valid JWT bearer token.
     * Authorization: User must have permission to delete the specified client.
     * 
     * @param int $id The unique identifier (accountid) of the client to delete.
     *                Must be a positive integer corresponding to an existing
     *                client record.
     * 
     * @return JsonResponse JSON response with deletion result:
     *                      { "message": "Cliente eliminado exitosamente" }
     *                      or
     *                      { "error": "Cliente no encontrado o ya eliminado" }
     * 
     * @throws \RuntimeException If database update fails
     * 
     * @api
     * 
     * @endpoint DELETE /api/clients/{id}
     * 
     * @path-param int $id Client accountid
     * 
     * @response 200 { "message": "Cliente eliminado exitosamente" }
     * @response 404 { "error": "Cliente no encontrado o ya eliminado" }
     * @response 500 { "error": "Internal server error" }
     * 
     * @example Request
     * DELETE /api/clients/123
     * Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...
     * 
     * @example Response 200
     * {
     *   "message": "Cliente eliminado exitosamente"
     * }
     * 
     * @example Response 404
     * {
     *   "error": "Cliente no encontrado o ya eliminado"
     * }
     * 
     * @see DeleteClientUseCase::execute() For business logic implementation
     */
    public function destroy(int $id)
    {
        $success = $this->deleteClientUseCase->execute($id);

        if ($success) {
            return response()->json(['message' => 'Cliente eliminado exitosamente']);
        }

        return response()->json(['error' => 'Cliente no encontrado o ya eliminado'], 404);
    }

    /**
     * Search clients by name or email address for autocomplete functionality.
     * 
     * This endpoint performs a case-insensitive partial match search across
     * client accountname and email1 fields. It is optimized for autocomplete
     * dropdowns and quick lookup scenarios where the user may not know the
     * exact client identifier.
     * 
     * Results are limited to a reasonable number for performance and UI usability.
     * Each result includes minimal fields (id, accountname, email1, assigned_user_id)
     * suitable for display in dropdown menus.
     * 
     * Authentication: Requires valid JWT bearer token.
     * Authorization: Access controlled by repository layer based on user permissions.
     * 
     * @param Request $request The HTTP request containing the search query.
     * 
     * @return JsonResponse JSON response with search results:
     *                      {
     *                        "data": [
     *                          {
     *                            "id": 123,
     *                            "accountname": "Acme Corporation",
     *                            "email1": "contact@acme.com",
     *                            "assigned_user_id": 5
     *                          },
     *                          ...
     *                        ]
     *                      }
     * 
     * @api
     * 
     * @endpoint GET /api/clients/search
     * 
     * @request-param string $q Search query (minimum 2 characters, default: empty)
     * 
     * @response 200 { "data": [ { "id", "accountname", "email1", "assigned_user_id" } ] }
     * @response 401 { "error": "Unauthorized" }
     * 
     * @example Request
     * GET /api/clients/search?q=acme
     * Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...
     * 
     * @example Response 200
     * {
     *   "data": [
     *     {
     *       "id": 123,
     *       "accountname": "Acme Corporation",
     *       "email1": "contact@acme.com",
     *       "assigned_user_id": 5
     *     },
     *     {
     *       "id": 456,
     *       "accountname": "Acme Subsidiary Ltd",
     *       "email1": "info@acme-subsidiary.com",
     *       "assigned_user_id": 5
     *     }
     *   ]
     * }
     * 
     * @example Response 200 (no results)
     * {
     *   "data": []
     * }
     * 
     * @see FindClientsByNameOrEmailUseCase::execute() For business logic implementation
     */
    public function search(Request $request)
    {
        $searchTerm = $request->get('q', '');

        if (strlen($searchTerm) < 2) {
            return response()->json(['data' => []]);
        }

        $clients = $this->findClientsByNameOrEmailUseCase->execute($searchTerm);

        return response()->json(['data' => $clients]);
    }

    /**
     * Find a client by exact account name match.
     * 
     * This endpoint performs a case-insensitive exact match search on the
     * accountname field. It is designed for duplicate checking during client
     * creation workflows or for quick lookups when the exact account name
     * is known.
     * 
     * Returns minimal client information (id, accountname, email1, assigned_user_id)
     * rather than a full ClientDto, optimizing for performance when only
     * identification is needed.
     * 
     * Authentication: Requires valid JWT bearer token.
     * Authorization: Access controlled by repository layer based on user permissions.
     * 
     * @param Request $request The HTTP request containing the account name.
     * 
     * @return JsonResponse JSON response with client data if found:
     *                      {
     *                        "data": {
     *                          "id": 123,
     *                          "accountname": "Acme Corporation",
     *                          "email1": "contact@acme.com",
     *                          "assigned_user_id": 5
     *                        }
     *                      }
     *                      or error response if not found or invalid request.
     * 
     * @api
     * 
     * @endpoint GET /api/clients/by-name
     * 
     * @request-param string $accountname Account name for exact match (required)
     * 
     * @response 200 { "data": { "id", "accountname", "email1", "assigned_user_id" } }
     * @response 401 { "error": "Unauthorized" }
     * @response 404 { "error": "Cliente no encontrado" }
     * @response 422 { "error": "Nombre de cuenta requerido" }
     * 
     * @example Request
     * GET /api/clients/by-name?accountname=Acme%20Corporation
     * Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...
     * 
     * @example Response 200
     * {
     *   "data": {
     *     "id": 123,
     *     "accountname": "Acme Corporation",
     *     "email1": "contact@acme.com",
     *     "assigned_user_id": 5
     *   }
     * }
     * 
     * @example Response 404
     * {
     *   "error": "Cliente no encontrado"
     * }
     * 
     * @see FindClientByAccountNameUseCase::execute() For business logic implementation
     */
    public function findByAccountName(Request $request)
    {
        $accountName = $request->get('accountname');

        if (!$accountName) {
            return response()->json(['error' => 'Nombre de cuenta requerido'], 422);
        }

        $client = $this->findClientByAccountNameUseCase->execute($accountName);

        if (!$client) {
            return response()->json(['error' => 'Cliente no encontrado'], 404);
        }

        return response()->json(['data' => $client]);
    }

    /**
     * Get summary of related entities for a client.
     * 
     * This endpoint returns aggregated statistics about a specific client,
     * including counts of related entities (opportunities, quotes, projects,
     * contacts) and the most recent activity timestamp across all related
     * records.
     * 
     * Summary data is optimized for dashboard widgets and client overview
     * displays, avoiding the need to fetch and aggregate related entity
     * lists on the frontend.
     * 
     * Authentication: Requires valid JWT bearer token.
     * Authorization: User must have permission to view the specified client
     * and its related entities.
     * 
     * @param Request $request The HTTP request (unused, included for signature consistency).
     * @param int $id The unique identifier (accountid) of the client for which
     *                to retrieve summary statistics.
     * 
     * @return JsonResponse JSON response with client summary data:
     *                      {
     *                        "clientId": 123,
     *                        "opportunitiesCount": 5,
     *                        "quotesCount": 12,
     *                        "projectsCount": 3,
     *                        "contactsCount": 8,
     *                        "lastActivity": "2026-03-18 14:30:00"
     *                      }
     * 
     * @throws NotFoundHttpException If client with specified ID does not exist
     *                               or user lacks permission to view
     * @throws \RuntimeException If database query fails during summary calculation
     * 
     * @api
     * 
     * @endpoint GET /api/clients/{id}/summary
     * 
     * @path-param int $id Client accountid
     * 
     * @response 200 {
     *   "clientId": 123,
     *   "opportunitiesCount": 5,
     *   "quotesCount": 12,
     *   "projectsCount": 3,
     *   "contactsCount": 8,
     *   "lastActivity": "2026-03-18 14:30:00"
     * }
     * @response 401 { "error": "Unauthorized" }
     * @response 404 { "error": "Client not found" }
     * @response 500 { "error": "Internal server error" }
     * 
     * @example Request
     * GET /api/clients/123/summary
     * Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...
     * 
     * @example Response 200
     * {
     *   "clientId": 123,
     *   "opportunitiesCount": 5,
     *   "quotesCount": 12,
     *   "projectsCount": 3,
     *   "contactsCount": 8,
     *   "lastActivity": "2026-03-18 14:30:00"
     * }
     * 
     * @see GetClientSummaryUseCase::execute() For business logic implementation
     * @see \App\Domain\Entities\ClientSummary::toArray() For response structure
     */
    public function summary(Request $request, int $id): JsonResponse
    {
        try {
            $summary = $this->getClientSummaryUseCase->execute($id);

            return response()->json($summary->toArray(), 200);
        } catch (\InvalidArgumentException $e) {
            throw new NotFoundHttpException($e->getMessage());
        }
    }
}