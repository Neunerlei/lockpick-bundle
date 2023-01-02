<?php
declare(strict_types=1);


namespace Neunerlei\LockpickBundle\Cache;


use Neunerlei\Lockpick\Override\ClassOverrider;
use Symfony\Component\HttpKernel\CacheClearer\CacheClearerInterface;

class LockpickCacheClearer implements CacheClearerInterface
{
    /**
     * @inheritDoc
     */
    public function clear(string $cacheDir)
    {
        ClassOverrider::flushStorage();
    }

}