<?php

declare(strict_types=1);

namespace Sandstorm\NeosAcl\ViewModel;

use Neos\Flow\Annotations as Flow;

#[Flow\Proxy(false)]
final readonly class DynamicRoleListItem
{
    /**
     * @param list<string> $parentRoleNames
     * @param list<string> $workspaceNames
     * @param list<string> $dimensionSpacePointLabels
     * @param list<string> $nodeLabels
     */
    public function __construct(
        public string $identifier,
        public string $name,
        public string $roleIdentifier,
        public bool $abstract,
        public array $parentRoleNames,
        public array $workspaceNames,
        public array $dimensionSpacePointLabels,
        public array $nodeLabels,
    ) {
    }
}
