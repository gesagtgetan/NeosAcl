<?php

declare(strict_types=1);

namespace Sandstorm\NeosAcl\Tests\Unit\Policy;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sandstorm\NeosAcl\Policy\DynamicRolePolicyEntry;

final class DynamicRolePolicyEntryTest extends TestCase
{
    /**
     * @return iterable<string, array{int|string|bool, bool}>
     */
    public static function abstractColumnValues(): iterable
    {
        yield 'mysql int 1' => [1, true];
        yield 'mysql int 0' => [0, false];
        yield 'string 1' => ['1', true];
        yield 'string 0' => ['0', false];
        yield 'bool' => [true, true];
    }

    #[DataProvider('abstractColumnValues')]
    public function testFromRowReadsAllColumns(int|string|bool $abstract, bool $expectedAbstract): void
    {
        $entry = DynamicRolePolicyEntry::fromRow([
            'name' => 'Marketing',
            'subtreetag' => 'neosacl-marketing',
            'abstract' => $abstract,
            'parentrolenames' => '["Neos.Neos:RestrictedEditor"]',
        ]);

        self::assertSame('Marketing', $entry->name);
        self::assertSame('neosacl-marketing', $entry->subtreeTag);
        self::assertSame($expectedAbstract, $entry->abstract);
        self::assertSame(['Neos.Neos:RestrictedEditor'], $entry->parentRoleNames);
    }

    public function testFromRowRejectsMissingColumns(): void
    {
        $this->expectException(\RuntimeException::class);

        DynamicRolePolicyEntry::fromRow(['name' => 'Marketing']);
    }

    public function testFromRowRejectsNonStringParentRoles(): void
    {
        $this->expectException(\RuntimeException::class);

        DynamicRolePolicyEntry::fromRow([
            'name' => 'Marketing',
            'subtreetag' => 'neosacl-marketing',
            'abstract' => 0,
            'parentrolenames' => '[1, 2]',
        ]);
    }
}
