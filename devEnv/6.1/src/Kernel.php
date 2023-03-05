<?php

namespace App;

use App\Override\NotSoFinalUrlHelper;
use Neunerlei\LockpickBundle\ClassOverridesKernelTrait;
use Neunerlei\LockpickBundle\Overrides\OverrideGeneratorConfig;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\HttpFoundation\UrlHelper;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

class Kernel extends BaseKernel
{
    use MicroKernelTrait;
    use ClassOverridesKernelTrait;

    /**
     * @inheritDoc
     */
    public function boot(): void
    {
        $this->configureOverrideGenerator(static function (OverrideGeneratorConfig $config): void {
            $config->registerOverride(UrlHelper::class, NotSoFinalUrlHelper::class);
        });

        parent::boot();
    }
}
