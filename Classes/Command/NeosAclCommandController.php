<?php

declare(strict_types=1);

namespace Sandstorm\NeosAcl\Command;

use Neos\ContentRepository\Core\Feature\SubtreeTagging\Dto\SubtreeTag;
use Neos\ContentRepository\Core\SharedModel\ContentRepository\ContentRepositoryId;
use Neos\ContentRepository\Core\SharedModel\Workspace\WorkspaceName;
use Neos\ContentRepositoryRegistry\ContentRepositoryRegistry;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Cli\CommandController;
use Neos\Flow\Security\Context as SecurityContext;
use Sandstorm\NeosAcl\Domain\Model\DynamicRole;
use Sandstorm\NeosAcl\Domain\Model\InvalidDynamicRoleException;
use Sandstorm\NeosAcl\Domain\Model\NeosAclSubtreeTag;
use Sandstorm\NeosAcl\Domain\Repository\DynamicRoleRepository;
use Sandstorm\NeosAcl\Service\DynamicRoleApplier;
use Sandstorm\NeosAcl\Tagging\RestrictedSiteRootTagger;

#[Flow\Scope('singleton')]
class NeosAclCommandController extends CommandController
{
    #[Flow\Inject]
    protected ContentRepositoryRegistry $contentRepositoryRegistry;

    #[Flow\Inject]
    protected RestrictedSiteRootTagger $restrictedSiteRootTagger;

    #[Flow\Inject]
    protected DynamicRoleRepository $dynamicRoleRepository;

    #[Flow\Inject]
    protected DynamicRoleApplier $dynamicRoleApplier;

    #[Flow\Inject]
    protected SecurityContext $securityContext;

    /**
     * Tag all site roots as restricted and re-apply every dynamic role.
     *
     * Run this once after installing the package and again whenever a site was added,
     * so that restricted editors cannot edit the new site until a dynamic role grants it.
     */
    public function setupCommand(): void
    {
        $this->securityContext->withoutAuthorizationChecks(function (): void {
            foreach ($this->contentRepositoryRegistry->getContentRepositoryIds() as $contentRepositoryId) {
                $tagged = $this->restrictedSiteRootTagger->tagSitesRoot($contentRepositoryId);
                $this->outputLine('Content repository "%s": %s', [$contentRepositoryId->value, $tagged ? 'the sites root carries the restriction tag.' : 'no sites root found.']);
            }
            foreach ($this->dynamicRoleRepository->findAllOrderedByName() as $dynamicRole) {
                $this->dynamicRoleApplier->apply($dynamicRole);
                $this->outputLine('Applied dynamic role "%s".', [$dynamicRole->getRoleIdentifier()]);
            }
        });
    }

    /**
     * Delete a dynamic role, its subtree tags and its workspace assignments.
     *
     * @param string $name Name of the dynamic role without the "Dynamic:" prefix
     */
    public function removeCommand(string $name): void
    {
        $dynamicRole = $this->dynamicRoleRepository->findOneByName($name);
        if ($dynamicRole === null) {
            $this->outputLine('<error>There is no dynamic role named "%s".</error>', [$name]);
            $this->quit(1);
        }
        $childRoles = $this->dynamicRoleRepository->findChildRoles($dynamicRole);
        if ($childRoles !== []) {
            $this->outputLine('<error>%s</error>', [InvalidDynamicRoleException::forRoleWithChildren($dynamicRole->getRoleIdentifier(), array_map(static fn (DynamicRole $child): string => $child->getRoleIdentifier(), $childRoles))->getMessage()]);
            $this->quit(1);
        }
        $this->securityContext->withoutAuthorizationChecks(fn () => $this->dynamicRoleApplier->revoke($dynamicRole));
        $this->dynamicRoleRepository->remove($dynamicRole);
        $this->outputLine('Deleted dynamic role "%s". Remove it from user accounts with user:removerole.', [$dynamicRole->getRoleIdentifier()]);
    }

    /**
     * Show which node aggregates carry the restriction tag and the tags of the dynamic roles.
     *
     * Reads the live workspace, so a grant that an editor does not see yet points to a
     * workspace that still needs a rebase.
     */
    public function listCommand(): void
    {
        foreach ($this->contentRepositoryRegistry->getContentRepositoryIds() as $contentRepositoryId) {
            $this->outputLine('<b>Content repository "%s"</b>', [$contentRepositoryId->value]);
            $this->outputTaggedAggregates($contentRepositoryId, NeosAclSubtreeTag::restricted());
        }
        foreach ($this->dynamicRoleRepository->findAllOrderedByName() as $dynamicRole) {
            $matcher = $dynamicRole->getMatcherConfiguration();
            $this->outputLine();
            $this->outputLine('<b>%s</b> (abstract: %s, parents: %s)', [
                $dynamicRole->getRoleIdentifier(),
                $dynamicRole->isAbstract() ? 'yes' : 'no',
                implode(', ', $dynamicRole->getParentRoleNames()),
            ]);
            $workspaceNames = $matcher->selectedWorkspaceNameStrings();
            $this->outputLine('  workspaces: %s', [$workspaceNames === [] ? '-' : implode(', ', $workspaceNames)]);
            $this->outputTaggedAggregates($matcher->contentRepositoryId, $dynamicRole->getSubtreeTag());
        }
    }

    private function outputTaggedAggregates(ContentRepositoryId $contentRepositoryId, SubtreeTag $tag): void
    {
        $contentGraph = $this->contentRepositoryRegistry->get($contentRepositoryId)->getContentGraph(WorkspaceName::forLive());
        $taggedAggregates = $contentGraph->findNodeAggregatesTaggedBy($tag);
        $this->outputLine('  tag "%s" on %d node aggregate(s)', [$tag->value, count($taggedAggregates)]);
        foreach ($taggedAggregates as $aggregate) {
            $this->outputLine('    %s (%s) in %s', [
                $aggregate->nodeAggregateId->value,
                $aggregate->nodeTypeName->value,
                $aggregate->getCoveredDimensionsTaggedBy($tag, withoutInherited: true)->toJson(),
            ]);
        }
    }
}
