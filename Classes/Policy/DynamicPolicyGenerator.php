<?php

declare(strict_types=1);

namespace Sandstorm\NeosAcl\Policy;

use Doctrine\DBAL\Exception\TableNotFoundException;
use Doctrine\ORM\EntityManagerInterface;
use Neos\Flow\Annotations as Flow;

/**
 * Slot for {@see \Neos\Flow\Security\Policy\PolicyService::emitConfigurationLoaded()},
 * connected in {@see \Sandstorm\NeosAcl\Package::boot()}.
 */
#[Flow\Scope('singleton')]
final readonly class DynamicPolicyGenerator
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @param array<mixed> $policyConfiguration
     */
    public function onConfigurationLoaded(array &$policyConfiguration): void
    {
        try {
            $rows = $this->entityManager->getConnection()
                ->executeQuery('SELECT name, subtreetag, abstract, parentrolenames FROM sandstorm_neosacl_domain_model_dynamicrole')
                ->fetchAllAssociative();
        } catch (TableNotFoundException) {
            return;
        }

        $entries = array_map(DynamicRolePolicyEntry::fromRow(...), $rows);
        $policyConfiguration = DynamicPolicyConfiguration::mergeInto($policyConfiguration, $entries);
    }
}
