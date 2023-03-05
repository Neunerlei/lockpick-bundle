<?php
declare(strict_types=1);


namespace Neunerlei\LockpickBundle;


use Neunerlei\LockpickBundle\Exception\InvalidConfigException;
use Neunerlei\LockpickBundle\Exception\InvalidParentException;
use Neunerlei\LockpickBundle\Overrides\OverrideGeneratorBinder;
use Neunerlei\LockpickBundle\Overrides\OverrideGeneratorConfig;
use Symfony\Component\HttpKernel\Kernel;

trait ClassOverridesKernelTrait
{
    protected OverrideGeneratorBinder $overrideGeneratorBinder;
    protected bool $rebindAfterReboot = false;

    /**
     * Internal method to trigger a rebind after the kernel was rebooted.
     * @return void
     * @internal
     */
    public function triggerShutdownForOverrideGenerator(): void
    {
        $this->rebindAfterReboot = true;
    }

    /**
     * Add this method in the {@see Kernel::boot()} method, BEFORE you call parent::boot()!
     * It allows you to configure the override generation reliably in all situations.
     *
     * NOTE: You can call this method only ONCE in a lifecycle!
     *
     * @param callable $configurator The given callable receives {@see OverrideGeneratorConfig} in order to
     *                               configure the override generator
     * @return void
     * @see OverrideGeneratorConfig for more information about what you can configure.
     */
    protected function configureOverrideGenerator(callable $configurator): void
    {
        $this->assertRunningFromWithinKernel();
        /** @var Kernel $this */

        if (isset($this->overrideGeneratorBinder)) {

            // Handle reboot
            if ($this->rebindAfterReboot) {
                $this->rebindAfterReboot = false;
                sleep(5);
                $this->overrideGeneratorBinder->rebind();
                return;
            }

            // Failsafe if the boot method would be called multiple times
            if (true === $this->booted) {
                return;
            }

            throw new InvalidConfigException('You already configured the override generator, you can\'t reconfigure it!');
        }

        $this->overrideGeneratorBinder = new OverrideGeneratorBinder();

        $config = new OverrideGeneratorConfig();
        $config->setStoragePath($this->getCacheDir() . '/lockpickClassOverrides');

        $configurator($config);

        $this->overrideGeneratorBinder->bind($config);
    }

    /**
     * Check if it is executed from within a kernel instance, or throws an exception
     * @return void
     */
    protected function assertRunningFromWithinKernel(): void
    {
        if ($this instanceof Kernel) {
            return;
        }

        throw new InvalidParentException(
            sprintf(
                'The "ClassOverridesKernelTrait" MUST be executed from within a "%s" kernel',
                Kernel::class
            ));
    }
}