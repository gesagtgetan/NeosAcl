<?php

declare(strict_types=1);

namespace Sandstorm\NeosAcl\Tests\Unit\Policy;

use Neos\Neos\Security\Authorization\Privilege\EditNodePrivilege;
use PHPUnit\Framework\TestCase;
use Sandstorm\NeosAcl\Policy\DynamicPolicyConfiguration;
use Sandstorm\NeosAcl\Policy\DynamicRolePolicyEntry;

final class DynamicPolicyConfigurationTest extends TestCase
{
    public function testBuildsOneRoleAndOneEditNodePrivilegeTargetPerEntry(): void
    {
        $configuration = DynamicPolicyConfiguration::fromEntries([
            new DynamicRolePolicyEntry('Marketing', 'neosacl-marketing', false, ['Neos.Neos:RestrictedEditor', 'Neos.Neos:LivePublisher']),
            new DynamicRolePolicyEntry('Sales', 'neosacl-sales', true, []),
        ]);

        self::assertSame([
            'privilegeTargets' => [
                EditNodePrivilege::class => [
                    'Dynamic:Marketing.EditNodes' => ['matcher' => 'neosacl-marketing'],
                    'Dynamic:Sales.EditNodes' => ['matcher' => 'neosacl-sales'],
                ],
            ],
            'roles' => [
                'Dynamic:Marketing' => [
                    'abstract' => false,
                    'parentRoles' => ['Neos.Neos:RestrictedEditor', 'Neos.Neos:LivePublisher'],
                    'privileges' => [
                        ['privilegeTarget' => 'Dynamic:Marketing.EditNodes', 'permission' => 'GRANT'],
                    ],
                ],
                'Dynamic:Sales' => [
                    'abstract' => true,
                    'parentRoles' => [],
                    'privileges' => [
                        ['privilegeTarget' => 'Dynamic:Sales.EditNodes', 'permission' => 'GRANT'],
                    ],
                ],
            ],
        ], $configuration);
    }

    public function testMergeIntoLeavesThePolicyUntouchedWithoutEntries(): void
    {
        $policy = [
            'privilegeTargets' => [EditNodePrivilege::class => ['Sandstorm.NeosAcl:EditAllNodes' => ['matcher' => 'neosacl-restricted']]],
            'roles' => ['Neos.Neos:Administrator' => ['privileges' => []]],
        ];

        self::assertSame($policy, DynamicPolicyConfiguration::mergeInto($policy, []));
    }

    public function testMergeIntoAddsRolesAndTargetsNextToTheExistingOnes(): void
    {
        $policy = [
            'privilegeTargets' => [EditNodePrivilege::class => ['Sandstorm.NeosAcl:EditAllNodes' => ['matcher' => 'neosacl-restricted']]],
            'roles' => ['Neos.Neos:Administrator' => ['privileges' => []]],
        ];

        $merged = DynamicPolicyConfiguration::mergeInto($policy, [new DynamicRolePolicyEntry('Sales', 'neosacl-sales', false, [])]);

        self::assertSame([
            'privilegeTargets' => [EditNodePrivilege::class => [
                'Sandstorm.NeosAcl:EditAllNodes' => ['matcher' => 'neosacl-restricted'],
                'Dynamic:Sales.EditNodes' => ['matcher' => 'neosacl-sales'],
            ]],
            'roles' => [
                'Neos.Neos:Administrator' => ['privileges' => []],
                'Dynamic:Sales' => [
                    'abstract' => false,
                    'parentRoles' => [],
                    'privileges' => [['privilegeTarget' => 'Dynamic:Sales.EditNodes', 'permission' => 'GRANT']],
                ],
            ],
        ], $merged);
    }

    public function testMergeIntoDropsParentRolesThatDoNotExist(): void
    {
        $policy = ['roles' => ['Neos.Neos:RestrictedEditor' => ['privileges' => []]]];

        $merged = DynamicPolicyConfiguration::mergeInto($policy, [
            new DynamicRolePolicyEntry('Child', 'neosacl-child', false, ['Dynamic:Deleted', 'Neos.Neos:RestrictedEditor', 'Dynamic:Base']),
            new DynamicRolePolicyEntry('Base', 'neosacl-base', true, []),
        ]);

        self::assertSame(
            [
                'Neos.Neos:RestrictedEditor' => ['privileges' => []],
                'Dynamic:Child' => [
                    'abstract' => false,
                    'parentRoles' => ['Neos.Neos:RestrictedEditor', 'Dynamic:Base'],
                    'privileges' => [['privilegeTarget' => 'Dynamic:Child.EditNodes', 'permission' => 'GRANT']],
                ],
                'Dynamic:Base' => [
                    'abstract' => true,
                    'parentRoles' => [],
                    'privileges' => [['privilegeTarget' => 'Dynamic:Base.EditNodes', 'permission' => 'GRANT']],
                ],
            ],
            $merged['roles'],
        );
    }

    public function testBuildsEmptyStructuresWithoutEntries(): void
    {
        self::assertSame(
            ['privilegeTargets' => [EditNodePrivilege::class => []], 'roles' => []],
            DynamicPolicyConfiguration::fromEntries([]),
        );
    }
}
