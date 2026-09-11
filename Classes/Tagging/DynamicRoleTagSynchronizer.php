<?php

declare(strict_types=1);

namespace Sandstorm\NeosAcl\Tagging;

use Neos\ContentRepository\Core\ContentRepository;
use Neos\ContentRepository\Core\DimensionSpace\DimensionSpacePointSet;
use Neos\ContentRepository\Core\Projection\ContentGraph\NodeAggregate;
use Neos\ContentRepository\Core\SharedModel\Workspace\WorkspaceName;
use Neos\ContentRepositoryRegistry\ContentRepositoryRegistry;
use Neos\Flow\Annotations as Flow;
use Sandstorm\NeosAcl\Domain\Model\DynamicRole;
use Sandstorm\NeosAcl\Domain\Model\MatcherConfiguration;

/**
 * Makes the node aggregates tagged with a dynamic role's subtree tag match the role's
 * selected nodes and dimension space points. The content repository is the source of
 * truth for the current state, so no snapshot of the previous selection is needed.
 */
#[Flow\Scope('singleton')]
final readonly class DynamicRoleTagSynchronizer
{
    public function __construct(
        private ContentRepositoryRegistry $contentRepositoryRegistry,
        private SubtreeTagWriter $subtreeTagWriter,
    ) {
    }

    public function synchronize(DynamicRole $dynamicRole): void
    {
        $matcher = $dynamicRole->getMatcherConfiguration();
        $contentRepository = $this->contentRepositoryRegistry->get($matcher->contentRepositoryId);
        $tag = $dynamicRole->getSubtreeTag();
        $contentGraph = $contentRepository->getContentGraph(WorkspaceName::forLive());

        foreach ($contentGraph->findNodeAggregatesTaggedBy($tag) as $taggedAggregate) {
            if (!$matcher->selectedNodeAggregateIds->contain($taggedAggregate->nodeAggregateId)) {
                $this->subtreeTagWriter->removeExplicitTags($contentRepository, $taggedAggregate->nodeAggregateId, $tag);
            }
        }

        foreach ($matcher->selectedNodeAggregateIds as $nodeAggregateId) {
            $aggregate = $contentGraph->findNodeAggregateById($nodeAggregateId);
            if ($aggregate === null) {
                continue;
            }
            $this->subtreeTagWriter->setExplicitTags(
                $contentRepository,
                $nodeAggregateId,
                $tag,
                $this->desiredDimensionSpacePoints($contentRepository, $matcher, $aggregate),
            );
        }
    }

    public function removeAllTags(DynamicRole $dynamicRole): void
    {
        $contentRepository = $this->contentRepositoryRegistry->get($dynamicRole->getMatcherConfiguration()->contentRepositoryId);
        $tag = $dynamicRole->getSubtreeTag();
        foreach ($contentRepository->getContentGraph(WorkspaceName::forLive())->findNodeAggregatesTaggedBy($tag) as $taggedAggregate) {
            $this->subtreeTagWriter->removeExplicitTags($contentRepository, $taggedAggregate->nodeAggregateId, $tag);
        }
    }

    private function desiredDimensionSpacePoints(ContentRepository $contentRepository, MatcherConfiguration $matcher, NodeAggregate $aggregate): DimensionSpacePointSet
    {
        if ($matcher->selectedDimensionSpacePoints->isEmpty()) {
            return $aggregate->coveredDimensionSpacePoints;
        }
        $variationGraph = $contentRepository->getVariationGraph();
        $withSpecializations = DimensionSpacePointSet::fromArray([]);
        foreach ($matcher->selectedDimensionSpacePoints as $dimensionSpacePoint) {
            $withSpecializations = $withSpecializations->getUnion($variationGraph->getSpecializationSet($dimensionSpacePoint, true));
        }

        return $withSpecializations->getIntersection($aggregate->coveredDimensionSpacePoints);
    }
}
