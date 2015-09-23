<?php

namespace Vivait\DelayedEventBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * This is the class that validates and merges configuration from your app/config files
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/extension.html#cookbook-bundles-extension-config-class}
 */
class Configuration implements ConfigurationInterface
{
    /**
     * {@inheritDoc}
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('vivait_delayed_event');

        $rootNode
            ->children()
                ->arrayNode('storage')
                    //->defaultValue('sqlite')
                    ->children()
                        ->scalarNode('type')->defaultNull()->end()
                        ->append($this->addBasicProviderNode('apc'))
                        ->append($this->addBasicProviderNode('array'))
                        ->append($this->addBasicProviderNode('void'))
                        ->append($this->addBasicProviderNode('wincache'))
                        ->append($this->addBasicProviderNode('xcache'))
                        ->append($this->addBasicProviderNode('zenddata'))
                        ->append($this->addCustomProviderNode())
                        ->append($this->addCouchbaseNode())
                        ->append($this->addChainNode())
                        ->append($this->addMemcachedNode())
                        ->append($this->addMemcacheNode())
                        ->append($this->addFileSystemNode())
                        ->append($this->addPhpFileNode())
                        ->append($this->addMongoNode())
                        ->append($this->addRedisNode())
                        ->append($this->addRiakNode())
                        ->append($this->addSqlite3Node())
                    ->end()
//                    ->validate()
//                    ->ifTrue(function($value) { return !$this->container->hasDefinition('vivait_delayed_event.queue.'. $value); })
//                        ->thenInvalid('Invalid queue transport "%s"')
//                    ->end()
                ->end()
//                ->scalarNode('storage')
//                    ->validate()
//                    ->ifTrue(function($value) { return $value == 'array'; })
//                        ->thenInvalid('Array storage provider is not supported')
//                    ->ifTrue(function($value) { return !$this->container->hasDefinition(sprintf('doctrine_cache.providers.%s_cache', $value)); })
//                        ->thenInvalid('Invalid storage "%s"')
//                    ->end()
//                ->end()
            ->end()
        ->end();

        return $treeBuilder;
    }

    /**
     * @param string $name
     *
     * @return \Symfony\Component\Config\Definition\Builder\TreeBuilder
     */
    private function addBasicProviderNode($name)
    {
        $builder = new TreeBuilder();
        $node    = $builder->root($name);
        return $node;
    }
    /**
     * Build custom node configuration definition
     *
     * @return \Symfony\Component\Config\Definition\Builder\TreeBuilder
     */
    private function addCustomProviderNode()
    {
        $builder = new TreeBuilder();
        $node    = $builder->root('custom_provider');
        $node
            ->children()
                ->scalarNode('type')->isRequired()->cannotBeEmpty()->end()
                ->arrayNode('options')
                    ->useAttributeAsKey('name')
                    ->prototype('scalar')->end()
                ->end()
            ->end()
        ;
        return $node;
    }
    /**
     * Build chain node configuration definition
     *
     * @return \Symfony\Component\Config\Definition\Builder\TreeBuilder
     */
    private function addChainNode()
    {
        $builder = new TreeBuilder();
        $node    = $builder->root('chain');
        $node
            ->fixXmlConfig('provider')
            ->children()
                ->arrayNode('providers')
                    ->prototype('scalar')->end()
                ->end()
            ->end()
        ;
        return $node;
    }
    /**
     * Build memcache node configuration definition
     *
     * @return \Symfony\Component\Config\Definition\Builder\TreeBuilder
     */
    private function addMemcacheNode()
    {
        $builder = new TreeBuilder();
        $node    = $builder->root('memcache');
        $host    = '%doctrine_cache.memcache.host%';
        $port    = '%doctrine_cache.memcache.port%';
        $node
            ->addDefaultsIfNotSet()
            ->fixXmlConfig('server')
            ->children()
                ->scalarNode('connection_id')->defaultNull()->end()
                ->arrayNode('servers')
                ->useAttributeAsKey('host')
                    ->prototype('array')
                        ->beforeNormalization()
                            ->ifTrue(function ($v) {
                                return is_scalar($v);
                            })
                            ->then(function ($val) {
                                return array('port' => $val);
                            })
                        ->end()
                        ->children()
                            ->scalarNode('host')->defaultValue($host)->end()
                            ->scalarNode('port')->defaultValue($port)->end()
                        ->end()
                    ->end()
                    ->defaultValue(array($host => array(
                        'host' => $host,
                        'port' => $port
                    )))
                ->end()
            ->end()
        ;
        return $node;
    }
    /**
     * Build memcached node configuration definition
     *
     * @return \Symfony\Component\Config\Definition\Builder\TreeBuilder
     */
    private function addMemcachedNode()
    {
        $builder = new TreeBuilder();
        $node    = $builder->root('memcached');
        $host    = '%doctrine_cache.memcached.host%';
        $port    = '%doctrine_cache.memcached.port%';
        $node
            ->addDefaultsIfNotSet()
            ->fixXmlConfig('server')
            ->children()
                ->scalarNode('connection_id')->defaultNull()->end()
                ->arrayNode('servers')
                ->useAttributeAsKey('host')
                    ->prototype('array')
                        ->beforeNormalization()
                            ->ifTrue(function ($v) {
                                return is_scalar($v);
                            })
                            ->then(function ($val) {
                                return array('port' => $val);
                            })
                        ->end()
                        ->children()
                            ->scalarNode('host')->defaultValue($host)->end()
                            ->scalarNode('port')->defaultValue($port)->end()
                        ->end()
                    ->end()
                    ->defaultValue(array($host => array(
                        'host' => $host,
                        'port' => $port
                    )))
                ->end()
            ->end()
        ;
        return $node;
    }
    /**
     * Build redis node configuration definition
     *
     * @return \Symfony\Component\Config\Definition\Builder\TreeBuilder
     */
    private function addRedisNode()
    {
        $builder = new TreeBuilder();
        $node    = $builder->root('redis');
        $node
            ->addDefaultsIfNotSet()
            ->children()
                ->scalarNode('connection_id')->defaultNull()->end()
                ->scalarNode('host')->defaultValue('%doctrine_cache.redis.host%')->end()
                ->scalarNode('port')->defaultValue('%doctrine_cache.redis.port%')->end()
                ->scalarNode('password')->defaultNull()->end()
                ->scalarNode('database')->defaultNull()->end()
            ->end()
        ;
        return $node;
    }
    /**
     * Build riak node configuration definition
     *
     * @return \Symfony\Component\Config\Definition\Builder\TreeBuilder
     */
    private function addRiakNode()
    {
        $builder = new TreeBuilder();
        $node    = $builder->root('riak');
        $node
            ->addDefaultsIfNotSet()
            ->children()
                ->scalarNode('host')->defaultValue('%doctrine_cache.riak.host%')->end()
                ->scalarNode('port')->defaultValue('%doctrine_cache.riak.port%')->end()
                ->scalarNode('bucket_name')->defaultValue('doctrine_cache')->end()
                ->scalarNode('connection_id')->defaultNull()->end()
                ->scalarNode('bucket_id')->defaultNull()->end()
                ->arrayNode('bucket_property_list')
                    ->children()
                        ->scalarNode('allow_multiple')->defaultNull()->end()
                        ->scalarNode('n_value')->defaultNull()->end()
                    ->end()
                ->end()
            ->end()
        ;
        return $node;
    }
    /**
     * Build couchbase node configuration definition
     *
     * @return \Symfony\Component\Config\Definition\Builder\TreeBuilder
     */
    private function addCouchbaseNode()
    {
        $builder = new TreeBuilder();
        $node    = $builder->root('couchbase');
        $node
            ->addDefaultsIfNotSet()
            ->fixXmlConfig('hostname')
            ->children()
                ->scalarNode('connection_id')->defaultNull()->end()
                ->arrayNode('hostnames')
                    ->prototype('scalar')->end()
                    ->defaultValue(array('%doctrine_cache.couchbase.hostnames%'))
                ->end()
                ->scalarNode('username')->defaultNull()->end()
                ->scalarNode('password')->defaultNull()->end()
                ->scalarNode('bucket_name')->defaultValue('doctrine_cache')->end()
            ->end()
        ;
        return $node;
    }
    /**
     * Build mongodb node configuration definition
     *
     * @return \Symfony\Component\Config\Definition\Builder\TreeBuilder
     */
    private function addMongoNode()
    {
        $builder = new TreeBuilder();
        $node    = $builder->root('mongodb');
        $node
            ->addDefaultsIfNotSet()
            ->children()
                ->scalarNode('connection_id')->defaultNull()->end()
                ->scalarNode('collection_id')->defaultNull()->end()
                ->scalarNode('database_name')->defaultValue('doctrine_cache')->end()
                ->scalarNode('collection_name')->defaultValue('doctrine_cache')->end()
                ->scalarNode('server')->defaultValue('%doctrine_cache.mongodb.server%')->end()
            ->end()
        ;
        return $node;
    }
    /**
     * Build php_file node configuration definition
     *
     * @return \Symfony\Component\Config\Definition\Builder\TreeBuilder
     */
    private function addPhpFileNode()
    {
        $builder = new TreeBuilder();
        $node    = $builder->root('php_file');
        $node
            ->addDefaultsIfNotSet()
            ->children()
                ->scalarNode('directory')->defaultValue('%kernel.cache_dir%/doctrine/cache/phpfile')->end()
                ->scalarNode('extension')->defaultNull()->end()
            ->end()
        ;
        return $node;
    }
    /**
     * Build file_system node configuration definition
     *
     * @return \Symfony\Component\Config\Definition\Builder\TreeBuilder
     */
    private function addFileSystemNode()
    {
        $builder = new TreeBuilder();
        $node    = $builder->root('file_system');
        $node
            ->addDefaultsIfNotSet()
            ->children()
                ->scalarNode('directory')->defaultValue('%kernel.cache_dir%/doctrine/cache/file_system')->end()
                ->scalarNode('extension')->defaultNull()->end()
            ->end()
        ;
        return $node;
    }
    /**
     * Build sqlite3 node configuration definition
     *
     * @return \Symfony\Component\Config\Definition\Builder\TreeBuilder
     */
    private function addSqlite3Node()
    {
        $builder = new TreeBuilder();
        $node    = $builder->root('sqlite3');
        $node
            ->addDefaultsIfNotSet()
            ->children()
                ->scalarNode('connection_id')->defaultNull()->end()
                ->scalarNode('file_name')->defaultNull()->end()
                ->scalarNode('table_name')->defaultNull()->end()
            ->end()
        ;
        return $node;
    }
}
