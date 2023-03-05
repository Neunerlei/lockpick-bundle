# Lock picks for Symfony

This is a bundle that integrates [neunerlei/lockpick](https://github.com/Neunerlei/lockpick) seamlessly
into your Symfony project.

**A word of caution:** If you use this bundle, please make sure you understand the implications and possible
issues that might arise from its usage if not done properly.

## Installation

Install this package using composer:

```
composer require neunerlei/lockpick-bundle
```

This bundle was tested against Symfony 5.4 and 6.1.

## Configuration

Sadly, because the "class-override" magic digs deep into the system, we can't reliably use the Symfony compliant
way of defining a "Configuration" structure. (E.g. when you would want to override framework classes,
or because some bundles load the classes already before the configuration has been loaded).

Therefore we provide an alternative way of reliably configuring the class overrides in your symfony application.

For the configuration add the `\Neunerlei\LockpickBundle\ClassOverridesKernelTrait` trait to your applications
`Kernel` class; normally located at `App\Kernel`. Now you can override the `boot` method, to register
the required overrides in your application:

```php
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
    
    // Add this trait to your kernel
    use ClassOverridesKernelTrait;

    /**
     * @inheritDoc
     */
    public function boot(): void
    {
        // The configureOverrideGenerator accepts a closure as parameter, which will receive an instance of 
        // {@see OverrideGeneratorConfig} you can use to configure all facets of the override generator...
        $this->configureOverrideGenerator(static function (OverrideGeneratorConfig $config): void {
        
            // You can add your overrides through the speaking configuration interface,
            // take a look at the code documentation if you feel lost.
            $config->registerOverride(UrlHelper::class, NotSoFinalUrlHelper::class);
            
            // You can also chain multiple overrides
            $config
              ->registerOverride(AcmeBundle\ClassToOverride::class, YourBundle\ClassToOverrideWith::class)
              ->registerOverride(AnotherAcmeBundle\AnotherClassToOverride::class, YourBundle\AnotherClassToOverrideWith::class);
              
            // Those options are optional and will be automatically set if omitted:
            
            // The absolute path to your composer autoload.php. If omitted the script tries to find the autoloader itself
            $config->setComposerAutoloadPath('/...');
            
            // The path where the generated class copies should be stored
            $config->setStoragePath('/...')
        });

        // IMPORTANT! Configure the override generator BEFORE calling parent::boot()!
        parent::boot();
    }
}
```

### Tip for overriding other bundles

In order to override files from other bundles, I would strongly advise you to modify the `/config/bundles.php` manually.
Ensure that `Neunerlei\LockpickBundle\LockpickBundle::class` is loaded at the top of the list,
so that the magic can happen before the Container does stuff with the sources.

```php
<?php

return [
    Neunerlei\LockpickBundle\LockpickBundle::class => ['all' => true], // <- The bundle should be loaded here...
    Symfony\Bundle\FrameworkBundle\FrameworkBundle::class => ['all' => true],
];
```

**Help wanted** If you know how to convince Symfony to prepend this bundle before all others,
except of adjusting the `bundles.php` manually, give me a shout please! :)

## Caveats

- Due to the Symfony architecture, it is not possible to easily override most of the framework core classes. You can try
  it, but don't expect that to work without issues.

## Postcardware

You're free to use this package, but if it makes it to your production environment I highly appreciate you sending me a
postcard from your hometown, mentioning
which of our package(s) you are using.

You can find my address [here](https://www.neunerlei.eu/).

Thank you :D 