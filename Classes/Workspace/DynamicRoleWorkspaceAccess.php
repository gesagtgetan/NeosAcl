<?php

declare(strict_types=1);

namespace Sandstorm\NeosAcl\Workspace;

use Neos\ContentRepository\Core\SharedModel\Workspace\WorkspaceName;
use Neos\ContentRepositoryRegistry\ContentRepositoryRegistry;
use Neos\Flow\Annotations as Flow;
use Neos\Neos\Domain\Model\WorkspaceRole;
use Neos\Neos\Domain\Model\WorkspaceRoleAssignment;
use Neos\Neos\Domain\Model\WorkspaceRoleSubject;
use Neos\Neos\Domain\Service\WorkspaceService;
use Sandstorm\NeosAcl\Domain\Model\DynamicRole;

/**
 * Gives the dynamic role a collaborator assignment on each selected workspace and
 * removes the assignment from every other workspace.
 */
#[Flow\Scope('singleton')]
final readonly class DynamicRoleWorkspaceAccess
{
    public function __construct(
        private ContentRepositoryRegistry $contentRepositoryRegistry,
        private WorkspaceService $workspaceService,
    ) {
    }

    public function synchronize(DynamicRole $dynamicRole): void
    {
        $this->assignExactly($dynamicRole, $dynamicRole->getMatcherConfiguration()->selectedWorkspaceNames);
    }

    public function revokeAll(DynamicRole $dynamicRole): void
    {
        $this->assignExactly($dynamicRole, []);
    }

    /**
     * @param list<WorkspaceName> $wantedWorkspaceNames
     */
    private function assignExactly(DynamicRole $dynamicRole, array $wantedWorkspaceNames): void
    {
        $contentRepositoryId = $dynamicRole->getMatcherConfiguration()->contentRepositoryId;
        $contentRepository = $this->contentRepositoryRegistry->get($contentRepositoryId);
        $subject = WorkspaceRoleSubject::createForGroup($dynamicRole->getRoleIdentifier());

        foreach ($contentRepository->findWorkspaces() as $workspace) {
            $wanted = self::containsWorkspaceName($wantedWorkspaceNames, $workspace->workspaceName);
            $assigned = false;
            foreach ($this->workspaceService->getWorkspaceRoleAssignments($contentRepositoryId, $workspace->workspaceName) as $assignment) {
                if ($assignment->subject->equals($subject)) {
                    $assigned = true;
                }
            }
            if ($wanted && !$assigned) {
                $this->workspaceService->assignWorkspaceRole(
                    $contentRepositoryId,
                    $workspace->workspaceName,
                    WorkspaceRoleAssignment::createForGroup($dynamicRole->getRoleIdentifier(), WorkspaceRole::COLLABORATOR),
                );
            }
            if (!$wanted && $assigned) {
                $this->workspaceService->unassignWorkspaceRole($contentRepositoryId, $workspace->workspaceName, $subject);
            }
        }
    }

    /**
     * @param list<WorkspaceName> $workspaceNames
     */
    private static function containsWorkspaceName(array $workspaceNames, WorkspaceName $needle): bool
    {
        foreach ($workspaceNames as $workspaceName) {
            if ($workspaceName->equals($needle)) {
                return true;
            }
        }

        return false;
    }
}
