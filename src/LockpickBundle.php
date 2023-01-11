<?php
declare(strict_types=1);


namespace Neunerlei\LockpickBundle;


use Neunerlei\Lockpick\Override\ClassOverrider;
use Neunerlei\LockpickBundle\DependencyInjection\LockpickExtension;
use Neunerlei\LockpickBundle\DependencyInjection\RemoveOverriddenClassesFromPreloadPass;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class LockpickBundle extends Bundle
{
    public const PARAM_STORAGE_PATH = 'lockpick.classOverrides.storagePath';
    public const PARAM_AUTOLOAD_PATH = 'lockpick.classOverrides.composerAutoloaderPath';
    public const PARAM_OVERRIDE_MAP = 'lockpick.classOverrides.map';

    protected bool $initDone = false;

    /**
     * @inheritDoc
     */
    public function createContainerExtension(): ?ExtensionInterface
    {
        $ext = parent::createContainerExtension();

        if ($ext instanceof LockpickExtension) {
            $ext->setOnBuildRunner(function (ContainerBuilder $container) {
                $parameterBag = $container->getParameterBag();

                $this->runOnBuildAndBoot(
                    $parameterBag->resolveValue($parameterBag->get(static::PARAM_STORAGE_PATH)),
                    $parameterBag->resolveValue($parameterBag->get(static::PARAM_AUTOLOAD_PATH)),
                    $parameterBag->resolveValue($parameterBag->get(static::PARAM_OVERRIDE_MAP))
                );

                ClassOverrider::build();
            });
        }

        return $ext;
    }


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
    public function boot(): void
    {
        parent::boot();

        $storagePath = $this->container->getParameter(static::PARAM_STORAGE_PATH);

        $this->runOnBuildAndBoot(
            $storagePath,
            $this->container->getParameter(static::PARAM_AUTOLOAD_PATH),
            $this->container->getParameter(static::PARAM_OVERRIDE_MAP)
        );
    }

    /**
     * Main boot process of registering our custom autoloader.
     * This has to be done once at build and once at boot time in order to catch all the possible classes.
     *
     * @param string $storagePath
     * @param string $composerAutoloadPath
     * @param array $overrideMap
     * @return void
     */
    protected function runOnBuildAndBoot(
        string $storagePath,
        string $composerAutoloadPath,
        array  $overrideMap
    ): void
    {
        if ($this->initDone) {
            return;
        }

        $this->initDone = true;

        // This happens when the cache is cleared and symfony reloads the container
        // in this case we need to allow overrides to be registered (because the config might have changed)
        $allowOverridesOfLoadedClasses = false;
        if (ClassOverrider::isInitialized()) {
            $allowOverridesOfLoadedClasses = true;
        }

        $this->initializeClassOverrider($storagePath, $composerAutoloadPath);

        // Auto-inject the event dispatcher into the overrider
        if (isset($this->container)) {
            $eventDispatcher = $this->container->get('event_dispatcher', ContainerInterface::NULL_ON_INVALID_REFERENCE);
            if ($eventDispatcher instanceof EventDispatcherInterface) {
                ClassOverrider::setEventDispatcher($eventDispatcher);
            }
        }

        if ($allowOverridesOfLoadedClasses) {
            ClassOverrider::getAutoLoader()->getOverrideList()->setAllowToRegisterLoadedClasses(true);
        }

        $this->applyClassOverrides($overrideMap);

        if ($allowOverridesOfLoadedClasses) {
            ClassOverrider::getAutoLoader()->getOverrideList()->setAllowToRegisterLoadedClasses(false);
        }
    }

    protected function initializeClassOverrider(string $storagePath, string $composerAutoloadPath): void
    {
        ClassOverrider::init(
            ClassOverrider::makeAutoLoaderByStoragePath(
                $storagePath,
                require $composerAutoloadPath
            )
        );
    }

    protected function applyClassOverrides(array $overrideMap): void
    {
        if (empty($overrideMap)) {
            return;
        }

        foreach ($overrideMap as $classToOverride => $classToOverrideWith) {
            ClassOverrider::registerOverride(ltrim($classToOverride, '\\'), ltrim($classToOverrideWith, '\\'));
        }
    }

    protected function rebuildAllClassOverridesIfNotPresent(): void
    {
        $markerFile = 'bundle-rebuild-marker.txt';
        $io = ClassOverrider::getIoDriver();
        if ($io->hasFile($markerFile)) {
            return;
        }

        ClassOverrider::build();
        $io->setFileContent($markerFile, '');
    }

}