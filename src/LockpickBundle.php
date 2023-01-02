<?php
declare(strict_types=1);


namespace Neunerlei\LockpickBundle;


use Composer\Autoload\ClassLoader;
use Neunerlei\FileSystem\Fs;
use Neunerlei\FileSystem\Path;
use Neunerlei\Lockpick\Override\ClassOverrider;
use Neunerlei\LockpickBundle\DependencyInjection\RemoveOverriddenClassesFromPreloadPass;
use Neunerlei\LockpickBundle\Exception\InvalidConfigException;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\ErrorHandler\DebugClassLoader;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

class LockpickBundle extends AbstractBundle
{
    public const PARAM_STORAGE_PATH = 'lockpick.classOverrides.storagePath';
    public const PARAM_AUTOLOAD_PATH = 'lockpick.classOverrides.composerAutoloaderPath';
    public const PARAM_OVERRIDE_MAP = 'lockpick.classOverrides.map';

    protected bool $initDone = false;

    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
            /* *** */ ->arrayNode('classOverrides')->addDefaultsIfNotSet()
            /* ****** */ ->children()
            /* ********* */ ->scalarNode('composerAutoloadPath')
            /* ************ */ ->defaultNull()
            /* ************ */ ->info('The absolute path to your composer autoload.php. If omitted the script tries to find the autoloader itself')
            /* ************ */ ->end()
            /* ********* */ ?->scalarNode('storagePath')
            /* ************ */ ->defaultValue('%kernel.cache_dir%/lockpickClassOverrides')
            /* ************ */ ->info('The path where the generated class copies should be stored')
            /* ************ */ ->end()
            /* ********* */ ?->arrayNode('map')
            /* ************ */ ->example([
                'AcmeBundle\\ClassToOverride' => 'YourBundle\\ClassToOverrideWith',
                'AnotherAcmeBundle\\AnotherClassToOverride' => 'YourBundle\\AnotherClassToOverrideWith'
            ])
            /* ************ */ ->info('A map of classes to override as key, and the list of classes to override them with as values')
            /* ************ */ ->scalarPrototype()->end();
    }

    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $container->import('../config/services.yaml');

        $builder->setParameter(static::PARAM_STORAGE_PATH, $config['classOverrides']['storagePath']);
        $builder->setParameter(static::PARAM_OVERRIDE_MAP, $config['classOverrides']['map'] ?? []);
        $builder->setParameter(static::PARAM_AUTOLOAD_PATH,
            $this->findComposerAutoloaderPath($config['classOverrides']['composerAutoloadPath']));

        $parameterBag = $builder->getParameterBag();

        $this->runOnBuildAndBoot(
            $parameterBag->resolveValue($parameterBag->get(static::PARAM_STORAGE_PATH)),
            $parameterBag->resolveValue($parameterBag->get(static::PARAM_AUTOLOAD_PATH)),
            $parameterBag->resolveValue($parameterBag->get(static::PARAM_OVERRIDE_MAP))
        );
    }

    /**
     * @inheritDoc
     */
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $container->addCompilerPass(new RemoveOverriddenClassesFromPreloadPass(), priority: -500);
    }

    /**
     * @inheritDoc
     */
    public function boot(): void
    {
        parent::boot();

        $this->runOnBuildAndBoot(
            $this->container->getParameter(static::PARAM_STORAGE_PATH),
            $this->container->getParameter(static::PARAM_AUTOLOAD_PATH),
            $this->container->getParameter(static::PARAM_OVERRIDE_MAP)
        );

        // Auto-inject the event dispatcher into the overrider
        $eventDispatcher = $this->container->get('event_dispatcher',
            ContainerInterface::NULL_ON_INVALID_REFERENCE);
        if ($eventDispatcher instanceof EventDispatcherInterface) {
            ClassOverrider::setEventDispatcher($eventDispatcher);
        }
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

    /**
     * Internal helper to find the path to the composer autoload.php which is used to
     * find the actual autoloader implementation we are using
     *
     * @param string|null $autoloaderPath
     * @return string
     */
    protected function findComposerAutoloaderPath(?string $autoloaderPath = null): string
    {
        // If the path was configured we can go the easy route
        if (!empty($autoloaderPath)) {
            if (!Fs::exists($autoloaderPath) || !is_file($autoloaderPath)) {
                throw new InvalidConfigException(
                    sprintf(
                        'The file "%s" you configured as autoload path, does not exist',
                        $autoloaderPath
                    )
                );
            }

            $loader = require $autoloaderPath;

            if (!$loader instanceof ClassLoader) {
                throw new InvalidConfigException(
                    sprintf(
                        'The file "%s" you configured as autoload path, did not return a composer class loader instance',
                        $autoloaderPath
                    )
                );
            }

            return $autoloaderPath;
        }

        // Let's try to find it...
        foreach (spl_autoload_functions() as $callback) {
            if (!is_array($callback)) {
                continue;
            }

            if (!isset($callback[0])) {
                continue;
            }

            if ($callback[0] instanceof DebugClassLoader) {
                $callback = $callback[0]->getClassLoader();
            }

            if (!isset($callback[0]) || !$callback[0] instanceof ClassLoader) {
                continue;
            }

            $classLoaderFile = (new \ReflectionObject($callback[0]))->getFileName();
            return Path::join($classLoaderFile, '../../', 'autoload.php');
        }

        throw new InvalidConfigException(
            'Failed to automatically detect the composer class loader, based on your autoload functions, please specify the "lockpick.classOverrides.composerAutoloadPath" manually in your configuration'
        );
    }

}