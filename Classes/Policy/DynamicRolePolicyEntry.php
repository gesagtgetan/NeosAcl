<?php

declare(strict_types=1);

namespace Sandstorm\NeosAcl\Policy;

use Neos\Flow\Annotations as Flow;

/**
 * The part of a persisted dynamic role that the policy needs, read straight from the
 * database row because the policy is loaded before the ORM is usable.
 */
#[Flow\Proxy(false)]
final readonly class DynamicRolePolicyEntry
{
    /**
     * @param list<string> $parentRoleNames
     */
    public function __construct(
        public string $name,
        public string $subtreeTag,
        public bool $abstract,
        public array $parentRoleNames,
    ) {
    }

    /**
     * @param array<string, mixed> $row columns name, subtreetag, abstract, parentrolenames
     */
    public static function fromRow(array $row): self
    {
        $name = $row['name'] ?? null;
        $subtreeTag = $row['subtreetag'] ?? null;
        $abstract = $row['abstract'] ?? null;
        $parentRoleNames = $row['parentrolenames'] ?? null;
        if (!is_string($name) || !is_string($subtreeTag) || !is_scalar($abstract) || !is_string($parentRoleNames)) {
            throw new \RuntimeException('Unexpected dynamic role row: ' . json_encode($row, JSON_THROW_ON_ERROR), 1757600007);
        }
        $decodedParentRoleNames = json_decode($parentRoleNames, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($decodedParentRoleNames)) {
            throw new \RuntimeException(sprintf('parentrolenames of dynamic role "%s" is not a JSON array', $name), 1757600008);
        }
        $parentRoleNameStrings = [];
        foreach ($decodedParentRoleNames as $parentRoleName) {
            if (!is_string($parentRoleName)) {
                throw new \RuntimeException(sprintf('parentrolenames of dynamic role "%s" must only contain strings', $name), 1757600009);
            }
            $parentRoleNameStrings[] = $parentRoleName;
        }

        return new self($name, $subtreeTag, (bool) $abstract, $parentRoleNameStrings);
    }
}
