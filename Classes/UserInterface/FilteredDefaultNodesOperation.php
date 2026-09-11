<?php

declare(strict_types=1);

namespace Sandstorm\NeosAcl\UserInterface;

use Neos\ContentRepository\Core\Projection\ContentGraph\Node;
use Neos\Eel\FlowQuery\FlowQuery;
use Neos\Flow\Annotations as Flow;
use Neos\Neos\Ui\FlowQueryOperations\NeosUiDefaultNodesOperation;

/**
 * Replaces the Neos UI operation that loads the document tree and removes documents the
 * user cannot edit, unless `Sandstorm.NeosAcl.userInterface.hideUneditableDocuments` is off.
 */
class FilteredDefaultNodesOperation extends NeosUiDefaultNodesOperation
{
    protected static $priority = 1000;

    #[Flow\Inject]
    protected EditableDocumentFilter $editableDocumentFilter;

    #[Flow\InjectConfiguration(package: 'Sandstorm.NeosAcl', path: 'userInterface.hideUneditableDocuments')]
    protected bool $hideUneditableDocuments = true;

    /**
     * @param FlowQuery $flowQuery
     * @param array<mixed> $arguments
     */
    public function evaluate(FlowQuery $flowQuery, array $arguments)
    {
        parent::evaluate($flowQuery, array_values($arguments));
        if (!$this->hideUneditableDocuments) {
            return;
        }
        /** @var array<string, Node> $nodes */
        $nodes = $flowQuery->getContext();
        $flowQuery->setContext(array_filter($nodes, $this->editableDocumentFilter->isVisible(...)));
    }
}
