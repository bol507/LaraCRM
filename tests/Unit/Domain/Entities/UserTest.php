<?php

namespace Tests\Unit\Domain\Entities;

use App\Domain\Entities\User;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class UserTest extends TestCase
{
    // ✅ Helper method para crear usuario con datos por defecto
    private function createValidUser(array $overrides = []): User
    {
        $defaults = [
            'id' => 1,
            'userName' => 'testuser',
            'firstName' => 'John',
            'lastName' => 'Doe',
            'email' => 'john.doe@example.com',
            'role' => 'Usuario',
            'status' => 'Active',
            'phoneCrm' => '+507 6123-4567',
            'department' => 'Development',
            'reportsToId' => 5,
            'isActive' => true,
        ];

        $data = array_merge($defaults, $overrides);

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
    // CONSTRUCTOR & VALIDATION TESTS
    // ========================================================================

    #[\PHPUnit\Framework\Attributes\Test]
    public function constructor_creates_valid_user_with_all_properties(): void
    {
        $user = $this->createValidUser();

        $this->assertSame(1, $user->getId());
        $this->assertSame('testuser', $user->getUserName());
        $this->assertSame('John', $user->getFirstName());
        $this->assertSame('Doe', $user->getLastName());
        $this->assertSame('john.doe@example.com', $user->getEmail());
        $this->assertSame('Usuario', $user->getRole());
        $this->assertSame('Active', $user->getStatus());
        $this->assertSame('+5076123-4567', $user->getPhoneCrm());
        $this->assertSame('Development', $user->getDepartment());
        $this->assertSame(5, $user->getReportsToId());
        $this->assertTrue($user->isActive());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function constructor_accepts_null_for_optional_fields(): void
    {
        $user = new User(
            id: 1,
            userName: 'testuser',
            firstName: 'John',
            lastName: 'Doe',
            email: 'john@example.com',
            role: 'Usuario',
            status: 'Active',
            phoneCrm: null,
            department: null,
            reportsToId: null,
            isActive: true
        );

        $this->assertNull($user->getPhoneCrm());
        $this->assertNull($user->getDepartment());
        $this->assertNull($user->getReportsToId());
    }

    // ========================================================================
    // VALIDATION: USERNAME TESTS (CORREGIDOS)
    // ========================================================================

    #[\PHPUnit\Framework\Attributes\Test]
    #[\PHPUnit\Framework\Attributes\DataProvider('invalidUserNameProvider')]
    public function constructor_throws_exception_for_invalid_username(string $invalidUserName, string $expectedMessagePattern): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches($expectedMessagePattern);

        $this->createValidUser(['userName' => $invalidUserName]);
    }

    public static function invalidUserNameProvider(): array
    {
        return [
            'empty' => ['', '/Username cannot be empty/'],
            'whitespace' => ['   ', '/Username cannot be empty/'],
            'too_short' => ['ab', '/Username must be between/'],
            'too_long' => [str_repeat('a', 51), '/Username must be between/'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function constructor_accepts_valid_username_boundaries(): void
    {
        // Minimum length (3 chars)
        $user1 = $this->createValidUser(['userName' => 'abc']);
        $this->assertSame('abc', $user1->getUserName());

        // Maximum length (50 chars)
        $maxUserName = str_repeat('a', 50);
        $user2 = $this->createValidUser(['userName' => $maxUserName]);
        $this->assertSame($maxUserName, $user2->getUserName());
    }

    // ========================================================================
    // VALIDATION: EMAIL TESTS (CORREGIDOS)
    // ========================================================================

    #[\PHPUnit\Framework\Attributes\Test]
    #[\PHPUnit\Framework\Attributes\DataProvider('invalidEmailProvider')]
    public function constructor_throws_exception_for_invalid_email(string $invalidEmail): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid email format');

        $this->createValidUser(['email' => $invalidEmail]);
    }

    public static function invalidEmailProvider(): array
    {
        return [
            'no_at' => ['invalidemail.com'],
            'no_domain' => ['user@'],
            'no_local' => ['@domain.com'],
            'multiple_at' => ['user@@domain.com'],
        ];
    }

    // ========================================================================
    // VALIDATION: ROLE TESTS (CORREGIDOS)
    // ========================================================================

    #[\PHPUnit\Framework\Attributes\Test]
    #[\PHPUnit\Framework\Attributes\DataProvider('invalidRoleProvider')]
    public function constructor_throws_exception_for_invalid_role(string $invalidRole): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Invalid role '{$invalidRole}'");

        $this->createValidUser(['role' => $invalidRole]);
    }

    public static function invalidRoleProvider(): array
    {
        return [
            'empty' => [''],
            'invalid' => ['SuperAdmin'],
            'lowercase' => ['admin'],
        ];
    }

    // ========================================================================
    // VALIDATION: STATUS TESTS (CORREGIDOS)
    // ========================================================================

    #[\PHPUnit\Framework\Attributes\Test]
    #[\PHPUnit\Framework\Attributes\DataProvider('invalidStatusProvider')]
    public function constructor_throws_exception_for_invalid_status(string $invalidStatus): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Invalid status '{$invalidStatus}'");

        $this->createValidUser(['status' => $invalidStatus]);
    }

    public static function invalidStatusProvider(): array
    {
        return [
            'empty' => [''],
            'invalid' => ['Deleted'],
            'lowercase' => ['active'],
        ];
    }

    // ========================================================================
    // BUSINESS METHOD TESTS
    // ========================================================================

    #[\PHPUnit\Framework\Attributes\Test]
    public function activate_changes_status_to_active(): void
    {
        $user = $this->createValidUser([
            'status' => 'Inactive',
            'isActive' => false,
        ]);

        $user->activate();

        $this->assertTrue($user->isActive());
        $this->assertSame('Active', $user->getStatus());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function activate_throws_exception_if_already_active(): void
    {
        $user = $this->createValidUser(); // Already active by default

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('User is already active');

        $user->activate();
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function deactivate_changes_status_to_inactive(): void
    {
        $user = $this->createValidUser(['role' => 'Usuario']);

        $user->deactivate();

        $this->assertFalse($user->isActive());
        $this->assertSame('Inactive', $user->getStatus());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function deactivate_throws_exception_for_admin_users(): void
    {
        $user = $this->createValidUser(['role' => 'Admin']);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Cannot deactivate admin user');

        $user->deactivate();
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function is_admin_returns_true_only_for_admin_role(): void
    {
        $admin = $this->createValidUser(['role' => 'Admin']);
        $usuario = $this->createValidUser(['role' => 'Usuario']);

        $this->assertTrue($admin->isAdmin());
        $this->assertFalse($usuario->isAdmin());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function can_view_sensitive_data_requires_admin_and_active(): void
    {
        // Active admin → can view
        $user1 = $this->createValidUser(['role' => 'Admin', 'status' => 'Active', 'isActive' => true]);
        $this->assertTrue($user1->canViewSensitiveData());

        // Inactive admin → cannot view
        $user2 = $this->createValidUser(['role' => 'Admin', 'status' => 'Inactive', 'isActive' => false]);
        $this->assertFalse($user2->canViewSensitiveData());

        // Active non-admin → cannot view
        $user3 = $this->createValidUser(['role' => 'Usuario', 'status' => 'Active', 'isActive' => true]);
        $this->assertFalse($user3->canViewSensitiveData());
    }

    // ========================================================================
    // MUTATOR TESTS
    // ========================================================================

    #[\PHPUnit\Framework\Attributes\Test]
    public function change_email_updates_after_validation(): void
    {
        $user = $this->createValidUser();
        $newEmail = 'new.email@example.com';

        $user->changeEmail($newEmail);

        $this->assertSame($newEmail, $user->getEmail());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function change_email_throws_exception_for_invalid_email(): void
    {
        $user = $this->createValidUser();

        $this->expectException(InvalidArgumentException::class);
        $user->changeEmail('invalid-email');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function change_phone_sanitizes_input(): void
    {
        $user = $this->createValidUser();

        $user->changePhone('+507 (6123) 4567');

        $this->assertSame('+50761234567', $user->getPhoneCrm());
    }

    // ========================================================================
    // FACTORY METHOD TESTS
    // ========================================================================

    #[\PHPUnit\Framework\Attributes\Test]
    public function from_array_creates_user_with_snake_case_keys(): void
    {
        $snakeCaseData = [
            'id' => 42,
            'user_name' => 'snake_user',
            'first_name' => 'Snake',
            'last_name' => 'User',
            'email' => 'snake@example.com',
            'role' => 'Admin',
            'status' => 'Active',
            'phone_crm' => '+507 1234-5678',
            'department' => 'QA',
            'reports_to_id' => 10,
            'is_active' => true,
        ];

        $user = User::fromArray($snakeCaseData);

        $this->assertSame(42, $user->getId());
        $this->assertSame('snake_user', $user->getUserName());
        $this->assertSame('Snake User', $user->getFullName());
        $this->assertTrue($user->isAdmin());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function to_array_returns_snake_case_for_persistence(): void
    {
        $user = $this->createValidUser();
        $array = $user->toArray();

        $this->assertArrayHasKey('user_name', $array);
        $this->assertArrayHasKey('email', $array);
        $this->assertArrayHasKey('is_active', $array);
        $this->assertSame('testuser', $array['user_name']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function sanitize_phone_preserves_plus_and_hyphen(): void
    {
        $user = $this->createValidUser(['phoneCrm' => '+507 6123-4567']);
        $this->assertSame('+5076123-4567', $user->getPhoneCrm());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function sanitize_phone_removes_parentheses_and_spaces(): void
    {
        $user = $this->createValidUser(['phoneCrm' => '(507) 123-4567']);
        $this->assertSame('507123-4567', $user->getPhoneCrm());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function sanitize_phone_removes_dots_and_other_chars(): void
    {
        $user = $this->createValidUser(['phoneCrm' => '+1.555.123.4567']);
        $this->assertSame('+15551234567', $user->getPhoneCrm());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function sanitize_phone_accepts_null(): void
    {
        $user = $this->createValidUser(['phoneCrm' => null]);
        $this->assertNull($user->getPhoneCrm());
    }
}
