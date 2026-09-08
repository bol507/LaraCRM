<?php

namespace Tests\Unit\Application\ValueObjects\Comment;

use App\Application\ValueObjects\Comment\CommentModule;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the CommentModule value object.
 *
 * @package Tests\Unit\Application\ValueObjects\Comment
 */
#[PHPUnit\Framework\Attributes\CoversClass(CommentModule::class)]
class CommentModuleTest extends TestCase
{
    #[\PHPUnit\Framework\Attributes\Test]
    public function supports_all_crm_modules(): void
    {
        $this->assertTrue(CommentModule::isSupported('Project'));
        $this->assertTrue(CommentModule::isSupported('Quotes'));
        $this->assertTrue(CommentModule::isSupported('Calendar'));
        $this->assertTrue(CommentModule::isSupported('Accounts'));
        $this->assertTrue(CommentModule::isSupported('Contacts'));
        $this->assertTrue(CommentModule::isSupported('Potentials'));
        $this->assertTrue(CommentModule::isSupported('HelpDesk'));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function rejects_unsupported_modules(): void
    {
        $this->assertFalse(CommentModule::isSupported('Leads'));
        $this->assertFalse(CommentModule::isSupported('Invoice'));
        $this->assertFalse(CommentModule::isSupported(''));
        $this->assertFalse(CommentModule::isSupported('Client'));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function resolves_setype_for_each_supported_module(): void
    {
        $this->assertSame('Project', CommentModule::toSetype('Project'));
        $this->assertSame('Quotes', CommentModule::toSetype('Quotes'));
        $this->assertSame('Calendar', CommentModule::toSetype('Calendar'));
        $this->assertSame('Accounts', CommentModule::toSetype('Accounts'));
        $this->assertSame('Contacts', CommentModule::toSetype('Contacts'));
        $this->assertSame('Potentials', CommentModule::toSetype('Potentials'));
        $this->assertSame('HelpDesk', CommentModule::toSetype('HelpDesk'));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function to_setype_throws_for_unsupported_module(): void
    {
        $this->expectException(InvalidArgumentException::class);
        CommentModule::toSetype('Leads');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function all_returns_the_full_supported_list(): void
    {
        $expected = [
            'Project',
            'Quotes',
            'Calendar',
            'Accounts',
            'Contacts',
            'Potentials',
            'HelpDesk',
        ];

        $this->assertSame($expected, CommentModule::all());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function module_names_match_vtiger_setypes(): void
    {
        foreach (CommentModule::all() as $module) {
            $this->assertSame($module, CommentModule::toSetype($module));
        }
    }
}