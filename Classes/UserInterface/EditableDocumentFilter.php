<?php

declare(strict_types=1);

namespace Sandstorm\NeosAcl\UserInterface;

use Neos\ContentRepository\Core\Feature\SubtreeTagging\Dto\SubtreeTag;
use Neos\ContentRepository\Core\Projection\ContentGraph\Filter\FindAncestorNodesFilter;
use Neos\ContentRepository\Core\Projection\ContentGraph\Node;
use Neos\ContentRepositoryRegistry\ContentRepositoryRegistry;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Security\Context as SecurityContext;
use Neos\Flow\Security\Policy\Role;
use Neos\Neos\Security\Authorization\ContentRepositoryAuthorizationService;
use Neos\Neos\Security\Authorization\Privilege\EditNodePrivilege;
use Sandstorm\NeosAcl\Domain\Model\DynamicRole;

/**
 * Decides which documents the Neos UI document tree shows for members of dynamic roles:
 * those they may edit and the ancestors leading to them. Users without a dynamic role see
 * the whole tree, as they did with the Neos 8 version of this package. Purely cosmetic,
 * the edit privilege is enforced by the content repository regardless.
 */
#[Flow\Scope('singleton')]
final class EditableDocumentFilter
{
    /**
     * Ids of nodes that have an editable descendant, keyed by workspace and dimension space point hash.
     *
     * @var array<string, array<string, true>>
     */
    private array $ancestorIdsOfEditableSubtrees = [];

    public function __construct(
        private readonly SecurityContext $securityContext,
        private readonly ContentRepositoryAuthorizationService $authorizationService,
        private readonly ContentRepositoryRegistry $contentRepositoryRegistry,
    ) {
    }

    public function isVisible(Node $node): bool
    {
        if (!$this->currentUserHasDynamicRole()) {
            return true;
        }
        if ($this->authorizationService->getNodePermissions($node, $this->securityContext->getRoles())->edit) {
            return true;
        }

        return isset($this->ancestorIdsOfEditableSubtrees($node)[$node->aggregateId->value]);
    }

    /**
     * @return array<string, true>
     */
    private function ancestorIdsOfEditableSubtrees(Node $node): array
    {
        $cacheKey = $node->contentRepositoryId->value . '|' . $node->workspaceName->value . '|' . $node->dimensionSpacePoint->hash;
        if (isset($this->ancestorIdsOfEditableSubtrees[$cacheKey])) {
            return $this->ancestorIdsOfEditableSubtrees[$cacheKey];
        }

        $contentRepository = $this->contentRepositoryRegistry->get($node->contentRepositoryId);
        $contentGraph = $contentRepository->getContentGraph($node->workspaceName);
        $subgraph = $this->contentRepositoryRegistry->subgraphForNode($node);
        $ancestorIds = [];
        foreach ($this->grantedEditTags() as $tag) {
            foreach ($contentGraph->findNodeAggregatesTaggedBy($tag) as $taggedAggregate) {
                foreach ($subgraph->findAncestorNodes($taggedAggregate->nodeAggregateId, FindAncestorNodesFilter::create()) as $ancestor) {
                    $ancestorIds[$ancestor->aggregateId->value] = true;
                }
            }
        }
        $this->ancestorIdsOfEditableSubtrees[$cacheKey] = $ancestorIds;

        return $ancestorIds;
    }

    private function currentUserHasDynamicRole(): bool
    {
        foreach ($this->securityContext->getRoles() as $role) {
            \assert($role instanceof Role);
            if (str_starts_with($role->getIdentifier(), DynamicRole::ROLE_IDENTIFIER_PREFIX)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<SubtreeTag>
     */
    private function grantedEditTags(): array
    {
        $tags = [];
        foreach ($this->securityContext->getRoles() as $role) {
            \assert($role instanceof Role);
            foreach ($role->getPrivilegesByType(EditNodePrivilege::class) as $privilege) {
                \assert($privilege instanceof EditNodePrivilege);
                if (!$privilege->isGranted()) {
                    continue;
                }
                foreach ($privilege->getSubtreeTags() as $tag) {
                    $tags[$tag->value] = $tag;
                }
            }
        }

        return array_values($tags);
    }
}
