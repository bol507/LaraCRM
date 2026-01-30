<?php

namespace App\Http\Controllers\Api;

use App\Application\DTOs\ClientDto;
use App\Application\DTOs\CreateClientRequest;
use App\Application\UseCases\CreateClientUseCase;
use App\Http\Controllers\Controller;
use App\Application\UseCases\GetClientsUseCase;
use App\Application\UseCases\GetClientByIdUseCase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ClientController extends Controller
{
    public function __construct(
        private readonly GetClientsUseCase $getClientsUseCase,
        private readonly GetClientByIdUseCase $getClientByIdUseCase,
        private readonly CreateClientUseCase $createClientUseCase
    ) {}

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

    public function show(int $id)
    {
        $client = $this->getClientByIdUseCase->execute($id);
        return response()->json(ClientDto::fromEntity($client));
    }

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

        $clientId = $this->createClientUseCase->execute($createRequest);

        return response()->json([
            'message' => 'Cliente creado exitosamente',
            'client_id' => $clientId
        ], 201);
    }
}
