<?php

declare(strict_types=1);

namespace Sandstorm\NeosAcl\Tagging;

use Neos\ContentRepository\Core\ContentRepository;
use Neos\ContentRepository\Core\DimensionSpace\DimensionSpacePoint;
use Neos\ContentRepository\Core\DimensionSpace\DimensionSpacePointSet;
use Neos\ContentRepository\Core\Feature\SubtreeTagging\Command\TagSubtree;
use Neos\ContentRepository\Core\Feature\SubtreeTagging\Command\UntagSubtree;
use Neos\ContentRepository\Core\Feature\SubtreeTagging\Dto\SubtreeTag;
use Neos\ContentRepository\Core\Projection\ContentGraph\NodeAggregate;
use Neos\ContentRepository\Core\SharedModel\Node\NodeAggregateId;
use Neos\ContentRepository\Core\SharedModel\Node\NodeVariantSelectionStrategy;
use Neos\ContentRepository\Core\SharedModel\Workspace\WorkspaceName;
use Neos\Flow\Annotations as Flow;

/**
 * Writes subtree tags into the live workspace so that the explicitly tagged dimension
 * space points of a node aggregate become exactly the requested set. Commands run with
 * the caller's permissions; an administrator may write to live through its
 * `Neos.Neos:LivePublisher` parent role, CLI commands disable the checks themselves.
 *
 * The content repository offers no "only this variant" strategy, so every command
 * affects the given dimension space point and its specializations. Removals therefore
 * run first, generalizations first, and additions afterwards re-read the aggregate
 * before each command so that already covered specializations are skipped.
 */
#[Flow\Scope('singleton')]
final readonly class SubtreeTagWriter
{
    public function setExplicitTags(ContentRepository $contentRepository, NodeAggregateId $nodeAggregateId, SubtreeTag $tag, DimensionSpacePointSet $desired): void
    {
        $aggregate = $this->requireAggregate($contentRepository, $nodeAggregateId);
        $desiredAndCovered = $desired->getIntersection($aggregate->coveredDimensionSpacePoints);

        $toRemove = $aggregate->getCoveredDimensionsTaggedBy($tag, withoutInherited: true)->getDifference($desiredAndCovered);
        foreach ($this->mostGeneralFirst($contentRepository, $toRemove) as $dimensionSpacePoint) {
            $aggregate = $this->requireAggregate($contentRepository, $nodeAggregateId);
            if (!$aggregate->getCoveredDimensionsTaggedBy($tag, withoutInherited: true)->contains($dimensionSpacePoint)) {
                continue;
            }
            $contentRepository->handle(UntagSubtree::create(
                WorkspaceName::forLive(),
                $nodeAggregateId,
                $dimensionSpacePoint,
                NodeVariantSelectionStrategy::STRATEGY_ALL_SPECIALIZATIONS,
                $tag,
            ));
        }

        $aggregate = $this->requireAggregate($contentRepository, $nodeAggregateId);
        $toAdd = $desiredAndCovered->getDifference($aggregate->getCoveredDimensionsTaggedBy($tag, withoutInherited: true));
        foreach ($this->mostGeneralFirst($contentRepository, $toAdd) as $dimensionSpacePoint) {
            $aggregate = $this->requireAggregate($contentRepository, $nodeAggregateId);
            if ($aggregate->getCoveredDimensionsTaggedBy($tag, withoutInherited: true)->contains($dimensionSpacePoint)) {
                continue;
            }
            $contentRepository->handle(TagSubtree::create(
                WorkspaceName::forLive(),
                $nodeAggregateId,
                $dimensionSpacePoint,
                NodeVariantSelectionStrategy::STRATEGY_ALL_SPECIALIZATIONS,
                $tag,
            ));
        }
    }

    public function removeExplicitTags(ContentRepository $contentRepository, NodeAggregateId $nodeAggregateId, SubtreeTag $tag): void
    {
        $this->setExplicitTags($contentRepository, $nodeAggregateId, $tag, DimensionSpacePointSet::fromArray([]));
    }

    private function requireAggregate(ContentRepository $contentRepository, NodeAggregateId $nodeAggregateId): NodeAggregate
    {
        $aggregate = $contentRepository->getContentGraph(WorkspaceName::forLive())->findNodeAggregateById($nodeAggregateId);
        if ($aggregate === null) {
            throw new \RuntimeException(sprintf('Node aggregate "%s" does not exist in the live workspace', $nodeAggregateId->value), 1757600010);
        }

        return $aggregate;
    }

    /**
     * @return list<DimensionSpacePoint>
     */
    private function mostGeneralFirst(ContentRepository $contentRepository, DimensionSpacePointSet $dimensionSpacePoints): array
    {
        $variationGraph = $contentRepository->getVariationGraph();
        $ordered = array_values(iterator_to_array($dimensionSpacePoints));
        usort(
            $ordered,
            static fn (DimensionSpacePoint $a, DimensionSpacePoint $b): int => count($variationGraph->getIndexedGeneralizations($a)) <=> count($variationGraph->getIndexedGeneralizations($b)),
        );

        return $ordered;
    }
}
