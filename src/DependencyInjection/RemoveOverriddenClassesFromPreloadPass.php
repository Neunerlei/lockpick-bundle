<?php
declare(strict_types=1);


namespace Neunerlei\LockpickBundle\DependencyInjection;


use Neunerlei\Lockpick\Override\ClassOverrider;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class RemoveOverriddenClassesFromPreloadPass implements CompilerPassInterface
{
    /**
     * @inheritDoc
     */
    public function process(ContainerBuilder $container): void
    {
        if (!ClassOverrider::isInitialized()) {
            return;
        }

        $autoLoader = ClassOverrider::getAutoLoader();
        foreach (ClassOverrider::getNotPreloadableClasses() as $classToModify) {
            $className = ltrim($classToModify, '\\');
            $autoLoader->loadClass($className);

            if (!$container->hasDefinition($className)) {
                continue;
            }

            $container->getDefinition($className)->addTag('container.no_preload');
        }
    }

}