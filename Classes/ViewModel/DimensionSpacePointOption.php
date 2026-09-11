<?php

declare(strict_types=1);

namespace Sandstorm\NeosAcl\ViewModel;

use Neos\Flow\Annotations as Flow;

#[Flow\Proxy(false)]
final readonly class DimensionSpacePointOption
{
    /**
     * @param string $value the dimension space point hash as submitted by the form
     */
    public function __construct(
        public string $value,
        public string $label,
    ) {
    }
}
