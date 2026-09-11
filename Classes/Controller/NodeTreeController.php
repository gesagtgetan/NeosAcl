<?php

declare(strict_types=1);

namespace Sandstorm\NeosAcl\Controller;

use Neos\ContentRepository\Core\SharedModel\Node\NodeAggregateId;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Mvc\Controller\ActionController;
use Neos\Flow\Mvc\View\JsonView;
use Neos\Neos\FrontendRouting\SiteDetection\SiteDetectionResult;
use Sandstorm\NeosAcl\Tree\DocumentTreeBuilder;
use Sandstorm\NeosAcl\Tree\DocumentTreeNode;

/**
 * JSON endpoint the node picker of the dynamic role form uses to expand a document.
 */
class NodeTreeController extends ActionController
{
    protected $defaultViewObjectName = JsonView::class;

    #[Flow\Inject]
    protected DocumentTreeBuilder $documentTreeBuilder;

    public function __construct()
    {
        $this->supportedMediaTypes = ['application/json'];
    }

    public function childrenAction(string $parentNodeAggregateId): void
    {
        $contentRepositoryId = SiteDetectionResult::fromRequest($this->request->getHttpRequest())->contentRepositoryId;
        $children = $this->documentTreeBuilder->children($contentRepositoryId, NodeAggregateId::fromString($parentNodeAggregateId));
        $this->view->assign('value', array_map(static fn (DocumentTreeNode $child): array => $child->jsonSerialize(), $children));
    }
}
