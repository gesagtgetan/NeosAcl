<?php

declare(strict_types=1);

namespace Sandstorm\NeosAcl\ViewModel;

use Neos\ContentRepository\Core\DimensionSpace\DimensionSpacePoint;
use Neos\Flow\Annotations as Flow;
use Sandstorm\NeosAcl\Domain\Model\DynamicRole;

#[Flow\Proxy(false)]
final readonly class DynamicRoleFormData
{
    /**
     * @param string|null $identifier null for a role that is not persisted yet
     * @param list<string> $parentRoleNames
     * @param list<string> $selectedWorkspaceNames
     * @param list<string> $selectedDimensionSpacePoints dimension space point hashes
     * @param list<string> $selectedNodeAggregateIds
     */
    public function __construct(
        public ?string $identifier,
        public string $name,
        public bool $abstract,
        public array $parentRoleNames,
        public array $selectedWorkspaceNames,
        public array $selectedDimensionSpacePoints,
        public array $selectedNodeAggregateIds,
    ) {
    }

    public static function forNewRole(): self
    {
        return new self(null, '', false, ['Neos.Neos:RestrictedEditor', 'Neos.Neos:LivePublisher'], [], [], []);
    }

    public static function fromDynamicRole(string $identifier, DynamicRole $dynamicRole): self
    {
        $matcher = $dynamicRole->getMatcherConfiguration();

        return new self(
            $identifier,
            $dynamicRole->getName(),
            $dynamicRole->isAbstract(),
            $dynamicRole->getParentRoleNames(),
            $matcher->selectedWorkspaceNameStrings(),
            array_values(array_map(
                static fn (DimensionSpacePoint $dimensionSpacePoint): string => $dimensionSpacePoint->hash,
                iterator_to_array($matcher->selectedDimensionSpacePoints),
            )),
            array_values($matcher->selectedNodeAggregateIds->toStringArray()),
        );
    }
}
