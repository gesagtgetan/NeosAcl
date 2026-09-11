<?php

declare(strict_types=1);

namespace Sandstorm\NeosAcl\Service;

use Neos\ContentRepository\Core\ContentRepository;
use Neos\ContentRepository\Core\Dimension\ContentDimensionId;
use Neos\ContentRepository\Core\DimensionSpace\DimensionSpacePoint;
use Neos\ContentRepository\Core\DimensionSpace\DimensionSpacePointSet;
use Neos\ContentRepository\Core\SharedModel\ContentRepository\ContentRepositoryId;
use Neos\ContentRepository\Core\SharedModel\Node\NodeAggregateIds;
use Neos\ContentRepositoryRegistry\ContentRepositoryRegistry;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Security\Policy\PolicyService;
use Neos\Flow\Security\Policy\Role;
use Neos\Neos\Domain\Model\WorkspaceClassification;
use Neos\Neos\Domain\Service\WorkspaceService;
use Sandstorm\NeosAcl\Domain\Model\DynamicRole;
use Sandstorm\NeosAcl\Tree\DocumentTreeBuilder;
use Sandstorm\NeosAcl\ViewModel\DimensionSpacePointOption;
use Sandstorm\NeosAcl\ViewModel\DynamicRoleFormOptions;
use Sandstorm\NeosAcl\ViewModel\RoleOption;
use Sandstorm\NeosAcl\ViewModel\WorkspaceOption;

#[Flow\Scope('singleton')]
final readonly class DynamicRoleFormOptionsFactory
{
    /**
     * Roles that grant everything anyway or are technical; offering them as parents would defeat the purpose.
     */
    private const HIDDEN_PARENT_ROLES = [
        'Neos.Flow:Everybody',
        'Neos.Flow:Anonymous',
        'Neos.Flow:AuthenticatedUser',
        'Neos.Neos:Administrator',
        'Neos.Neos:Editor',
        'Neos.Neos:SetupUser',
    ];

    public function __construct(
        private PolicyService $policyService,
        private ContentRepositoryRegistry $contentRepositoryRegistry,
        private WorkspaceService $workspaceService,
        private DocumentTreeBuilder $documentTreeBuilder,
    ) {
    }

    public function create(ContentRepositoryId $contentRepositoryId, ?DynamicRole $editedRole, int $treeLoadingDepth, string $childrenEndpoint): DynamicRoleFormOptions
    {
        $contentRepository = $this->contentRepositoryRegistry->get($contentRepositoryId);
        $selectedNodeAggregateIds = $editedRole?->getMatcherConfiguration()->selectedNodeAggregateIds ?? NodeAggregateIds::createEmpty();

        return new DynamicRoleFormOptions(
            $this->parentRoleOptions($editedRole),
            $this->workspaceOptions($contentRepository),
            $this->dimensionSpacePointOptions($contentRepository),
            $this->documentTreeBuilder->build($contentRepositoryId, $selectedNodeAggregateIds, $treeLoadingDepth),
            $childrenEndpoint,
        );
    }

    /**
     * @return list<string>
     */
    public function dimensionSpacePointLabels(ContentRepositoryId $contentRepositoryId, DimensionSpacePointSet $dimensionSpacePoints): array
    {
        $contentRepository = $this->contentRepositoryRegistry->get($contentRepositoryId);
        $labels = [];
        foreach ($dimensionSpacePoints as $dimensionSpacePoint) {
            $labels[] = $this->dimensionSpacePointLabel($contentRepository, $dimensionSpacePoint);
        }

        return $labels;
    }

    /**
     * @return list<RoleOption>
     */
    private function parentRoleOptions(?DynamicRole $editedRole): array
    {
        $hiddenRoles = self::HIDDEN_PARENT_ROLES;
        if ($editedRole !== null) {
            $hiddenRoles[] = $editedRole->getRoleIdentifier();
        }
        $options = [];
        foreach ($this->policyService->getRoles(true) as $role) {
            \assert($role instanceof Role);
            if (in_array($role->getIdentifier(), $hiddenRoles, true)) {
                continue;
            }
            $options[] = new RoleOption($role->getIdentifier(), $role->getIdentifier());
        }
        usort($options, static fn (RoleOption $a, RoleOption $b): int => strcmp($a->identifier, $b->identifier));

        return $options;
    }

    /**
     * @return list<WorkspaceOption>
     */
    private function workspaceOptions(ContentRepository $contentRepository): array
    {
        $options = [];
        foreach ($contentRepository->findWorkspaces() as $workspace) {
            $metadata = $this->workspaceService->getWorkspaceMetadata($contentRepository->id, $workspace->workspaceName);
            if ($metadata->classification !== WorkspaceClassification::SHARED) {
                continue;
            }
            $options[] = new WorkspaceOption($workspace->workspaceName->value, $metadata->title->value);
        }
        usort($options, static fn (WorkspaceOption $a, WorkspaceOption $b): int => strcmp($a->title, $b->title));

        return $options;
    }

    /**
     * @return list<DimensionSpacePointOption>
     */
    private function dimensionSpacePointOptions(ContentRepository $contentRepository): array
    {
        if ($contentRepository->getContentDimensionSource()->getContentDimensionsOrderedByPriority() === []) {
            return [];
        }
        $options = [];
        foreach ($contentRepository->getVariationGraph()->getDimensionSpacePoints() as $dimensionSpacePoint) {
            $options[] = new DimensionSpacePointOption($dimensionSpacePoint->hash, $this->dimensionSpacePointLabel($contentRepository, $dimensionSpacePoint));
        }

        return $options;
    }

    private function dimensionSpacePointLabel(ContentRepository $contentRepository, DimensionSpacePoint $dimensionSpacePoint): string
    {
        $dimensionSource = $contentRepository->getContentDimensionSource();
        $parts = [];
        foreach ($dimensionSpacePoint->coordinates as $dimensionId => $value) {
            $dimension = $dimensionSource->getDimension(new ContentDimensionId($dimensionId));
            $dimensionLabel = $dimension?->getConfigurationValue('label');
            $valueLabel = $dimension?->getValue($value)?->getConfigurationValue('label');
            $parts[] = sprintf(
                '%s: %s',
                is_string($dimensionLabel) && $dimensionLabel !== '' ? $dimensionLabel : $dimensionId,
                is_string($valueLabel) && $valueLabel !== '' ? $valueLabel : $value,
            );
        }

        return implode(', ', $parts);
    }
}
