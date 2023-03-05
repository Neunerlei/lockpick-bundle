<?php
declare(strict_types=1);


namespace Neunerlei\LockpickBundle\Overrides;


use Composer\Autoload\ClassLoader;
use Neunerlei\FileSystem\Fs;
use Neunerlei\FileSystem\Path;
use Neunerlei\Lockpick\Override\ClassOverrider;
use Neunerlei\LockpickBundle\Adapter\DelegateClassLoader;
use Neunerlei\LockpickBundle\Exception\InvalidConfigException;
use Symfony\Component\ErrorHandler\DebugClassLoader;

class OverrideGeneratorBinder
{
    protected static bool $initialized = false;

    public function bind(OverrideGeneratorConfig $config): void
    {
        $this->initializeClassOverrider(
            $config->getStoragePath(),
            $this->findComposerAutoloaderPaths($config->getComposerAutoloadPath())
        );

        $this->applyClassOverrides($config->getOverrideMap());

        $this->rebuildAllClassOverridesIfNotPresent();
    }

    public function rebind(): void
    {
        clearstatcache();
        $this->rebuildAllClassOverridesIfNotPresent();
    }

    protected function initializeClassOverrider(string $storagePath, array $composerAutoloadPaths): void
    {
        if (static::$initialized) {
            return;
        }

        static::$initialized = true;

        // If there is just a single classloader use it, otherwise use the delegate class loader
        if (count($composerAutoloadPaths) === 1) {
            $autoloader = require reset($composerAutoloadPaths);
        } else {
            $autoloader = new DelegateClassLoader($composerAutoloadPaths);
        }

        ClassOverrider::init(
            ClassOverrider::makeAutoLoaderByStoragePath(
                $storagePath,
                $autoloader
            )
        );
    }

    protected function applyClassOverrides(array $overrideMap): void
    {
        if (empty($overrideMap)) {
            return;
        }

        foreach ($overrideMap as $args) {
            [$classToOverride, $classToOverrideWith, $overrule] = $args;
            ClassOverrider::registerOverride(ltrim($classToOverride, '\\'), ltrim($classToOverrideWith, '\\'), $overrule);
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