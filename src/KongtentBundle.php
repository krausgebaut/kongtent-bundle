<?php

/*
 * This file is part of the kongtent bundle.
 *
 * (c) krausgebaut von Marcel Kraus <mail@krausgebaut.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Krausgebaut\KongtentBundle;

use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

/**
 * `http_client` and `cache` name services, so a test binds its own.
 */
final class KongtentBundle extends AbstractBundle
{
    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->stringNode('cache')->defaultValue('cache.app')->cannotBeEmpty()->end()
                ->stringNode('http_client')->defaultValue('http_client')->cannotBeEmpty()->end()
                ->stringNode('key')->isRequired()->cannotBeEmpty()->end()
                ->stringNode('url')->isRequired()->cannotBeEmpty()->end()
            ->end();
    }

    /**
     * @param array{cache: string, http_client: string, key: string, url: string} $config
     */
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $services = $container->services();

        $services->set(Markdown::class);

        $services->set(Reader::class)
            ->args([service(Markdown::class), service('logger')]);

        $services->set(Client::class)
            ->args([
                service($config['http_client']),
                service(Reader::class),
                service($config['cache']),
                service('logger'),
                $config['url'],
                $config['key'],
            ]);
    }
}
