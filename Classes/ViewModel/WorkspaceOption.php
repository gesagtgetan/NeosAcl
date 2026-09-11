<?php

declare(strict_types=1);

namespace Sandstorm\NeosAcl\ViewModel;

use Neos\Flow\Annotations as Flow;

#[Flow\Proxy(false)]
final readonly class WorkspaceOption
{
    public function __construct(
        public string $name,
        public string $title,
    ) {
    }
}
