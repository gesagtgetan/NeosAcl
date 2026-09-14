<?php

declare(strict_types=1);

namespace Sandstorm\NeosAcl\Controller\Module;

use Neos\ContentRepository\Core\SharedModel\ContentRepository\ContentRepositoryId;
use Neos\ContentRepositoryRegistry\ContentRepositoryRegistry;
use Neos\Error\Messages\Message;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Mvc\Routing\UriBuilder;
use Neos\Flow\Persistence\PersistenceManagerInterface;
use Neos\Flow\Security\Policy\PolicyService;
use Neos\Fusion\View\FusionView;
use Neos\Neos\Controller\Module\AbstractModuleController;
use Neos\Neos\FrontendRouting\SiteDetection\SiteDetectionResult;
use Sandstorm\NeosAcl\Domain\Model\DynamicRole;
use Sandstorm\NeosAcl\Domain\Model\InvalidDynamicRoleException;
use Sandstorm\NeosAcl\Domain\Model\MatcherConfiguration;
use Sandstorm\NeosAcl\Domain\Repository\DynamicRoleRepository;
use Sandstorm\NeosAcl\Service\DynamicRoleApplier;
use Sandstorm\NeosAcl\Service\DynamicRoleFormOptionsFactory;
use Sandstorm\NeosAcl\Tree\DocumentTreeBuilder;
use Sandstorm\NeosAcl\ViewModel\DynamicRoleFormData;
use Sandstorm\NeosAcl\ViewModel\DynamicRoleListItem;

class DynamicRoleController extends AbstractModuleController
{
    protected $defaultViewObjectName = FusionView::class;

    #[Flow\Inject]
    protected DynamicRoleRepository $dynamicRoleRepository;

    #[Flow\Inject]
    protected DynamicRoleApplier $dynamicRoleApplier;

    #[Flow\Inject]
    protected DynamicRoleFormOptionsFactory $formOptionsFactory;

    #[Flow\Inject]
    protected DocumentTreeBuilder $documentTreeBuilder;

    #[Flow\Inject]
    protected PolicyService $policyService;

    #[Flow\Inject]
    protected PersistenceManagerInterface $persistenceManager;

    #[Flow\Inject]
    protected ContentRepositoryRegistry $contentRepositoryRegistry;

    #[Flow\InjectConfiguration(package: 'Neos.Neos', path: 'userInterface.navigateComponent.nodeTree.loadingDepth')]
    protected int $nodeTreeLoadingDepth = 4;

    public function indexAction(): void
    {
        $listItems = [];
        foreach ($this->dynamicRoleRepository->findAllOrderedByName() as $dynamicRole) {
            $matcher = $dynamicRole->getMatcherConfiguration();
            $contentRepositoryId = $matcher->contentRepositoryId;
            $listItems[] = new DynamicRoleListItem(
                $this->identifierOf($dynamicRole),
                $dynamicRole->getName(),
                $dynamicRole->getRoleIdentifier(),
                $dynamicRole->isAbstract(),
                $dynamicRole->getParentRoleNames(),
                $matcher->selectedWorkspaceNameStrings(),
                $this->formOptionsFactory->dimensionSpacePointLabels($contentRepositoryId, $matcher->selectedDimensionSpacePoints),
                $this->documentTreeBuilder->labels($contentRepositoryId, $matcher->selectedNodeAggregateIds),
            );
        }
        $this->view->assign('dynamicRoles', $listItems);
    }

    public function initializeCreateAction(): void
    {
        $this->requirePost();
        $this->allowAllListArgumentValues();
    }

    public function initializeUpdateAction(): void
    {
        $this->requirePost();
        $this->allowAllListArgumentValues();
    }

    public function initializeRemoveAction(): void
    {
        $this->requirePost();
    }

    public function newAction(): void
    {
        $this->view->assignMultiple([
            'formData' => DynamicRoleFormData::forNewRole(),
            'formOptions' => $this->formOptionsFactory->create($this->contentRepositoryId(), null, $this->nodeTreeLoadingDepth, $this->childrenEndpoint()),
        ]);
    }

    /**
     * @param string $name
     * @param bool $abstract
     * @param array<string> $parentRoleNames
     * @param array<string> $selectedWorkspaces
     * @param array<string> $selectedDimensionSpacePoints
     * @param array<string> $selectedNodes
     */
    public function createAction(string $name, bool $abstract = false, array $parentRoleNames = [], array $selectedWorkspaces = [], array $selectedDimensionSpacePoints = [], array $selectedNodes = []): void
    {
        try {
            $dynamicRole = new DynamicRole(
                $name,
                $abstract,
                $this->existingRoleIdentifiers($parentRoleNames),
                $this->matcherFromSelection($this->contentRepositoryId(), $selectedWorkspaces, $selectedDimensionSpacePoints, $selectedNodes),
            );
            if ($this->dynamicRoleRepository->findOneBySubtreeTag($dynamicRole->getSubtreeTag()->value) !== null) {
                throw InvalidDynamicRoleException::forDuplicateName($name);
            }
        } catch (InvalidDynamicRoleException $exception) {
            $this->addFlashMessage($exception->getMessage(), '', Message::SEVERITY_ERROR);
            $this->redirect('new');
        }

        $this->dynamicRoleRepository->add($dynamicRole);
        $this->persistenceManager->persistAll();
        $this->dynamicRoleApplier->apply($dynamicRole);
        $this->addFlashMessage(sprintf('Created the dynamic role "%s". Editors see the change after their workspace was rebased.', $dynamicRole->getRoleIdentifier()));
        $this->redirect('index');
    }

    public function editAction(DynamicRole $dynamicRole): void
    {
        $this->view->assignMultiple([
            'formData' => DynamicRoleFormData::fromDynamicRole($this->identifierOf($dynamicRole), $dynamicRole),
            'formOptions' => $this->formOptionsFactory->create($dynamicRole->getMatcherConfiguration()->contentRepositoryId, $dynamicRole, $this->nodeTreeLoadingDepth, $this->childrenEndpoint()),
        ]);
    }

    /**
     * @param DynamicRole $dynamicRole
     * @param bool $abstract
     * @param array<string> $parentRoleNames
     * @param array<string> $selectedWorkspaces
     * @param array<string> $selectedDimensionSpacePoints
     * @param array<string> $selectedNodes
     */
    public function updateAction(DynamicRole $dynamicRole, bool $abstract = false, array $parentRoleNames = [], array $selectedWorkspaces = [], array $selectedDimensionSpacePoints = [], array $selectedNodes = []): void
    {
        try {
            $dynamicRole->update(
                $abstract,
                $this->existingRoleIdentifiers($parentRoleNames),
                $this->matcherFromSelection($dynamicRole->getMatcherConfiguration()->contentRepositoryId, $selectedWorkspaces, $selectedDimensionSpacePoints, $selectedNodes),
            );
        } catch (InvalidDynamicRoleException $exception) {
            $this->addFlashMessage($exception->getMessage(), '', Message::SEVERITY_ERROR);
            $this->redirect('edit', null, null, ['dynamicRole' => $dynamicRole]);
        }

        $this->dynamicRoleRepository->update($dynamicRole);
        $this->persistenceManager->persistAll();
        $this->dynamicRoleApplier->apply($dynamicRole);
        $this->addFlashMessage(sprintf('Updated the dynamic role "%s". Editors see the change after their workspace was rebased.', $dynamicRole->getRoleIdentifier()));
        $this->redirect('index');
    }

    public function removeAction(DynamicRole $dynamicRole): void
    {
        $childRoles = $this->dynamicRoleRepository->findChildRoles($dynamicRole);
        if ($childRoles !== []) {
            $this->addFlashMessage(
                InvalidDynamicRoleException::forRoleWithChildren($dynamicRole->getRoleIdentifier(), array_map(static fn (DynamicRole $child): string => $child->getRoleIdentifier(), $childRoles))->getMessage(),
                '',
                Message::SEVERITY_ERROR,
            );
            $this->redirect('index');
        }
        $this->dynamicRoleApplier->revoke($dynamicRole);
        $this->dynamicRoleRepository->remove($dynamicRole);
        $this->addFlashMessage(sprintf('Deleted the dynamic role "%s".', $dynamicRole->getRoleIdentifier()));
        $this->redirect('index');
    }

    /**
     * The trusted properties of the form only cover the checkboxes that were rendered on the
     * server; documents expanded in the browser add more values to the same list.
     */
    private function requirePost(): void
    {
        if ($this->request->getHttpRequest()->getMethod() !== 'POST') {
            $this->throwStatus(405, 'Dynamic roles are changed with POST requests only');
        }
    }

    private function allowAllListArgumentValues(): void
    {
        foreach (['parentRoleNames', 'selectedWorkspaces', 'selectedDimensionSpacePoints', 'selectedNodes'] as $argumentName) {
            $this->arguments->getArgument($argumentName)->getPropertyMappingConfiguration()->allowAllProperties();
        }
    }

    /**
     * @param array<mixed> $submittedRoleIdentifiers
     *
     * @return list<string>
     */
    private function existingRoleIdentifiers(array $submittedRoleIdentifiers): array
    {
        $roleIdentifiers = [];
        foreach ($submittedRoleIdentifiers as $roleIdentifier) {
            if (!is_string($roleIdentifier)) {
                throw InvalidDynamicRoleException::forNonStringSelection('parent role');
            }
            if (!$this->policyService->hasRole($roleIdentifier)) {
                throw InvalidDynamicRoleException::forUnknownParentRole($roleIdentifier);
            }
            $roleIdentifiers[] = $roleIdentifier;
        }

        return $roleIdentifiers;
    }

    /**
     * @param ContentRepositoryId $contentRepositoryId
     * @param array<string> $selectedWorkspaces
     * @param array<string> $selectedDimensionSpacePoints
     * @param array<string> $selectedNodes
     */
    private function matcherFromSelection(ContentRepositoryId $contentRepositoryId, array $selectedWorkspaces, array $selectedDimensionSpacePoints, array $selectedNodes): MatcherConfiguration
    {
        return MatcherConfiguration::fromSubmittedSelection(
            $contentRepositoryId,
            $selectedWorkspaces,
            $selectedDimensionSpacePoints,
            $this->contentRepositoryRegistry->get($contentRepositoryId)->getVariationGraph()->getDimensionSpacePoints(),
            $selectedNodes,
        );
    }

    private function contentRepositoryId(): ContentRepositoryId
    {
        return SiteDetectionResult::fromRequest($this->request->getHttpRequest())->contentRepositoryId;
    }

    private function childrenEndpoint(): string
    {
        $uriBuilder = new UriBuilder();
        $uriBuilder->setRequest($this->request->getMainRequest());
        $uriBuilder->setFormat('json');

        return $uriBuilder->uriFor('children', [], 'NodeTree', 'Sandstorm.NeosAcl');
    }

    private function identifierOf(DynamicRole $dynamicRole): string
    {
        $identifier = $this->persistenceManager->getIdentifierByObject($dynamicRole);
        if (!is_string($identifier)) {
            throw new \RuntimeException('The dynamic role has no persistence identifier', 1757600011);
        }

        return $identifier;
    }
}
