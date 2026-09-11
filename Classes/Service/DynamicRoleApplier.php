<?php

declare(strict_types=1);

namespace Sandstorm\NeosAcl\Service;

use Neos\Flow\Annotations as Flow;
use Neos\Fusion\Core\Cache\ContentCache;
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
        private ContentCache $contentCache,
    ) {
    }

    public function apply(DynamicRole $dynamicRole): void
    {
        $this->restrictedSiteRootTagger->tagSiteRoots($dynamicRole->getMatcherConfiguration()->contentRepositoryId);
        $this->tagSynchronizer->synchronize($dynamicRole);
        $this->workspaceAccess->synchronize($dynamicRole);
        $this->flushContentCache();
    }

    public function revoke(DynamicRole $dynamicRole): void
    {
        $this->tagSynchronizer->removeAllTags($dynamicRole);
        $this->workspaceAccess->revokeAll($dynamicRole);
        $this->flushContentCache();
    }

    /**
     * Cached content may still carry editable markers for a user whose access was
     * just removed, which makes the editor fail on the first keystroke.
     */
    private function flushContentCache(): void
    {
        $this->contentCache->flush();
    }
}
