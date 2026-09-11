<?php

declare(strict_types=1);

namespace Sandstorm\NeosAcl\Tests\Unit\Domain\Model;

use Neos\ContentRepository\Core\DimensionSpace\DimensionSpacePointSet;
use Neos\ContentRepository\Core\SharedModel\ContentRepository\ContentRepositoryId;
use Neos\ContentRepository\Core\SharedModel\Node\NodeAggregateIds;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sandstorm\NeosAcl\Domain\Model\DynamicRole;
use Sandstorm\NeosAcl\Domain\Model\InvalidDynamicRoleException;
use Sandstorm\NeosAcl\Domain\Model\MatcherConfiguration;

final class DynamicRoleTest extends TestCase
{
    public function testDerivesRoleIdentifierAndSubtreeTagFromTheName(): void
    {
        $dynamicRole = new DynamicRole('MarketingEditors', false, ['Neos.Neos:RestrictedEditor'], self::emptyMatcher());

        self::assertSame('MarketingEditors', $dynamicRole->getName());
        self::assertSame('Dynamic:MarketingEditors', $dynamicRole->getRoleIdentifier());
        self::assertSame('neosacl-marketingeditors', $dynamicRole->getSubtreeTag()->value);
        self::assertFalse($dynamicRole->isAbstract());
        self::assertSame(['Neos.Neos:RestrictedEditor'], $dynamicRole->getParentRoleNames());
    }

    public function testAcceptsTheLongestAllowedName(): void
    {
        $name = str_repeat('a', 28);
        $dynamicRole = new DynamicRole($name, true, [], self::emptyMatcher());

        self::assertSame('neosacl-' . $name, $dynamicRole->getSubtreeTag()->value);
        self::assertTrue($dynamicRole->isAbstract());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidNames(): iterable
    {
        yield 'empty' => [''];
        yield 'too long' => [str_repeat('a', 29)];
        yield 'with colon' => ['Neos:Editor'];
        yield 'with space' => ['Marketing Editors'];
        yield 'with dash' => ['marketing-editors'];
    }

    #[DataProvider('invalidNames')]
    public function testRejectsInvalidNames(string $name): void
    {
        $this->expectException(InvalidDynamicRoleException::class);

        new DynamicRole($name, false, [], self::emptyMatcher());
    }

    public function testUpdateKeepsNameAndTag(): void
    {
        $dynamicRole = new DynamicRole('Marketing', false, [], self::emptyMatcher());
        $matcher = MatcherConfiguration::fromArray(['selectedNodes' => ['node-a']]);

        $dynamicRole->update(true, ['Neos.Neos:LivePublisher'], $matcher);

        self::assertSame('neosacl-marketing', $dynamicRole->getSubtreeTag()->value);
        self::assertTrue($dynamicRole->isAbstract());
        self::assertSame(['Neos.Neos:LivePublisher'], $dynamicRole->getParentRoleNames());
        self::assertSame(['node-a'], $dynamicRole->getMatcherConfiguration()->selectedNodeAggregateIds->toStringArray());
    }

    private static function emptyMatcher(): MatcherConfiguration
    {
        return MatcherConfiguration::create(ContentRepositoryId::fromString('default'), [], DimensionSpacePointSet::fromArray([]), NodeAggregateIds::createEmpty());
    }
}
