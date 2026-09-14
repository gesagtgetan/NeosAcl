<?php

declare(strict_types=1);

namespace Sandstorm\NeosAcl\Service;

use Neos\Flow\Annotations as Flow;
use Sandstorm\NeosAcl\Domain\Model\DynamicRole;
use Sandstorm\NeosAcl\Tagging\DynamicRoleTagSynchronizer;
use Sandstorm\NeosAcl\Tagging\RestrictedSiteRootTagger;
use Sandstorm\NeosAcl\Workspace\DynamicRoleWorkspaceAccess;

/**
 * Brings the content repository and the workspace roles in line with a dynamic role.
 */
#[Flow\Scope('singleton')]
final readonly class DynamicRoleApplier
{
    public function __construct(
        private RestrictedSiteRootTagger $restrictedSiteRootTagger,
        private DynamicRoleTagSynchronizer $tagSynchronizer,
        private DynamicRoleWorkspaceAccess $workspaceAccess,
    ) {
    }

    public function apply(DynamicRole $dynamicRole): void
    {
        $this->restrictedSiteRootTagger->tagSitesRoot($dynamicRole->getMatcherConfiguration()->contentRepositoryId);
        $this->tagSynchronizer->synchronize($dynamicRole);
        $this->workspaceAccess->synchronize($dynamicRole);
    }

    public function revoke(DynamicRole $dynamicRole): void
    {
        $this->tagSynchronizer->removeAllTags($dynamicRole);
        $this->workspaceAccess->revokeAll($dynamicRole);
    }
}
