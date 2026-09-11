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
 * Puts the restriction tag on every site node so that editing anywhere requires a
 * grant of `Sandstorm.NeosAcl:EditAllNodes` or of a dynamic role.
 */
#[Flow\Scope('singleton')]
final readonly class RestrictedSiteRootTagger
{
    public function __construct(
        private ContentRepositoryRegistry $contentRepositoryRegistry,
        private SubtreeTagWriter $subtreeTagWriter,
    ) {
    }

    /**
     * @return int number of site node aggregates that carry the tag afterwards
     */
    public function tagSiteRoots(ContentRepositoryId $contentRepositoryId): int
    {
        $contentRepository = $this->contentRepositoryRegistry->get($contentRepositoryId);
        $contentGraph = $contentRepository->getContentGraph(WorkspaceName::forLive());
        $sitesRoot = $contentGraph->findRootNodeAggregateByType(NodeTypeNameFactory::forSites());
        if ($sitesRoot === null) {
            return 0;
        }

        $count = 0;
        foreach ($contentGraph->findChildNodeAggregates($sitesRoot->nodeAggregateId) as $siteAggregate) {
            $this->subtreeTagWriter->setExplicitTags(
                $contentRepository,
                $siteAggregate->nodeAggregateId,
                NeosAclSubtreeTag::restricted(),
                $siteAggregate->coveredDimensionSpacePoints,
            );
            ++$count;
        }

        return $count;
    }
}
