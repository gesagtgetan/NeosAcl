<?php

declare(strict_types=1);

namespace Sandstorm\NeosAcl\ViewModel;

use Neos\Flow\Annotations as Flow;
use Sandstorm\NeosAcl\Tree\DocumentTreeNode;

#[Flow\Proxy(false)]
final readonly class DynamicRoleFormOptions
{
    /**
     * @param list<RoleOption> $parentRoles
     * @param list<WorkspaceOption> $workspaces
     * @param list<DimensionSpacePointOption> $dimensionSpacePoints empty when the content repository has no dimensions
     * @param list<DocumentTreeNode> $documentTree
     */
    public function __construct(
        public array $parentRoles,
        public array $workspaces,
        public array $dimensionSpacePoints,
        public array $documentTree,
        public string $childrenEndpoint,
    ) {
    }
}
