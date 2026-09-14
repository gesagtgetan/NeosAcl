<?php

declare(strict_types=1);

namespace Sandstorm\NeosAcl\UserInterface;

use Neos\ContentRepository\Core\SharedModel\Node\NodeAddress;
use Neos\ContentRepositoryRegistry\ContentRepositoryRegistry;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Aop\JoinPointInterface;
use Neos\Neos\Ui\Application\ReloadNodes\MinimalNodeForTree;
use Neos\Neos\Ui\Application\ReloadNodes\NodeMap;
use Neos\Neos\Ui\Application\ReloadNodes\ReloadNodesQuery;
use Neos\Neos\Ui\Application\ReloadNodes\ReloadNodesQueryResult;

/**
 * After publishing or discarding, the Neos UI reloads the document tree through
 * `ReloadNodesQueryHandler` instead of the FlowQuery operations, so the same filter is
 * applied to that result.
 */
#[Flow\Aspect]
class FilteredReloadNodesAspect
{
    #[Flow\Inject]
    protected EditableDocumentFilter $editableDocumentFilter;

    #[Flow\Inject]
    protected ContentRepositoryRegistry $contentRepositoryRegistry;

    #[Flow\InjectConfiguration(package: 'Sandstorm.NeosAcl', path: 'userInterface.hideUneditableDocuments')]
    protected bool $hideUneditableDocuments = true;

    #[Flow\Around('method(Neos\Neos\Ui\Application\ReloadNodes\ReloadNodesQueryHandler->handle())')]
    public function filterReloadedNodes(JoinPointInterface $joinPoint): ReloadNodesQueryResult
    {
        $result = $joinPoint->getAdviceChain()->proceed($joinPoint);
        \assert($result instanceof ReloadNodesQueryResult);
        if (!$this->hideUneditableDocuments) {
            return $result;
        }
        $query = $joinPoint->getMethodArgument('query');
        \assert($query instanceof ReloadNodesQuery);
        $subgraph = $this->contentRepositoryRegistry->get($query->contentRepositoryId)
            ->getContentSubgraph($query->workspaceName, $query->dimensionSpacePoint);

        $alwaysVisible = [$query->documentId->value => true];
        foreach ($query->ancestorsOfDocumentIds as $ancestorId) {
            $alwaysVisible[$ancestorId->value] = true;
        }
        $visibleItems = [];
        $items = $result->nodes->jsonSerialize();
        foreach (is_array($items) ? $items : [] as $item) {
            \assert($item instanceof MinimalNodeForTree);
            $aggregateId = NodeAddress::fromJsonString($item->getNodeAddressAsString())->aggregateId;
            $node = $subgraph->findNodeById($aggregateId);
            if ($node === null || isset($alwaysVisible[$aggregateId->value]) || $this->editableDocumentFilter->isVisible($node)) {
                $visibleItems[] = $item;
            }
        }

        return new ReloadNodesQueryResult($result->documentId, new NodeMap(...$visibleItems));
    }
}
