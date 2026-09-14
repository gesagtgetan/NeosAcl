<?php

declare(strict_types=1);

namespace Sandstorm\NeosAcl\Domain\Model;

final class InvalidDynamicRoleException extends \InvalidArgumentException
{
    public static function forName(string $name): self
    {
        return new self(sprintf('"%s" is not a valid dynamic role name. Use 1 to 28 letters, digits or underscores.', $name), 1757600001);
    }

    public static function forReservedName(string $name): self
    {
        return new self(sprintf('"%s" is reserved for the restriction tag of the site roots and cannot be used as a dynamic role name.', $name), 1757600016);
    }

    public static function forNonStringSelection(string $selectionName): self
    {
        return new self(sprintf('The %s selection must only contain strings.', $selectionName), 1757600002);
    }

    public static function forUnknownParentRole(string $roleIdentifier): self
    {
        return new self(sprintf('The parent role "%s" does not exist.', $roleIdentifier), 1757600003);
    }

    public static function forUnknownDimensionSpacePoint(string $hash): self
    {
        return new self(sprintf('The dimension space point "%s" does not exist.', $hash), 1757600013);
    }

    public static function forDuplicateName(string $name): self
    {
        return new self(sprintf('A dynamic role named "%s" exists already.', $name), 1757600004);
    }
}
