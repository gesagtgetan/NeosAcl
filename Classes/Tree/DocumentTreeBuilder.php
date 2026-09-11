<?php

declare(strict_types=1);

namespace Sandstorm\NeosAcl\Tree;

use Neos\ContentRepository\Core\ContentRepository;
use Neos\ContentRepository\Core\DimensionSpace\DimensionSpacePoint;
use Neos\ContentRepository\Core\Projection\ContentGraph\ContentSubgraphInterface;
use Neos\ContentRepository\Core\Projection\ContentGraph\Filter\CountChildNodesFilter;
use Neos\ContentRepository\Core\Projection\ContentGraph\Filter\FindAncestorNodesFilter;
use Neos\ContentRepository\Core\Projection\ContentGraph\Filter\FindChildNodesFilter;
use Neos\ContentRepository\Core\Projection\ContentGraph\Node;
use Neos\ContentRepository\Core\SharedModel\ContentRepository\ContentRepositoryId;
use Neos\ContentRepository\Core\SharedModel\Node\NodeAggregateId;
use Neos\ContentRepository\Core\SharedModel\Node\NodeAggregateIds;
use Neos\ContentRepository\Core\SharedModel\Workspace\WorkspaceName;
use Neos\ContentRepositoryRegistry\ContentRepositoryRegistry;
use Neos\Flow\Annotations as Flow;
use Neos\Neos\Domain\NodeLabel\NodeLabelGeneratorInterface;
use Neos\Neos\Domain\Service\NodeTypeNameFactory;
use Neos\Neos\Domain\SubtreeTagging\NeosVisibilityConstraints;

/**
 * Reads the document tree of the live workspace in the primary dimension space point
 * for the node picker of the dynamic role form.
 */
#[Flow\Scope('singleton')]
final readonly class DocumentTreeBuilder
{
    public function __construct(
        private ContentRepositoryRegistry $contentRepositoryRegistry,
        private NodeLabelGeneratorInterface $nodeLabelGenerator,
    ) {
    }

    /**
     * Site nodes with their descendants down to $loadingDepth; ancestors of selected nodes are always loaded.
     *
     * @return list<DocumentTreeNode>
     */
    public function build(ContentRepositoryId $contentRepositoryId, NodeAggregateIds $selectedNodeAggregateIds, int $loadingDepth): array
    {
        $contentRepository = $this->contentRepositoryRegistry->get($contentRepositoryId);
        $subgraph = $this->primarySubgraph($contentRepository);
        $contentGraph = $contentRepository->getContentGraph(WorkspaceName::forLive());
        $sitesRoot = $contentGraph->findRootNodeAggregateByType(NodeTypeNameFactory::forSites());
        if ($sitesRoot === null) {
            return [];
        }

        $ancestorIdsOfSelection = [];
        foreach ($selectedNodeAggregateIds as $selectedNodeAggregateId) {
            foreach ($subgraph->findAncestorNodes($selectedNodeAggregateId, FindAncestorNodesFilter::create()) as $ancestor) {
                $ancestorIdsOfSelection[$ancestor->aggregateId->value] = true;
            }
        }

        $siteTreeNodes = [];
        foreach ($contentGraph->findChildNodeAggregates($sitesRoot->nodeAggregateId) as $siteAggregate) {
            $siteNode = $subgraph->findNodeById($siteAggregate->nodeAggregateId);
            if ($siteNode === null) {
                continue;
            }
            $siteTreeNodes[] = $this->buildNode($subgraph, $siteNode, 1, $loadingDepth, $selectedNodeAggregateIds, $ancestorIdsOfSelection);
        }

        return $siteTreeNodes;
    }

    /**
     * Direct document children without their own children loaded.
     *
     * @return list<DocumentTreeNode>
     */
    public function children(ContentRepositoryId $contentRepositoryId, NodeAggregateId $parentNodeAggregateId): array
    {
        $subgraph = $this->primarySubgraph($this->contentRepositoryRegistry->get($contentRepositoryId));
        $children = [];
        foreach ($subgraph->findChildNodes($parentNodeAggregateId, self::documentChildrenFilter()) as $childNode) {
            $children[] = $this->buildNode($subgraph, $childNode, 1, 0, NodeAggregateIds::createEmpty(), []);
        }

        return $children;
    }

    /**
     * @return list<string>
     */
    public function labels(ContentRepositoryId $contentRepositoryId, NodeAggregateIds $nodeAggregateIds): array
    {
        $subgraph = $this->primarySubgraph($this->contentRepositoryRegistry->get($contentRepositoryId));
        $labels = [];
        foreach ($nodeAggregateIds as $nodeAggregateId) {
            $node = $subgraph->findNodeById($nodeAggregateId);
            $labels[] = $node === null ? $nodeAggregateId->value : $this->nodeLabelGenerator->getLabel($node);
        }

        return $labels;
    }

    /**
     * @param array<string, true> $forceExpandedIds
     */
    private function buildNode(ContentSubgraphInterface $subgraph, Node $node, int $depth, int $loadingDepth, NodeAggregateIds $selectedNodeAggregateIds, array $forceExpandedIds): DocumentTreeNode
    {
        $childCount = $subgraph->countChildNodes($node->aggregateId, CountChildNodesFilter::fromFindChildNodesFilter(self::documentChildrenFilter()));
        $expand = $childCount > 0 && ($depth < $loadingDepth || isset($forceExpandedIds[$node->aggregateId->value]));
        $children = null;
        if ($expand) {
            $children = [];
            foreach ($subgraph->findChildNodes($node->aggregateId, self::documentChildrenFilter()) as $childNode) {
                $children[] = $this->buildNode($subgraph, $childNode, $depth + 1, $loadingDepth, $selectedNodeAggregateIds, $forceExpandedIds);
            }
        }

        return new DocumentTreeNode(
            $node->aggregateId->value,
            $this->nodeLabelGenerator->getLabel($node),
            $node->nodeTypeName->value,
            $childCount > 0,
            $selectedNodeAggregateIds->contain($node->aggregateId),
            $children,
        );
    }

    private function primarySubgraph(ContentRepository $contentRepository): ContentSubgraphInterface
    {
        $rootGeneralizations = array_values($contentRepository->getVariationGraph()->getRootGeneralizations());
        $dimensionSpacePoint = $rootGeneralizations[0] ?? DimensionSpacePoint::createWithoutDimensions();

        return $contentRepository->getContentGraph(WorkspaceName::forLive())->getSubgraph($dimensionSpacePoint, NeosVisibilityConstraints::excludeRemoved());
    }

    private static function documentChildrenFilter(): FindChildNodesFilter
    {
        return FindChildNodesFilter::create(nodeTypes: NodeTypeNameFactory::forDocument()->value);
    }
}
