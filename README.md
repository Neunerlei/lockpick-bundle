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

This bundle was designed against Symfony 6.1 and currently can\'t be used with older versions.

## Configuration

The bundle provides you with a Symfony compliant way of registering class overrides and the storage path.

In your `config/packages/lockpick.yaml` you can configure the following options:

```yaml
lockpick:
  classOverrides:

    # The absolute path to your composer autoload.php. If omitted the script tries to find the autoloader itself
    composerAutoloadPath: null

    # The path where the generated class copies should be stored
    storagePath: '%kernel.cache_dir%/lockpickClassOverrides'

    # A map of classes to override as key, and the list of classes to override them with as values
    map:

      # Examples:
      AcmeBundle\ClassToOverride: YourBundle\ClassToOverrideWith
      AnotherAcmeBundle\AnotherClassToOverride: YourBundle\AnotherClassToOverrideWith
```

### Tip for overriding other bundles

In order to override files from other bundles, I would strongly advise you to modify the `/config/bundles.php` manually.
Ensure that `` is loaded at the top of the list, so that the magic can happen before the Container does stuff with the
sources.

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