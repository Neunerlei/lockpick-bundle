<?php
declare(strict_types=1);


namespace Neunerlei\LockpickBundle\DependencyInjection;


use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    /**
     * @inheritDoc
     */
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('lockpick');

        $treeBuilder->getRootNode()
            ->children()
            /* *** */ ->arrayNode('classOverrides')->addDefaultsIfNotSet()
            /* ****** */ ->children()
            /* ********* */ ->scalarNode('composerAutoloadPath')
            /* ************ */ ->defaultNull()
            /* ************ */ ->info('The absolute path to your composer autoload.php. If omitted the script tries to find the autoloader itself')
            /* ************ */ ->end()
            /* ********* */ ?->scalarNode('storagePath')
            /* ************ */ ->defaultValue('%kernel.cache_dir%/lockpickClassOverrides')
            /* ************ */ ->info('The path where the generated class copies should be stored')
            /* ************ */ ->end()
            /* ********* */ ?->arrayNode('map')
            /* ************ */ ->example([
                'AcmeBundle\\ClassToOverride' => 'YourBundle\\ClassToOverrideWith',
                'AnotherAcmeBundle\\AnotherClassToOverride' => 'YourBundle\\AnotherClassToOverrideWith'
            ])
            /* ************ */ ->info('A map of classes to override as key, and the list of classes to override them with as values')
            /* ************ */ ->scalarPrototype()->end();

        return $treeBuilder;
    }

}