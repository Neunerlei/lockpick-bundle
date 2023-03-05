<?php
declare(strict_types=1);


namespace Neunerlei\LockpickBundle\Overrides;


class OverrideGeneratorConfig
{
    protected string $storagePath;
    protected string $composerAutoloadPath;
    protected array $map;

    /**
     * Returns the absolute path to the directory where the override generator should put its generated files
     * @return string
     */
    public function getStoragePath(): string
    {
        return $this->storagePath ?? sys_get_temp_dir();
    }

    /**
     * Defines the absolute path to the directory where the override generator should put its generated files
     * @param string $storagePath
     * @return OverrideGeneratorConfig
     */
    public function setStoragePath(string $storagePath): OverrideGeneratorConfig
    {
        $this->storagePath = $storagePath;
        return $this;
    }

    /**
     * Returns the configured (NOT DETECTED) composer autoload path.
     * @return string|null
     */
    public function getComposerAutoloadPath(): ?string
    {
        return $this->composerAutoloadPath ?? null;
    }

    /**
     * Sets the absolute path to your composer autoload.php. If omitted the script tries to find the autoloader itself
     * @param string $composerAutoloadPath
     * @return OverrideGeneratorConfig
     */
    public function setComposerAutoloadPath(string $composerAutoloadPath): OverrideGeneratorConfig
    {
        $this->composerAutoloadPath = $composerAutoloadPath;
        return $this;
    }

    /**
     * Registers a new class override. The override will completely replace the original source class.
     * The overwritten class will be copied and is available in the same namespace but with the
     * "LockpickClassOverride" prefix in front of it's class name. The overwritten class has all its private
     * properties and function changed to protected for easier overrides.
     *
     * This method throws an exception if the class is already overwritten by another class
     *
     * @param string $classToOverride The name of the class to overwrite with the class given in
     *                                $classToOverrideWith
     * @param string $classToOverrideWith The name of the class that should be used instead of the class defined as
     *                                    $classToOverride
     * @param bool $overrule If this is set to true already registered overrides can be changed to a
     *                       different definition
     */
    public function registerOverride(
        string $classToOverride,
        string $classToOverrideWith,
        bool   $overrule = false
    ): OverrideGeneratorConfig
    {
        $this->map[] = [$classToOverride, $classToOverrideWith, $overrule];

        return $this;
    }

    /**
     * Returns the list of overrides that have been configured in {@see registerOverride}
     * @return array
     */
    public function getOverrideMap(): array
    {
        return $this->map ?? [];
    }
}