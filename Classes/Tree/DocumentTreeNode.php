<?php

declare(strict_types=1);

namespace Sandstorm\NeosAcl\Tree;

use Neos\Flow\Annotations as Flow;

#[Flow\Proxy(false)]
final readonly class DocumentTreeNode implements \JsonSerializable
{
    /**
     * @param list<self>|null $children null when the children were not loaded
     */
    public function __construct(
        public string $aggregateId,
        public string $label,
        public string $nodeTypeName,
        public bool $hasChildren,
        public bool $selected,
        public ?array $children,
    ) {
    }

    /**
     * @return array{aggregateId: string, label: string, nodeTypeName: string, hasChildren: bool, selected: bool}
     */
    public function jsonSerialize(): array
    {
        return [
            'aggregateId' => $this->aggregateId,
            'label' => $this->label,
            'nodeTypeName' => $this->nodeTypeName,
            'hasChildren' => $this->hasChildren,
            'selected' => $this->selected,
        ];
    }
}
