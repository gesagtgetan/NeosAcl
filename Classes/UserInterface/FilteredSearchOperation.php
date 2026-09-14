<?php

declare(strict_types=1);

namespace Sandstorm\NeosAcl\UserInterface;

use Neos\ContentRepository\Core\Projection\ContentGraph\Node;
use Neos\Eel\FlowQuery\FlowQuery;
use Neos\Flow\Annotations as Flow;
use Neos\Neos\Ui\FlowQueryOperations\SearchOperation;

class FilteredSearchOperation extends SearchOperation
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
    public function evaluate(FlowQuery $flowQuery, array $arguments): void
    {
        parent::evaluate($flowQuery, array_values($arguments));
        if (!$this->hideUneditableDocuments) {
            return;
        }
        /** @var list<Node> $nodes */
        $nodes = $flowQuery->getContext();
        $flowQuery->setContext(array_values(array_filter($nodes, $this->editableDocumentFilter->isVisible(...))));
    }
}
