<?php

namespace App\Http\Controllers\Api;

use App\Application\DTOs\ChangePasswordRequest;
use App\Http\Controllers\Controller;
use App\Application\UseCases\GetAllUsersUseCase;
use App\Application\UseCases\CreateUserUseCase;
use App\Application\DTOs\CreateUserRequest;
use App\Application\DTOs\UpdateUserProfileRequest;
use App\Application\UseCases\ChangePasswordUseCase;
use App\Application\UseCases\DeleteUserUseCase;
use App\Application\UseCases\FindUserByFullNameUseCase;
use App\Application\UseCases\FindUsersByNameOrUsernameUseCase;
use App\Application\UseCases\GetMyProfileUseCase;
use App\Application\UseCases\UpdateUserProfileUseCase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class UserController extends Controller
{
    public function __construct(
        private readonly GetAllUsersUseCase $getAllUsersUseCase,
        private readonly CreateUserUseCase $createUserUseCase,
        private readonly UpdateUserProfileUseCase $updateUserProfileUseCase,
        private readonly ChangePasswordUseCase $changePasswordUseCase,
        private readonly DeleteUserUseCase $deleteUserUseCase,
        private readonly GetMyProfileUseCase $getMyProfileUseCase,
        private readonly FindUsersByNameOrUsernameUseCase $findUsersByNameOrUsernameUseCase,
        private readonly FindUserByFullNameUseCase $findUserByFullNameUseCase
    ) {}

    public function index(Request $request)
    {
        $page = (int) $request->get('page', 1);
        $perPage = (int) $request->get('per_page', 20);
        $search = $request->get('search');

        $paginator = $this->getAllUsersUseCase->execute($page, $perPage, $search);

        $data = array_map(function ($user) {
            return \App\Application\DTOs\UserDto::fromEntity($user);
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

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_name' => 'required|string|max:50|unique:vtiger.vtiger_users,user_name',
            'first_name' => 'required|string|max:50',
            'last_name' => 'required|string|max:50',
            'email' => 'required|email|max:100|unique:vtiger.vtiger_users,email1',
            'role' => 'required|in:Admin,Usuario,Cliente',
            'password' => 'required|string|min:6',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validación fallida',
                'messages' => $validator->errors()
            ], 422);
        }

        $authenticatedUser = $request->attributes->get('auth_user');
        if (!$authenticatedUser) {
            return response()->json(['error' => 'Usuario no autenticado'], 401);
        }
        $requestData = $request->all();

        $createRequest = new CreateUserRequest(
            user_name: $requestData['user_name'],
            first_name: $requestData['first_name'],
            last_name: $requestData['last_name'],
            email: $requestData['email'],
            role: $requestData['role'],
            password: $requestData['password'],
            phone_crm: $requestData['phone_crm'] ?? null,
            department: $requestData['department'] ?? null,
            reports_to_id: $requestData['reports_to_id'] ?? null,
        );

        $userId = $this->createUserUseCase->execute($createRequest, $authenticatedUser->id);

        return response()->json([
            'message' => 'Usuario creado exitosamente',
            'user_id' => $userId
        ], 201);
    }

    public function updateProfile(Request $request, int $id)
    {
        $validator = Validator::make($request->all(), [
            'first_name' => 'required|string|max:50',
            'last_name' => 'required|string|max:50',
            'user_name' => 'required|string|max:50',
            'email' => 'required|email|max:100',
            'role' => 'required|in:Admin,Usuario,Cliente',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validación fallida',
                'messages' => $validator->errors()
            ], 422);
        }

        $authenticatedUser = $request->attributes->get('auth_user');
        if (!$authenticatedUser) {
            return response()->json(['error' => 'Usuario no autenticado'], 401);
        }

        $requestData = $request->all();

        $updateRequest = new UpdateUserProfileRequest(
            id: $id,
            first_name: $requestData['first_name'],
            last_name: $requestData['last_name'],
            user_name: $requestData['user_name'],
            email: $requestData['email'],
            role: $requestData['role'],
            department: $requestData['department'] ?? null,
            phone_crm: $requestData['phone_crm'] ?? null,
            reports_to_id: $requestData['reports_to_id'] ?? null,
        );

        try {
            $success = $this->updateUserProfileUseCase->execute($updateRequest, $authenticatedUser->id);

            if ($success) {
                return response()->json(['message' => 'Perfil de usuario actualizado exitosamente']);
            }

            return response()->json(['error' => 'Usuario no encontrado'], 404);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al actualizar el perfil: ' . $e->getMessage()], 500);
        }
    }

    public function changePassword(Request $request, int $id)
    {
        $validator = Validator::make($request->all(), [
            'new_password' => 'required|string|min:6',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validación fallida',
                'messages' => $validator->errors()
            ], 422);
        }

        $authenticatedUser = $request->attributes->get('auth_user');
        if (!$authenticatedUser) {
            return response()->json(['error' => 'Usuario no autenticado'], 401);
        }

        // Verificar permisos: solo el mismo usuario o admin puede cambiar la contraseña
        if ($authenticatedUser->id !== $id && $authenticatedUser->is_admin !== '1') {
            return response()->json(['error' => 'No tienes permiso para cambiar esta contraseña'], 403);
        }

        $changeRequest = new ChangePasswordRequest(
            userId: $id,
            newPassword: $request->new_password
        );

        try {
            $success = $this->changePasswordUseCase->execute($changeRequest, $authenticatedUser->id);

            if ($success) {
                return response()->json(['message' => 'Contraseña actualizada exitosamente']);
            }

            return response()->json(['error' => 'Usuario no encontrado'], 404);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al cambiar la contraseña: ' . $e->getMessage()], 500);
        }
    }

    public function destroy(Request $request, int $id)
    {
        $authenticatedUser = $request->attributes->get('auth_user');
        if (!$authenticatedUser) {
            return response()->json(['error' => 'Usuario no autenticado'], 401);
        }

        try {
            $success = $this->deleteUserUseCase->execute($id, $authenticatedUser->id);

            if ($success) {
                return response()->json(['message' => 'Usuario eliminado exitosamente']);
            }

            return response()->json(['error' => 'Usuario no encontrado o ya eliminado'], 404);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 403);
        }
    }

    public function getMyProfile(Request $request)
    {
        $authenticatedUser = $request->attributes->get('auth_user');
        if (!$authenticatedUser) {
            return response()->json(['error' => 'Usuario no autenticado'], 401);
        }

        $userEntity = $this->getMyProfileUseCase->execute($authenticatedUser->id);

        if (!$userEntity) {
            return response()->json(['error' => 'Usuario no encontrado'], 404);
        }

        return response()->json([
            'data' => [
                'id' => $userEntity->id,
                'first_name' => $userEntity->first_name,
                'last_name' => $userEntity->last_name,
                'username' => $userEntity->user_name,
                'email' => $userEntity->email,
                'role' => $userEntity->role,
                'department' => $userEntity->department,
                'phone' => $userEntity->phone_crm,
                'status' => $userEntity->status,
                'is_active' => $userEntity->is_active,
            ]
        ]);
    }

    public function updateMyProfile(Request $request)
    {
        $authenticatedUser = $request->attributes->get('auth_user');
        if (!$authenticatedUser) {
            return response()->json(['error' => 'Usuario no autenticado'], 401);
        }

        $validator = Validator::make($request->all(), [
            'first_name' => 'required|string|max:50',
            'last_name' => 'required|string|max:50',
            'user_name' => 'required|string|max:50',
            'email' => 'required|email|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validación fallida',
                'messages' => $validator->errors()
            ], 422);
        }

        $requestData = $request->all();

        $updateRequest = new UpdateUserProfileRequest(
            id: $authenticatedUser->id,
            first_name: $requestData['first_name'],
            last_name: $requestData['last_name'],
            user_name: $requestData['user_name'],
            email: $requestData['email'],
            role: $authenticatedUser->role,
            department: $requestData['department'] ?? null,
            phone_crm: $requestData['phone_crm'] ?? null,
            reports_to_id: null,
        );

        try {
            $success = $this->updateUserProfileUseCase->execute($updateRequest, $authenticatedUser->id);

            if ($success) {
                return response()->json(['message' => 'Perfil actualizado exitosamente']);
            }

            return response()->json(['error' => 'Error al actualizar el perfil'], 500);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function searchUsers(Request $request)
    {
        $searchTerm = trim($request->get('q', ''));

        if (strlen($searchTerm) < 2) {
            return response()->json(['data' => []]);
        }

        try {
            $users = $this->findUsersByNameOrUsernameUseCase->execute($searchTerm);
            return response()->json(['data' => $users]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al buscar usuarios'], 500);
        }
    }

    public function findUserByFullName(Request $request)
    {
        $fullName = trim($request->get('full_name', ''));

        if (!$fullName) {
            return response()->json(['error' => 'Nombre completo requerido'], 422);
        }

        try {
            $user = $this->findUserByFullNameUseCase->execute($fullName);

            if (!$user) {
                return response()->json(['error' => 'Usuario no encontrado'], 404);
            }

            return response()->json(['data' => $user]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al buscar usuario'], 500);
        }
    }
}
