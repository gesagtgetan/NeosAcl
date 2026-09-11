<?php

declare(strict_types=1);

namespace Sandstorm\NeosAcl\ViewModel;

use Neos\Flow\Annotations as Flow;

#[Flow\Proxy(false)]
final readonly class RoleOption
{
    public function __construct(
        public string $identifier,
        public string $label,
    ) {
    }
}
