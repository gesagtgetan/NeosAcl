<?php

declare(strict_types=1);

namespace Sandstorm\NeosAcl\Tagging;

use Neos\ContentRepository\Core\SharedModel\ContentRepository\ContentRepositoryId;
use Neos\ContentRepository\Core\SharedModel\Workspace\WorkspaceName;
use Neos\ContentRepositoryRegistry\ContentRepositoryRegistry;
use Neos\Flow\Annotations as Flow;
use Neos\Neos\Domain\Service\NodeTypeNameFactory;
use Sandstorm\NeosAcl\Domain\Model\NeosAclSubtreeTag;

/**
 * Puts the restriction tag on the root aggregate of all sites so that editing anywhere
 * requires a grant of `Sandstorm.NeosAcl:EditAllNodes` or of a dynamic role; sites added
 * later inherit the tag without another setup run.
 */
#[Flow\Scope('singleton')]
final readonly class RestrictedSiteRootTagger
{
    public function __construct(
        private ContentRepositoryRegistry $contentRepositoryRegistry,
        private SubtreeTagWriter $subtreeTagWriter,
    ) {
    }

    public function tagSitesRoot(ContentRepositoryId $contentRepositoryId): bool
    {
        $contentRepository = $this->contentRepositoryRegistry->get($contentRepositoryId);
        $contentGraph = $contentRepository->getContentGraph(WorkspaceName::forLive());
        $sitesRoot = $contentGraph->findRootNodeAggregateByType(NodeTypeNameFactory::forSites());
        if ($sitesRoot === null) {
            return false;
        }

        $this->subtreeTagWriter->setExplicitTags($contentRepository, $sitesRoot->nodeAggregateId, NeosAclSubtreeTag::restricted(), $sitesRoot->coveredDimensionSpacePoints);
        foreach ($contentGraph->findChildNodeAggregates($sitesRoot->nodeAggregateId) as $siteAggregate) {
            $this->subtreeTagWriter->removeExplicitTags($contentRepository, $siteAggregate->nodeAggregateId, NeosAclSubtreeTag::restricted());
        }

        return true;
    }
}
