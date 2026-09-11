<?php

declare(strict_types=1);

namespace Sandstorm\NeosAcl\Domain\Model;

use Neos\ContentRepository\Core\DimensionSpace\DimensionSpacePoint;
use Neos\ContentRepository\Core\DimensionSpace\DimensionSpacePointSet;
use Neos\ContentRepository\Core\SharedModel\ContentRepository\ContentRepositoryId;
use Neos\ContentRepository\Core\SharedModel\Node\NodeAggregateId;
use Neos\ContentRepository\Core\SharedModel\Node\NodeAggregateIds;
use Neos\ContentRepository\Core\SharedModel\Workspace\WorkspaceName;
use Neos\Flow\Annotations as Flow;

/**
 * What a dynamic role grants, stored as JSON in {@see DynamicRole::$matcher}:
 *
 *   {
 *     "contentRepositoryId": "default",
 *     "selectedWorkspaces": ["marketing"],
 *     "selectedDimensionSpacePoints": [{"language": "de"}],
 *     "selectedNodes": ["a3f1..."]
 *   }
 *
 * Selected nodes are node aggregate ids whose subtrees become editable. An empty
 * dimension selection means all dimension space points the node covers; a selected
 * dimension space point includes its specializations. Selected workspaces receive a
 * collaborator assignment for the role. The legacy shape (Neos 8) with a node map
 * and dimension presets is read for the node ids only.
 */
#[Flow\Proxy(false)]
final readonly class MatcherConfiguration
{
    /**
     * @param list<WorkspaceName> $selectedWorkspaceNames
     */
    private function __construct(
        public ContentRepositoryId $contentRepositoryId,
        public array $selectedWorkspaceNames,
        public DimensionSpacePointSet $selectedDimensionSpacePoints,
        public NodeAggregateIds $selectedNodeAggregateIds,
    ) {
    }

    /**
     * @param list<WorkspaceName> $selectedWorkspaceNames
     */
    public static function create(
        ContentRepositoryId $contentRepositoryId,
        array $selectedWorkspaceNames,
        DimensionSpacePointSet $selectedDimensionSpacePoints,
        NodeAggregateIds $selectedNodeAggregateIds,
    ): self {
        return new self($contentRepositoryId, $selectedWorkspaceNames, $selectedDimensionSpacePoints, $selectedNodeAggregateIds);
    }

    /**
     * Builds the configuration from raw form input; each list may only contain strings.
     *
     * @param array<mixed> $submittedWorkspaceNames
     * @param array<mixed> $submittedDimensionSpacePointHashes hashes of points in $availableDimensionSpacePoints
     * @param array<mixed> $submittedNodeAggregateIds
     */
    public static function fromSubmittedSelection(
        ContentRepositoryId $contentRepositoryId,
        array $submittedWorkspaceNames,
        array $submittedDimensionSpacePointHashes,
        DimensionSpacePointSet $availableDimensionSpacePoints,
        array $submittedNodeAggregateIds,
    ): self {
        $dimensionSpacePoints = [];
        foreach (self::stringList($submittedDimensionSpacePointHashes, 'dimension space point') as $hash) {
            $dimensionSpacePoints[] = $availableDimensionSpacePoints[$hash] ?? throw InvalidDynamicRoleException::forUnknownDimensionSpacePoint($hash);
        }

        return new self(
            $contentRepositoryId,
            array_map(WorkspaceName::fromString(...), self::stringList($submittedWorkspaceNames, 'workspace')),
            DimensionSpacePointSet::fromArray($dimensionSpacePoints),
            NodeAggregateIds::fromArray(array_map(NodeAggregateId::fromString(...), self::stringList($submittedNodeAggregateIds, 'node'))),
        );
    }

    /**
     * @param array<string, mixed> $matcher
     */
    public static function fromArray(array $matcher): self
    {
        $contentRepositoryId = $matcher['contentRepositoryId'] ?? 'default';
        if (!is_string($contentRepositoryId)) {
            throw new \InvalidArgumentException('contentRepositoryId must be a string', 1757600005);
        }

        return new self(
            ContentRepositoryId::fromString($contentRepositoryId),
            array_map(WorkspaceName::fromString(...), self::stringList(self::arrayAt($matcher, 'selectedWorkspaces'), 'workspace')),
            DimensionSpacePointSet::fromArray(array_map(
                static fn (array $coordinates): DimensionSpacePoint => DimensionSpacePoint::fromArray(self::coordinates($coordinates)),
                array_values(array_filter(self::arrayAt($matcher, 'selectedDimensionSpacePoints'), is_array(...))),
            )),
            NodeAggregateIds::fromArray(array_map(NodeAggregateId::fromString(...), self::nodeAggregateIdStrings(self::arrayAt($matcher, 'selectedNodes')))),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'contentRepositoryId' => $this->contentRepositoryId->value,
            'selectedWorkspaces' => array_map(static fn (WorkspaceName $workspaceName): string => $workspaceName->value, $this->selectedWorkspaceNames),
            'selectedDimensionSpacePoints' => array_values(array_map(
                static fn (DimensionSpacePoint $dimensionSpacePoint): array => $dimensionSpacePoint->coordinates,
                iterator_to_array($this->selectedDimensionSpacePoints),
            )),
            'selectedNodes' => $this->selectedNodeAggregateIds->toStringArray(),
        ];
    }

    public function hasSelectedWorkspace(WorkspaceName $workspaceName): bool
    {
        foreach ($this->selectedWorkspaceNames as $selectedWorkspaceName) {
            if ($selectedWorkspaceName->equals($workspaceName)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    public function selectedWorkspaceNameStrings(): array
    {
        return array_map(static fn (WorkspaceName $workspaceName): string => $workspaceName->value, $this->selectedWorkspaceNames);
    }

    /**
     * @param array<string, mixed> $matcher
     *
     * @return array<mixed>
     */
    private static function arrayAt(array $matcher, string $key): array
    {
        $value = $matcher[$key] ?? [];
        if (!is_array($value)) {
            throw new \InvalidArgumentException(sprintf('%s must be an array, got %s', $key, get_debug_type($value)), 1757600006);
        }

        return $value;
    }

    /**
     * @param array<mixed> $coordinates
     *
     * @return array<string, string>
     */
    private static function coordinates(array $coordinates): array
    {
        $validated = [];
        foreach ($coordinates as $dimensionId => $value) {
            if (!is_string($dimensionId) || !is_string($value)) {
                throw new \InvalidArgumentException('Dimension space point coordinates must map dimension ids to values', 1757600012);
            }
            $validated[$dimensionId] = $value;
        }

        return $validated;
    }

    /**
     * @param array<mixed> $values
     *
     * @return list<string>
     */
    private static function stringList(array $values, string $selectionName): array
    {
        $strings = [];
        foreach ($values as $value) {
            if (!is_string($value)) {
                throw InvalidDynamicRoleException::forNonStringSelection($selectionName);
            }
            $strings[] = $value;
        }

        return $strings;
    }

    /**
     * Accepts the current list of ids as well as the legacy map of id => node options.
     *
     * @param array<mixed> $selectedNodes
     *
     * @return list<string>
     */
    private static function nodeAggregateIdStrings(array $selectedNodes): array
    {
        $ids = [];
        foreach ($selectedNodes as $key => $value) {
            if (is_string($value)) {
                $ids[] = $value;
            } elseif (is_array($value) && is_string($key)) {
                $ids[] = $key;
            }
        }

        return $ids;
    }
}
