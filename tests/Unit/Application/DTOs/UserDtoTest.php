<?php

namespace Tests\Unit\Application\DTOs;

use App\Application\DTOs\User\UserDto;
use App\Domain\Entities\User;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the UserDto data transfer object.
 * 
 * Tests focus on:
 * - Mapping domain entities to API-friendly arrays (fromEntity)
 * - Batch conversion of entity collections (fromEntities)
 * - Mapping arrays back to domain entities (toEntity)
 * - Handling of null values and computed fields
 * - Round-trip consistency (entity → array → entity)
 * 
 * @package Tests\Unit\Application\DTOs
 * @covers \App\Application\DTOs\UserDto
 */
class UserDtoTest extends TestCase
{
    // ✅ Valid entity data for testing
    private const VALID_ENTITY_DATA = [
        'id' => 42,
        'userName' => 'johndoe',
        'firstName' => 'John',
        'lastName' => 'Doe',
        'email' => 'john.doe@example.com',
        'role' => 'Admin',
        'status' => 'Active',
        'phoneCrm' => '+507 6123-4567',
        'department' => 'Engineering',
        'reportsToId' => 10,
        'isActive' => true,
    ];

    // ========================================================================
// HELPER METHODS
// ========================================================================

    /**
     * Helper to create valid snake_case data for toEntity() tests
     */
    private function snakeCaseUserData(array $overrides = []): array
    {
        $defaults = [
            'id' => 1,
            'user_name' => 'testuser',
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'test@example.com',
            'role' => 'Usuario',
            'status' => 'Active',
            'phone_crm' => null,
            'department' => null,
            'reports_to_id' => null,
            'is_active' => true,
        ];

        return array_merge($defaults, $overrides);
    }


    // ✅ Helper to create a valid User entity
    private function createValidUser(array $overrides = []): User
    {
        $data = array_merge(self::VALID_ENTITY_DATA, $overrides);

        return new User(
            id: $data['id'],
            userName: $data['userName'],
            firstName: $data['firstName'],
            lastName: $data['lastName'],
            email: $data['email'],
            role: $data['role'],
            status: $data['status'],
            phoneCrm: $data['phoneCrm'],
            department: $data['department'],
            reportsToId: $data['reportsToId'],
            isActive: $data['isActive'],
        );
    }

    // ========================================================================
    // FROM ENTITY TESTS
    // ========================================================================

    #[\PHPUnit\Framework\Attributes\Test]
    public function from_entity_converts_user_to_snake_case_array(): void
    {
        $user = $this->createValidUser();
        $array = UserDto::fromEntity($user);

        // ✅ Verify snake_case keys exist
        $this->assertArrayHasKey('user_name', $array);
        $this->assertArrayHasKey('first_name', $array);
        $this->assertArrayHasKey('last_name', $array);
        $this->assertArrayHasKey('phone_crm', $array);
        $this->assertArrayHasKey('reports_to_id', $array);
        $this->assertArrayHasKey('is_active', $array);

        // ✅ Verify values are correctly mapped
        $this->assertSame(42, $array['id']);
        $this->assertSame('johndoe', $array['user_name']);
        $this->assertSame('John', $array['first_name']);
        $this->assertSame('Doe', $array['last_name']);
        $this->assertSame('john.doe@example.com', $array['email']);
        $this->assertSame('Admin', $array['role']);
        $this->assertSame('Active', $array['status']);

        // ✅ CORREGIDO: El DTO refleja el valor sanitizado de la entidad
        $this->assertSame('+5076123-4567', $array['phone_crm']);

        $this->assertSame('Engineering', $array['department']);
        $this->assertSame(10, $array['reports_to_id']);
        $this->assertTrue($array['is_active']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function from_entity_includes_computed_full_name_field(): void
    {
        $user = $this->createValidUser();
        $array = UserDto::fromEntity($user);

        $this->assertArrayHasKey('full_name', $array);
        $this->assertSame('John Doe', $array['full_name']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function from_entity_handles_null_optional_fields(): void
    {
        $user = $this->createValidUser([
            'phoneCrm' => null,
            'department' => null,
            'reportsToId' => null,
        ]);
        $array = UserDto::fromEntity($user);

        $this->assertNull($array['phone_crm']);
        $this->assertNull($array['department']);
        $this->assertNull($array['reports_to_id']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function from_entity_preserves_boolean_is_active_value(): void
    {
        // Active user
        $activeUser = $this->createValidUser(['isActive' => true]);
        $activeArray = UserDto::fromEntity($activeUser);
        $this->assertTrue($activeArray['is_active']);

        // Inactive user
        $inactiveUser = $this->createValidUser(['isActive' => false, 'status' => 'Inactive']);
        $inactiveArray = UserDto::fromEntity($inactiveUser);
        $this->assertFalse($inactiveArray['is_active']);
    }

    // ========================================================================
    // FROM ENTITIES TESTS (BATCH CONVERSION)
    // ========================================================================

    #[\PHPUnit\Framework\Attributes\Test]
    public function from_entities_converts_collection_to_array_of_arrays(): void
    {
        $users = [
            $this->createValidUser(['id' => 1, 'userName' => 'user1']),
            $this->createValidUser(['id' => 2, 'userName' => 'user2']),
            $this->createValidUser(['id' => 3, 'userName' => 'user3']),
        ];

        $arrays = UserDto::fromEntities($users);

        $this->assertIsArray($arrays);
        $this->assertCount(3, $arrays);
        $this->assertIsArray($arrays[0]);
        $this->assertSame('user1', $arrays[0]['user_name']);
        $this->assertSame('user2', $arrays[1]['user_name']);
        $this->assertSame('user3', $arrays[2]['user_name']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function from_entities_returns_empty_array_for_empty_input(): void
    {
        $result = UserDto::fromEntities([]);
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    // ========================================================================
    // TO ENTITY TESTS (REVERSE MAPPING)
    // ========================================================================

    #[\PHPUnit\Framework\Attributes\Test]
    public function to_entity_creates_user_from_snake_case_array(): void
    {
        $snakeCaseData = [
            'id' => 99,
            'user_name' => 'snake_user',
            'first_name' => 'Snake',
            'last_name' => 'User',
            'email' => 'snake@example.com',
            'role' => 'Usuario',
            'status' => 'Active',
            'phone_crm' => '+507 9876-5432',
            'department' => 'Support',
            'reports_to_id' => 5,
            'is_active' => true,
        ];

        $user = UserDto::toEntity($snakeCaseData);

        $this->assertSame(99, $user->getId());
        $this->assertSame('snake_user', $user->getUserName());
        $this->assertSame('Snake', $user->getFirstName());
        $this->assertSame('User', $user->getLastName());
        $this->assertSame('snake@example.com', $user->getEmail());
        $this->assertSame('Usuario', $user->getRole());
        $this->assertSame('Active', $user->getStatus());


        $this->assertSame('+5079876-5432', $user->getPhoneCrm());

        $this->assertSame('Support', $user->getDepartment());
        $this->assertSame(5, $user->getReportsToId());
        $this->assertTrue($user->isActive());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function to_entity_handles_missing_optional_fields_with_defaults(): void
    {
        $minimalData = [
            'id' => 1,
            'user_name' => 'minimal',
            'first_name' => 'Min',
            'last_name' => 'imal',
            'email' => 'min@example.com',
            'role' => 'Usuario',
            'status' => 'Active',
            // phone_crm, department, reports_to_id omitted
        ];

        $user = UserDto::toEntity($minimalData);

        $this->assertNull($user->getPhoneCrm());
        $this->assertNull($user->getDepartment());
        $this->assertNull($user->getReportsToId());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function to_entity_defaults_role_to_usuario_if_missing(): void
    {
        // ✅ toEntity() espera snake_case keys
        $snakeCaseData = [
            'id' => 1,
            'user_name' => 'testuser',
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'test@example.com',
            // 'role' omitted intentionally to test default → should be 'Usuario'
            'status' => 'Active',
            'phone_crm' => null,
            'department' => null,
            'reports_to_id' => null,
            'is_active' => true,
        ];

        $user = UserDto::toEntity($snakeCaseData);

        $this->assertSame('Usuario', $user->getRole());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function to_entity_defaults_status_to_active_if_missing(): void
    {

        $snakeCaseData = [
            'id' => 1,
            'user_name' => 'testuser',
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'test@example.com',
            'role' => 'Usuario',
            // 'status' omitted intentionally to test default
            'phone_crm' => null,
            'department' => null,
            'reports_to_id' => null,
            'is_active' => true,
        ];

        $user = UserDto::toEntity($snakeCaseData);


        $this->assertSame('Active', $user->getStatus());
        $this->assertTrue($user->isActive());
    }

  

    #[\PHPUnit\Framework\Attributes\Test]
    public function to_entity_casts_id_and_reports_to_id_to_integers(): void
    {
        $data = [
            'id' => '42', // String instead of int
            'user_name' => 'test',
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'test@example.com',
            'role' => 'Usuario',
            'status' => 'Active',
            'reports_to_id' => '10', // String instead of int
        ];

        $user = UserDto::toEntity($data);

        $this->assertSame(42, $user->getId());
        $this->assertSame(10, $user->getReportsToId());
    }

    // ========================================================================
    // ROUND-TRIP CONSISTENCY TESTS
    // ========================================================================

    #[\PHPUnit\Framework\Attributes\Test]
    public function entity_to_array_to_entity_preserves_core_properties(): void
    {
        $originalUser = $this->createValidUser();

        // Entity → Array
        $array = UserDto::fromEntity($originalUser);

        // Array → Entity (using toEntity, which expects snake_case)
        $restoredUser = UserDto::toEntity($array);

        // Compare core properties (some may have defaults applied)
        $this->assertSame($originalUser->getId(), $restoredUser->getId());
        $this->assertSame($originalUser->getUserName(), $restoredUser->getUserName());
        $this->assertSame($originalUser->getEmail(), $restoredUser->getEmail());
        $this->assertSame($originalUser->getRole(), $restoredUser->getRole());
        $this->assertSame($originalUser->isActive(), $restoredUser->isActive());
        $this->assertSame($originalUser->getFullName(), $restoredUser->getFullName());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function array_to_entity_to_array_preserves_snake_case_structure(): void
    {
        $originalArray = [
            'id' => 100,
            'user_name' => 'roundtrip',
            'first_name' => 'Round',
            'last_name' => 'Trip',
            'email' => 'round@trip.com',
            'role' => 'Admin',
            'status' => 'Pending',
            'phone_crm' => '+507 1111-2222',
            'department' => 'QA',
            'reports_to_id' => 20,
            'is_active' => false,
        ];

        // Array → Entity
        $user = UserDto::toEntity($originalArray);

        // Entity → Array
        $restoredArray = UserDto::fromEntity($user);

        // Compare snake_case structure
        $this->assertSame($originalArray['user_name'], $restoredArray['user_name']);
        $this->assertSame($originalArray['email'], $restoredArray['email']);
        $this->assertSame($originalArray['role'], $restoredArray['role']);
        $this->assertSame($originalArray['is_active'], $restoredArray['is_active']);

        // full_name is computed, so it should exist in restored but not original
        $this->assertArrayHasKey('full_name', $restoredArray);
        $this->assertSame('Round Trip', $restoredArray['full_name']);
    }

    // ========================================================================
    // EDGE CASES & ERROR HANDLING
    // ========================================================================

    #[\PHPUnit\Framework\Attributes\Test]
    public function to_entity_throws_exception_for_missing_required_fields(): void
    {
        $this->expectException(InvalidArgumentException::class);


        $incompleteData = [
            'id' => 1,
            // Missing: user_name, first_name, last_name, email, role, status
        ];

        UserDto::toEntity($incompleteData);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    #[\PHPUnit\Framework\Attributes\DataProvider('invalidEmailProvider')]
    public function to_entity_throws_exception_for_invalid_email(string $invalidEmail): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid email format');

        // ✅ Usar helper con snake_case, NO self::VALID_ENTITY_DATA
        $data = $this->snakeCaseUserData(['email' => $invalidEmail]);

        UserDto::toEntity($data);
    }

    public static function invalidEmailProvider(): array
    {
        return [
            'no_at' => ['invalidemail.com'],
            'no_domain' => ['user@'],
            'no_local' => ['@domain.com'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\Test]
    #[\PHPUnit\Framework\Attributes\DataProvider('invalidRoleProvider')]
    public function to_entity_throws_exception_for_invalid_role(string $invalidRole): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Invalid role '{$invalidRole}'");

        // ✅ Usar helper con snake_case, NO self::VALID_ENTITY_DATA
        $data = $this->snakeCaseUserData(['role' => $invalidRole]);

        UserDto::toEntity($data);
    }

    public static function invalidRoleProvider(): array
    {
        return [
            'empty' => [''],
            'invalid' => ['SuperAdmin'],
            'lowercase' => ['admin'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function from_entity_does_not_modify_original_entity(): void
    {
        $user = $this->createValidUser();
        $originalEmail = $user->getEmail();

        $array = UserDto::fromEntity($user);

        // Entity should remain unchanged
        $this->assertSame($originalEmail, $user->getEmail());
        $this->assertSame('John Doe', $user->getFullName());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function from_entities_does_not_modify_original_collection(): void
    {
        $users = [
            $this->createValidUser(['id' => 1]),
            $this->createValidUser(['id' => 2]),
        ];
        $originalEmails = array_map(fn($u) => $u->getEmail(), $users);

        $arrays = UserDto::fromEntities($users);

        // Original entities should remain unchanged
        foreach ($users as $index => $user) {
            $this->assertSame($originalEmails[$index], $user->getEmail());
        }
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function to_entity_combines_is_active_and_status_with_and_logic(): void
    {

        $data1 = [
            'id' => 1,
            'user_name' => 'test',
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'test@example.com',
            'role' => 'Usuario',
            'status' => 'Active',
            'is_active' => true,
        ];
        $user1 = UserDto::toEntity($data1);
        $this->assertTrue($user1->isActive());


        $data2 = [
            'id' => 2,
            'user_name' => 'test2',
            'first_name' => 'Test',
            'last_name' => 'User2',
            'email' => 'test2@example.com',
            'role' => 'Usuario',
            'status' => 'Inactive',
            'is_active' => true,
        ];
        $user2 = UserDto::toEntity($data2);
        $this->assertFalse($user2->isActive());


        $data3 = [
            'id' => 3,
            'user_name' => 'test3',
            'first_name' => 'Test',
            'last_name' => 'User3',
            'email' => 'test3@example.com',
            'role' => 'Usuario',
            'status' => 'Active',
            'is_active' => false,
        ];
        $user3 = UserDto::toEntity($data3);
        $this->assertFalse($user3->isActive());


        $data4 = [
            'id' => 4,
            'user_name' => 'test4',
            'first_name' => 'Test',
            'last_name' => 'User4',
            'email' => 'test4@example.com',
            'role' => 'Usuario',
            'status' => 'Inactive',
            'is_active' => false,
        ];
        $user4 = UserDto::toEntity($data4);
        $this->assertFalse($user4->isActive());


        $data5 = [
            'id' => 5,
            'user_name' => 'test5',
            'first_name' => 'Test',
            'last_name' => 'User5',
            'email' => 'test5@example.com',
            'role' => 'Usuario',
            'status' => 'Active',
            // is_active omitted
        ];
        $user5 = UserDto::toEntity($data5);
        $this->assertTrue($user5->isActive());
    }
}
