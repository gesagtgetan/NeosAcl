<?php

declare(strict_types=1);

namespace Sandstorm\NeosAcl\Domain\Model;

use Doctrine\ORM\Mapping as ORM;
use Neos\ContentRepository\Core\Feature\SubtreeTagging\Dto\SubtreeTag;
use Neos\Flow\Annotations as Flow;

/**
 * A role that is added to the Flow policy at runtime. Its name is immutable because
 * the role identifier and the subtree tag derive from it.
 */
#[Flow\Entity]
class DynamicRole
{
    public const ROLE_IDENTIFIER_PREFIX = 'Dynamic:';

    private const NAME_PATTERN = '/^[a-zA-Z0-9_]{1,28}$/';

    protected string $name;

    #[ORM\Column(length: 36)]
    protected string $subtreeTag;

    protected bool $abstract;

    /**
     * @phpstan-var list<string>
     */
    #[ORM\Column(type: 'flow_json_array')]
    protected array $parentRoleNames;

    /**
     * @phpstan-var array<string, mixed>
     */
    #[ORM\Column(type: 'flow_json_array')]
    protected array $matcher;

    /**
     * @param list<string> $parentRoleNames
     */
    public function __construct(string $name, bool $abstract, array $parentRoleNames, MatcherConfiguration $matcher)
    {
        if (preg_match(self::NAME_PATTERN, $name) !== 1) {
            throw InvalidDynamicRoleException::forName($name);
        }
        $this->name = $name;
        $this->subtreeTag = NeosAclSubtreeTag::forDynamicRoleName($name)->value;
        $this->abstract = $abstract;
        $this->parentRoleNames = $parentRoleNames;
        $this->matcher = $matcher->toArray();
    }

    /**
     * @param list<string> $parentRoleNames
     */
    public function update(bool $abstract, array $parentRoleNames, MatcherConfiguration $matcher): void
    {
        $this->abstract = $abstract;
        $this->parentRoleNames = $parentRoleNames;
        $this->matcher = $matcher->toArray();
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getRoleIdentifier(): string
    {
        return self::ROLE_IDENTIFIER_PREFIX . $this->name;
    }

    public function getSubtreeTag(): SubtreeTag
    {
        return SubtreeTag::fromString($this->subtreeTag);
    }

    public function isAbstract(): bool
    {
        return $this->abstract;
    }

    /**
     * @return list<string>
     */
    public function getParentRoleNames(): array
    {
        return $this->parentRoleNames;
    }

    public function getMatcherConfiguration(): MatcherConfiguration
    {
        return MatcherConfiguration::fromArray($this->matcher);
    }
}
