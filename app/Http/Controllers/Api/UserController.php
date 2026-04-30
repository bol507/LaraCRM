<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Application\DTOs\User\ChangePasswordRequest;
use App\Application\DTOs\User\CreateUserRequest;
use App\Application\DTOs\User\UpdateUserProfileRequest;
use App\Application\DTOs\User\UpdateUserRequest;
use App\Application\DTOs\User\UserDto;
use App\Application\UseCases\User\ChangePasswordUseCase;
use App\Application\UseCases\User\CreateUserUseCase;
use App\Application\UseCases\User\DeleteUserUseCase;
use App\Application\UseCases\User\FindUserByFullNameUseCase;
use App\Application\UseCases\User\FindUserByIdUseCase;
use App\Application\UseCases\User\FindUsersByNameOrUsernameUseCase;
use App\Application\UseCases\User\GetAllUsersUseCase;
use App\Application\UseCases\User\GetMyProfileUseCase;
use App\Application\UseCases\User\UpdateUserProfileUseCase;
use App\Application\UseCases\User\UpdateUserUseCase;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use RuntimeException;

/**
 * REST API controller for User management operations.
 * 
 * Handles HTTP requests for user CRUD operations, profile management,
 * and search functionality. All responses use UserDto for consistent
 * API contract across the application.
 * 
 * @package App\Http\Controllers\Api
 * @see \App\Domain\Entities\User
 * @see \App\Application\DTOs\UserDto
 */
class UserController extends Controller
{
    /**
     * Constructor with dependency injection of use cases.
     * 
     * @param GetAllUsersUseCase $getAllUsersUseCase Use case for listing users
     * @param CreateUserUseCase $createUserUseCase Use case for creating users
     * @param UpdateUserProfileUseCase $updateUserProfileUseCase Use case for updating profiles
     * @param ChangePasswordUseCase $changePasswordUseCase Use case for password changes
     * @param DeleteUserUseCase $deleteUserUseCase Use case for soft-deleting users
     * @param GetMyProfileUseCase $getMyProfileUseCase Use case for retrieving current user profile
     * @param FindUsersByNameOrUsernameUseCase $findUsersByNameOrUsernameUseCase Use case for autocomplete search
     * @param FindUserByFullNameUseCase $findUserByFullNameUseCase Use case for full-name search
     * @param FindUserByIdUseCase $findUserByIdUseCase Use case for retrieving user by ID
     */
    public function __construct(
        private readonly GetAllUsersUseCase $getAllUsersUseCase,
        private readonly CreateUserUseCase $createUserUseCase,
        private readonly UpdateUserUseCase $updateUserUseCase,
        private readonly UpdateUserProfileUseCase $updateUserProfileUseCase,
        private readonly ChangePasswordUseCase $changePasswordUseCase,
        private readonly DeleteUserUseCase $deleteUserUseCase,
        private readonly GetMyProfileUseCase $getMyProfileUseCase,
        private readonly FindUsersByNameOrUsernameUseCase $findUsersByNameOrUsernameUseCase,
        private readonly FindUserByFullNameUseCase $findUserByFullNameUseCase,
        private readonly FindUserByIdUseCase $findUserByIdUseCase
    ) {}

    /**
     * Retrieve a paginated list of users with optional search filtering.
     * 
     * GET /api/users?page=1&per_page=20&search=term
     * 
     * @param Request $request HTTP request with query parameters
     * @return JsonResponse Paginated list of users in DTO format
     */
    public function index(Request $request): JsonResponse
    {
        $page = (int) $request->get('page', 1);
        $perPage = (int) $request->get('per_page', 20);
        $search = $request->get('search');

        $paginator = $this->getAllUsersUseCase->execute($page, $perPage, $search);
        $data = UserDto::fromEntities($paginator->items());

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
     * Retrieve a single user by their unique identifier.
     * 
     * GET /api/users/{id}
     * 
     * Authorization: Users can only view their own profile or be viewed by admins.
     * 
     * @param int $id The unique user identifier
     * @return JsonResponse User data in DTO format, or error response
     */
    public function show(int $id): JsonResponse
    {
        $authenticatedUser = request()->attributes->get('auth_user');

        if (!$authenticatedUser) {
            return response()->json(['error' => 'User not authenticated'], 401);
        }
        if ($authenticatedUser->getId() !== $id && !$authenticatedUser->isAdmin()) {
            return response()->json(['error' => 'Permission denied to view this user'], 403);
        }

        try {
            $user = $this->findUserByIdUseCase->execute($id);

            if (!$user) {
                return response()->json(['error' => 'User not found'], 404);
            }


            return response()->json([
                'data' => UserDto::fromEntity($user)
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error retrieving user: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create a new user account.
     * 
     * POST /api/users
     * 
     * @param Request $request HTTP request with user creation data
     * @return JsonResponse Created user ID or validation errors
     * @throws ValidationException If request validation fails
     */
    public function store(Request $request): JsonResponse
    {
        $email = $request->input('email');


        $genericEmails = [
            'info@canalwoods.com',
            'noreply@canalwoods.com',
            'admin@canalwoods.com',
        ];
        $isGeneric = $email && in_array(strtolower(trim($email)), $genericEmails);
        $validator = Validator::make($request->all(), [
            'user_name' => [
                'required',
                'string',
                'max:50',
                Rule::unique('vtiger.vtiger_users', 'user_name')->where('deleted', 0)
            ],
            'first_name' => 'required|string|max:30',
            'last_name' => 'required|string|max:30',
            'email' => $isGeneric
                ? ['required', 'email', 'max:100']
                : [
                    'required',
                    'email',
                    'max:100',
                    Rule::unique('vtiger.vtiger_users', 'email1')->where('deleted', 0)
                ],
            'is_admin' => 'required|boolean',
            'role_id'  => 'required|string|exists:vtiger.vtiger_role,roleid', // Jerarquía

            'password' => 'required|string|min:6',
            'phone_crm'  => 'nullable|string|max:50',
            'department' => 'nullable|string|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'messages' => $validator->errors()
            ], 422);
        }

        $authenticatedUser = $request->attributes->get('auth_user');
        if (!$authenticatedUser) {
            return response()->json(['error' => 'User not authenticated'], 401);
        }

        $requestData = $request->all();

        $createRequest = new CreateUserRequest(
            user_name: $requestData['user_name'],
            first_name: $requestData['first_name'],
            last_name: $requestData['last_name'],
            email: $requestData['email'],
            is_admin: $request->boolean('is_admin'),
            role_id: $request->input('role_id'),
            status: $requestData['status'] ?? null,
            password: $requestData['password'],
            phone_crm: $requestData['phone_crm'] ?? null,
            department: $requestData['department'] ?? null,
            reports_to_id: $requestData['reports_to_id'] ?? null,
        );

        $userId = $this->createUserUseCase->execute($createRequest, $authenticatedUser->getId());

        return response()->json([
            'message' => 'User created successfully',
            'user_id' => $userId
        ], 201);
    }

    /**
     * Update an existing user account.
     * 
     * PUT /api/users/{id}
     * 
     * @param Request $request HTTP request with user update data
     * @param string $id User ID from route parameter
     * @return JsonResponse Updated user data or error messages
     * @throws ValidationException If request validation fails
     */
    public function update(Request $request, string $id): JsonResponse
    {
        if (!is_numeric($id) || (int) $id <= 0) {
            return response()->json(['error' => 'Invalid user ID'], 400);
        }

        $userId = (int) $id;
        /*$email = $request->input('email');
        $genericEmails = [
            'info@canalwoods.com',
            'noreply@canalwoods.com',
            'admin@canalwoods.com',
        ];
        $isGeneric = $email && in_array(strtolower(trim($email)), $genericEmails);*/

        $validator = Validator::make($request->all(), [
            'user_name' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('vtiger.vtiger_users', 'user_name')
                    ->ignore($userId, 'id')->where('deleted', 0)
            ],
            'first_name' => 'nullable|string|max:30',
            'last_name'  => 'nullable|string|max:30',
            'email' => ['nullable', 'email', 'max:100'],
            'role_id'  => 'nullable|string|exists:vtiger.vtiger_role,roleid',
            'is_admin' => 'nullable|boolean',
            'status'     => 'nullable|in:Active,Inactive,Pending',
            'phone_crm'  => 'nullable|string|max:50',
            'department' => 'nullable|string|max:50',
            'reports_to_id' => 'nullable|integer',
            'password'   => 'nullable|string|min:6',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'messages' => $validator->errors()
            ], 422);
        }
        $authenticatedUser = $request->attributes->get('auth_user');
        if (!$authenticatedUser) {
            return response()->json(['error' => 'User not authenticated'], 401);
        }

        $requestData = array_filter($request->all(), fn($v) => $v !== null);
        $updateRequest = new UpdateUserRequest(
            user_name: $requestData['user_name'] ?? null,
            first_name: $requestData['first_name'] ?? null,
            last_name: $requestData['last_name'] ?? null,
            email: $requestData['email'] ?? null,
            is_admin: $request->boolean('is_admin'),
            role_id: $request->input('role_id'),
            status: $requestData['status'] ?? null,
            phone_crm: $requestData['phone_crm'] ?? null,
            department: $requestData['department'] ?? null,
            reports_to_id: isset($requestData['reports_to_id']) ? (int) $requestData['reports_to_id'] : null,
            password: $requestData['password'] ?? null, // Solo si se quiere cambiar
        );

        try {

            $updatedUser = $this->updateUserUseCase->execute($userId, $updateRequest, $authenticatedUser->getId());

            return response()->json([
                'message' => 'User updated successfully',
                'data' => $updatedUser->toArray()
            ], 200);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 404);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error updating user: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Update an existing user's profile information.
     * 
     * PUT /api/users/{id}/profile
     * 
     * @param Request $request HTTP request with updated profile data
     * @param int $id The unique user identifier to update
     * @return JsonResponse Success message or error response
     */
    public function updateProfile(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'first_name' => 'required|string|max:50',
            'last_name' => 'required|string|max:30',
            'user_name' => 'required|string|max:30',
            'email' => 'required|email|max:100',
            'role' => 'required|in:Admin,Usuario,Cliente',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'messages' => $validator->errors()
            ], 422);
        }

        $authenticatedUser = $request->attributes->get('auth_user');
        if (!$authenticatedUser) {
            return response()->json(['error' => 'User not authenticated'], 401);
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
            $success = $this->updateUserProfileUseCase->execute($updateRequest, $authenticatedUser->getId());

            if ($success) {
                return response()->json(['message' => 'User profile updated successfully']);
            }

            return response()->json(['error' => 'User not found'], 404);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error updating profile: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Change a user's password.
     * 
     * POST /api/users/{id}/change-password
     * 
     * Authorization: Users can only change their own password, unless caller is admin.
     * 
     * @param Request $request HTTP request with new password
     * @param int $id The unique user identifier whose password to change
     * @return JsonResponse Success message or error response
     */
    public function changePassword(Request $request, int $id): JsonResponse
    {
        if (!is_numeric($id) || (int) $id <= 0) {
            return response()->json(['error' => 'Invalid user ID'], 400);
        }
        $userId = (int) $id;

        $validator = Validator::make($request->all(), [
            'new_password' => [
                'required',
                'string',
                'min:6',
                'regex:/[A-Z]/',
                'regex:/[0-9]/',
            ],
            'current_password' => 'nullable|string', // Opcional: verificar contraseña actual
            'confirm_password' => 'required|string|same:new_password', // Confirmación
        ], [
            'new_password.regex' => 'Password must contain at least one uppercase letter and one number',
            'confirm_password.same' => 'Passwords do not match',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'messages' => $validator->errors()
            ], 422);
        }

        $authenticatedUser = $request->attributes->get('auth_user');
        if (!$authenticatedUser) {
            return response()->json(['error' => 'User not authenticated'], 401);
        }




        $dto = ChangePasswordRequest::fromArray([
            'user_id' => $userId,
            'new_password' => $request->new_password,
            'current_password' => $request->current_password,
        ]);

        try {
            $this->changePasswordUseCase->execute($dto, $authenticatedUser->getId());

            return response()->json(['message' => 'Password updated successfully']);
        } catch (InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        } catch (RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 404);
        }
    }

    /**
     * Soft-delete a user account.
     * 
     * DELETE /api/users/{id}
     * 
     * Authorization: Only administrators can delete users.
     * 
     * @param Request $request HTTP request
     * @param int $id The unique user identifier to delete
     * @return JsonResponse Success message or error response
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        if (!is_numeric($id) || (int) $id <= 0) {
            return response()->json(['error' => 'Invalid user ID'], 400);
        }

        $authenticatedUser = $request->attributes->get('auth_user');
        if (!$authenticatedUser) {
            return response()->json(['error' => 'User not authenticated'], 401);
        }
        $userId = (int) $id;
        try {
            $success = $this->deleteUserUseCase->execute($userId, $authenticatedUser->getId());
            if (!$success) {
                return response()->json(['error' => 'User not found or already deleted'], 404);
            }   

            return response()->json(['message' => 'User deleted successfully']);
        } catch (InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 403); // Forbidden
        } catch (RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 404); // Not found
        }
    }

    /**
     * Retrieve the authenticated user's own profile.
     * 
     * GET /api/users/me
     * 
     * @param Request $request HTTP request with authentication context
     * @return JsonResponse Current user's profile data in DTO format
     */
    public function getMyProfile(Request $request): JsonResponse
    {
        $authenticatedUser = $request->attributes->get('auth_user');
        if (!$authenticatedUser) {
            return response()->json(['error' => 'User not authenticated'], 401);
        }

        $userEntity = $this->getMyProfileUseCase->execute($authenticatedUser->getId());

        if (!$userEntity) {
            return response()->json(['error' => 'User not found'], 404);
        }


        return response()->json([
            'data' => UserDto::fromEntity($userEntity)
        ]);
    }

    /**
     * Update the authenticated user's own profile.
     * 
     * PUT /api/users/me
     * 
     * @param Request $request HTTP request with updated profile data
     * @return JsonResponse Success message or error response
     */
    public function updateMyProfile(Request $request): JsonResponse
    {
        $authenticatedUser = $request->attributes->get('auth_user');
        if (!$authenticatedUser) {
            return response()->json(['error' => 'User not authenticated'], 401);
        }

        $validator = Validator::make($request->all(), [
            'first_name' => 'required|string|max:50',
            'last_name' => 'required|string|max:50',
            'user_name' => 'required|string|max:50',
            'email' => 'required|email|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'messages' => $validator->errors()
            ], 422);
        }

        $requestData = $request->all();

        $updateRequest = new UpdateUserProfileRequest(
            id: $authenticatedUser->getId(),  // ✅ Use getter
            first_name: $requestData['first_name'],
            last_name: $requestData['last_name'],
            user_name: $requestData['user_name'],
            email: $requestData['email'],
            role: $authenticatedUser->getRole(),  // ✅ Use getter
            department: $requestData['department'] ?? null,
            phone_crm: $requestData['phone_crm'] ?? null,
            reports_to_id: null,
        );

        try {
            $success = $this->updateUserProfileUseCase->execute($updateRequest, $authenticatedUser->getId());

            if ($success) {
                return response()->json(['message' => 'Profile updated successfully']);
            }

            return response()->json(['error' => 'Error updating profile'], 500);
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Search users by name or username for autocomplete functionality.
     * 
     * GET /api/users/search?q=term
     * 
     * @param Request $request HTTP request with search query parameter
     * @return JsonResponse Array of matching users for autocomplete
     */
    public function searchUsers(Request $request): JsonResponse
    {
        $searchTerm = trim($request->get('q', ''));

        if (strlen($searchTerm) < 2) {
            return response()->json(['data' => []]);
        }

        try {
            $users = $this->findUsersByNameOrUsernameUseCase->execute($searchTerm);
            return response()->json(['data' => $users]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error searching users'
            ], 500);
        }
    }

    /**
     * Find a user by their full name (first + last name).
     * 
     * GET /api/users/find-by-fullname?full_name=John%20Doe
     * 
     * @param Request $request HTTP request with full_name query parameter
     * @return JsonResponse User data if found, or error response
     */
    public function findUserByFullName(Request $request): JsonResponse
    {
        $fullName = trim($request->get('full_name', ''));

        if (!$fullName) {
            return response()->json(['error' => 'Full name is required'], 422);
        }

        try {
            $user = $this->findUserByFullNameUseCase->execute($fullName);

            if (!$user) {
                return response()->json(['error' => 'User not found'], 404);
            }

            return response()->json(['data' => $user]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error searching user'
            ], 500);
        }
    }
}
