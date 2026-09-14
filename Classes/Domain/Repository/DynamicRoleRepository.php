<?php

declare(strict_types=1);

namespace Sandstorm\NeosAcl\Domain\Repository;

use Neos\Flow\Annotations as Flow;
use Neos\Flow\Persistence\Doctrine\Repository;
use Neos\Flow\Persistence\QueryInterface;
use Sandstorm\NeosAcl\Domain\Model\DynamicRole;

/**
 * @method DynamicRole|null findOneByName(string $name)
 * @method DynamicRole|null findOneBySubtreeTag(string $subtreeTag)
 */
#[Flow\Scope('singleton')]
class DynamicRoleRepository extends Repository
{
    public const ENTITY_CLASSNAME = DynamicRole::class;

    /**
     * @return list<DynamicRole>
     */
    public function findChildRoles(DynamicRole $parent): array
    {
        return array_values(array_filter(
            $this->findAllOrderedByName(),
            static fn (DynamicRole $candidate): bool => in_array($parent->getRoleIdentifier(), $candidate->getParentRoleNames(), true),
        ));
    }

    /**
     * @return list<DynamicRole>
     */
    public function findAllOrderedByName(): array
    {
        $query = $this->createQuery();
        $query->setOrderings(['name' => QueryInterface::ORDER_ASCENDING]);
        $dynamicRoles = [];
        foreach ($query->execute() as $dynamicRole) {
            \assert($dynamicRole instanceof DynamicRole);
            $dynamicRoles[] = $dynamicRole;
        }

        return $dynamicRoles;
    }
}
