<?php

declare(strict_types=1);

namespace Sandstorm\NeosAcl\Domain\Model;

use Neos\ContentRepository\Core\Feature\SubtreeTagging\Dto\SubtreeTag;
use Neos\Flow\Annotations as Flow;

/**
 * The subtree tags this package writes into the content repository.
 *
 * Every site root carries the restriction tag, which the privilege target
 * `Sandstorm.NeosAcl:EditAllNodes` in Policy.yaml matches. Each dynamic role
 * owns one tag that is set on the node aggregates the role may edit.
 */
#[Flow\Proxy(false)]
final class NeosAclSubtreeTag
{
    private const PREFIX = 'neosacl-';

    public static function restricted(): SubtreeTag
    {
        return SubtreeTag::fromString(self::PREFIX . 'restricted');
    }

    public static function forDynamicRoleName(string $dynamicRoleName): SubtreeTag
    {
        return SubtreeTag::fromString(self::PREFIX . strtolower($dynamicRoleName));
    }
}
