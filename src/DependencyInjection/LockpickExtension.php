<?php
declare(strict_types=1);


namespace Neunerlei\LockpickBundle\DependencyInjection;


use Composer\Autoload\ClassLoader;
use Neunerlei\FileSystem\Fs;
use Neunerlei\FileSystem\Path;
use Neunerlei\LockpickBundle\Exception\InvalidConfigException;
use Neunerlei\LockpickBundle\LockpickBundle;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\ErrorHandler\DebugClassLoader;

class LockpickExtension extends Extension
{
    protected \Closure $onBuildRunner;

    /**
     * Internal bridge to inject the on boot runner as a temporary implementation
     * to be backward compatible with symfony 5.4.
     *
     * @param \Closure $runner
     * @return void
     * @internal
     */
    public function setOnBuildRunner(\Closure $runner): void
    {
        $this->onBuildRunner = $runner;
    }

    /**
     * @inheritDoc
     */
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/../../config'));
        $loader->load('services.yaml');

        $container->setParameter(LockpickBundle::PARAM_STORAGE_PATH, $config['classOverrides']['storagePath']);
        $container->setParameter(LockpickBundle::PARAM_OVERRIDE_MAP, $config['classOverrides']['map'] ?? []);
        $container->setParameter(LockpickBundle::PARAM_AUTOLOAD_PATHS,
            $this->findComposerAutoloaderPaths($config['classOverrides']['composerAutoloadPath'])
        );

        if (is_callable($this->onBuildRunner ?? null)) {
            ($this->onBuildRunner)($container);
        }
    }

    /**
     * Internal helper to find the path to the composer autoload.php which is used to
     * find the actual autoloader implementation we are using
     *
     * @param string|null $autoloaderPath
     * @return array
     * @throws \ReflectionException
     */
    protected function findComposerAutoloaderPaths(?string $autoloaderPath = null): array
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

            return [$autoloaderPath];
        }

        $paths = [];

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

            if (!is_array($callback)) {
                continue;
            }

            if (!isset($callback[0]) || !$callback[0] instanceof ClassLoader) {
                continue;
            }

            $ref = new \ReflectionProperty($callback[0], 'vendorDir');
            $ref->setAccessible(true);
            $paths[] = Path::join($ref->getValue($callback[0]), 'autoload.php');
        }

        if (!empty($paths)) {
            return array_unique($paths);
        }

        throw new InvalidConfigException(
            'Failed to automatically detect the composer class loader, based on your autoload functions, please specify the "lockpick.classOverrides.composerAutoloadPath" manually in your configuration'
        );
    }

}