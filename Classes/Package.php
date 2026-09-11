<?php

declare(strict_types=1);

namespace Sandstorm\NeosAcl;

use Neos\Flow\Core\Bootstrap;
use Neos\Flow\Package\Package as BasePackage;
use Neos\Flow\Security\Policy\PolicyService;
use Sandstorm\NeosAcl\Policy\DynamicPolicyGenerator;

class Package extends BasePackage
{
    public function boot(Bootstrap $bootstrap): void
    {
        $bootstrap->getSignalSlotDispatcher()->connect(
            PolicyService::class,
            'configurationLoaded',
            DynamicPolicyGenerator::class,
            'onConfigurationLoaded'
        );
    }
}
