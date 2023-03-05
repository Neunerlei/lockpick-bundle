<?php
declare(strict_types=1);


namespace Neunerlei\LockpickBundle;

use Neunerlei\LockpickBundle\DependencyInjection\RemoveOverriddenClassesFromPreloadPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class LockpickBundle extends Bundle
{
    /**
     * @inheritDoc
     */
    public function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new RemoveOverriddenClassesFromPreloadPass(), priority: -500);
    }

    /**
     * @inheritDoc
     */
    public function shutdown(): void
    {
        $kernel = $this->container->get('kernel');

        if ($kernel && is_callable([$kernel, 'triggerShutdownForOverrideGenerator'])) {
            $kernel->triggerShutdownForOverrideGenerator();
        }
    }
}