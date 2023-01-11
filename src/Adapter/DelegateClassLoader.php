<?php
declare(strict_types=1);


namespace Neunerlei\LockpickBundle\Adapter;


use Composer\Autoload\ClassLoader;

class DelegateClassLoader extends ClassLoader
{
    /**
     * The list of concrete class loaders to delegate the request to
     * @var ClassLoader[]
     */
    protected array $autoLoaders;

    /** @noinspection PhpMissingParentConstructorInspection */
    public function __construct(array $autoloaderPaths)
    {
        foreach ($autoloaderPaths as $path) {
            $this->autoLoaders[] = require $path;
        }
    }

    /**
     * Returns the list of all wrapped class loader instances
     * @return array|ClassLoader[]
     */
    public function getWrappedClassLoaders(): array
    {
        return $this->autoLoaders;
    }

    public function findFile($class)
    {
        foreach ($this->autoLoaders as $autoLoader) {
            $file = $autoLoader->findFile($class);

            if ($file === false) {
                continue;
            }

            return $file;
        }

        return false;
    }
}