<?php

declare(strict_types=1);

namespace Sandstorm\NeosAcl\Policy;

use Neos\Flow\Annotations as Flow;
use Neos\Neos\Security\Authorization\Privilege\EditNodePrivilege;
use Neos\Utility\Arrays;
use Sandstorm\NeosAcl\Domain\Model\DynamicRole;

/**
 * Turns persisted dynamic roles into the Policy.yaml structure Flow expects:
 * one role `Dynamic:<name>` and one EditNodePrivilege target `Dynamic:<name>.EditNodes`
 * whose matcher is the role's subtree tag.
 */
#[Flow\Proxy(false)]
final class DynamicPolicyConfiguration
{
    private const EDIT_NODES_TARGET_SUFFIX = '.EditNodes';

    /**
     * Adds the dynamic roles and privilege targets to the loaded policy. Nothing is
     * merged for an empty list, so the static policy stays untouched.
     *
     * @param array<mixed> $policyConfiguration
     * @param list<DynamicRolePolicyEntry> $entries
     *
     * @return array<mixed>
     */
    public static function mergeInto(array $policyConfiguration, array $entries): array
    {
        if ($entries === []) {
            return $policyConfiguration;
        }

        return Arrays::arrayMergeRecursiveOverrule($policyConfiguration, self::fromEntries($entries), false, false);
    }

    /**
     * @param list<DynamicRolePolicyEntry> $entries
     *
     * @return array{privilegeTargets: array<string, array<string, array{matcher: string}>>, roles: array<string, array{abstract: bool, parentRoles: list<string>, privileges: list<array{privilegeTarget: string, permission: string}>}>}
     */
    public static function fromEntries(array $entries): array
    {
        $privilegeTargets = [];
        $roles = [];
        foreach ($entries as $entry) {
            $roleIdentifier = DynamicRole::ROLE_IDENTIFIER_PREFIX . $entry->name;
            $privilegeTargetIdentifier = $roleIdentifier . self::EDIT_NODES_TARGET_SUFFIX;
            $privilegeTargets[$privilegeTargetIdentifier] = ['matcher' => $entry->subtreeTag];
            $roles[$roleIdentifier] = [
                'abstract' => $entry->abstract,
                'parentRoles' => $entry->parentRoleNames,
                'privileges' => [self::grant($privilegeTargetIdentifier)],
            ];
        }

        return [
            'privilegeTargets' => [EditNodePrivilege::class => $privilegeTargets],
            'roles' => $roles,
        ];
    }

    /**
     * @return array{privilegeTarget: string, permission: string}
     */
    private static function grant(string $privilegeTarget): array
    {
        return ['privilegeTarget' => $privilegeTarget, 'permission' => 'GRANT'];
    }
}
