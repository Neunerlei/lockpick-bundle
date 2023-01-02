<?php
declare(strict_types=1);


namespace App\Override;


use Symfony\Component\HttpFoundation\LockpickClassOverrideUrlHelper;

class NotSoFinalUrlHelper extends LockpickClassOverrideUrlHelper
{
    public function getRelativePath(string $path): string
    {
        return 'I hacked you ;)';
    }
}