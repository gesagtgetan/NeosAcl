<?php

declare(strict_types=1);

namespace Sandstorm\NeosAcl\Tests\Unit\Domain\Model;

use Neos\ContentRepository\Core\DimensionSpace\DimensionSpacePoint;
use Neos\ContentRepository\Core\DimensionSpace\DimensionSpacePointSet;
use Neos\ContentRepository\Core\SharedModel\ContentRepository\ContentRepositoryId;
use Neos\ContentRepository\Core\SharedModel\Node\NodeAggregateId;
use PHPUnit\Framework\TestCase;
use Sandstorm\NeosAcl\Domain\Model\InvalidDynamicRoleException;
use Sandstorm\NeosAcl\Domain\Model\MatcherConfiguration;

final class MatcherConfigurationTest extends TestCase
{
    public function testFromArrayReadsTheCurrentShape(): void
    {
        $matcher = MatcherConfiguration::fromArray([
            'contentRepositoryId' => 'second',
            'selectedWorkspaces' => ['marketing', 'sales'],
            'selectedDimensionSpacePoints' => [['language' => 'de'], ['language' => 'en']],
            'selectedNodes' => ['node-a', 'node-b'],
        ]);

        self::assertSame('second', $matcher->contentRepositoryId->value);
        self::assertSame(['marketing', 'sales'], $matcher->selectedWorkspaceNameStrings());
        self::assertTrue($matcher->selectedDimensionSpacePoints->contains(DimensionSpacePoint::fromArray(['language' => 'de'])));
        self::assertTrue($matcher->selectedDimensionSpacePoints->contains(DimensionSpacePoint::fromArray(['language' => 'en'])));
        self::assertCount(2, $matcher->selectedDimensionSpacePoints);
        self::assertSame(['node-a', 'node-b'], $matcher->selectedNodeAggregateIds->toStringArray());
    }

    public function testFromArrayReadsNodeIdsFromTheLegacyShapeAndIgnoresPresets(): void
    {
        $matcher = MatcherConfiguration::fromArray([
            'selectedWorkspaces' => ['live'],
            'selectedDimensionPresets' => [['dimensionName' => 'language', 'presetName' => 'de']],
            'selectedNodes' => [
                'node-a' => ['whitelistedNodeTypes' => []],
                'node-b' => ['whitelistedNodeTypes' => ['Neos.Neos:Document']],
            ],
        ]);

        self::assertSame('default', $matcher->contentRepositoryId->value);
        self::assertSame(['live'], $matcher->selectedWorkspaceNameStrings());
        self::assertTrue($matcher->selectedDimensionSpacePoints->isEmpty());
        self::assertSame(['node-a', 'node-b'], $matcher->selectedNodeAggregateIds->toStringArray());
    }

    public function testFromArrayDefaultsMissingKeysToEmptySelections(): void
    {
        $matcher = MatcherConfiguration::fromArray([]);

        self::assertSame('default', $matcher->contentRepositoryId->value);
        self::assertSame([], $matcher->selectedWorkspaceNames);
        self::assertTrue($matcher->selectedDimensionSpacePoints->isEmpty());
        self::assertSame([], $matcher->selectedNodeAggregateIds->toStringArray());
    }

    public function testToArrayRoundTrips(): void
    {
        $original = [
            'contentRepositoryId' => 'default',
            'selectedWorkspaces' => ['marketing'],
            'selectedDimensionSpacePoints' => [['language' => 'de']],
            'selectedNodes' => ['node-a'],
        ];

        self::assertSame($original, MatcherConfiguration::fromArray($original)->toArray());
    }

    public function testFromSubmittedSelectionResolvesDimensionSpacePointsByHash(): void
    {
        $german = DimensionSpacePoint::fromArray(['language' => 'de']);
        $english = DimensionSpacePoint::fromArray(['language' => 'en']);
        $matcher = MatcherConfiguration::fromSubmittedSelection(
            ContentRepositoryId::fromString('default'),
            ['marketing'],
            [$german->hash],
            DimensionSpacePointSet::fromArray([$german, $english]),
            ['node-a'],
        );

        self::assertSame(['marketing'], $matcher->selectedWorkspaceNameStrings());
        self::assertTrue($matcher->selectedDimensionSpacePoints->contains($german));
        self::assertFalse($matcher->selectedDimensionSpacePoints->contains($english));
        self::assertTrue($matcher->selectedNodeAggregateIds->contain(NodeAggregateId::fromString('node-a')));
    }

    public function testFromSubmittedSelectionRejectsUnknownDimensionSpacePoints(): void
    {
        $this->expectException(InvalidDynamicRoleException::class);

        MatcherConfiguration::fromSubmittedSelection(ContentRepositoryId::fromString('default'), [], ['unknown-hash'], DimensionSpacePointSet::fromArray([]), []);
    }

    public function testFromSubmittedSelectionRejectsNonStringValues(): void
    {
        $this->expectException(InvalidDynamicRoleException::class);

        MatcherConfiguration::fromSubmittedSelection(ContentRepositoryId::fromString('default'), [], [], DimensionSpacePointSet::fromArray([]), [['nested' => 'value']]);
    }
}
