<?php

/*
 * This file is part of the kongtent bundle.
 *
 * (c) krausgebaut von Marcel Kraus <mail@krausgebaut.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Krausgebaut\KongtentBundle\Tests\App;

use Krausgebaut\KongtentBundle\Client;
use Krausgebaut\KongtentBundle\KongtentBundle;
use Krausgebaut\KongtentBundle\Test\RecordedClient;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

final class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    public function registerBundles(): iterable
    {
        return [new FrameworkBundle(), new KongtentBundle(), new TwigBundle()];
    }

    public function getProjectDir(): string
    {
        return __DIR__;
    }

    public function getCacheDir(): string
    {
        return \dirname(__DIR__, 2).'/var/cache/'.$this->environment;
    }

    public function getLogDir(): string
    {
        return \dirname(__DIR__, 2).'/var/log';
    }

    private function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', ['secret' => 'test', 'test' => true]);
        $container->extension('twig', ['default_path' => __DIR__.'/templates']);
        $services = $container->services();
        // Nothing here injects the client, so the compiler would remove it.
        $services->alias('test.kongtent.client', Client::class)->public();

        // `defaults` boots with nothing but what a site has to set.
        if ('defaults' === $this->environment) {
            $container->extension('kongtent', ['key' => 'recorded', 'url' => 'https://kongtent.example']);

            return;
        }

        $container->extension('kongtent', [
            'cache' => 'kongtent.recorded_cache',
            'http_client' => 'kongtent.recorded_client',
            'key' => 'recorded',
            'url' => 'https://kongtent.example',
        ]);
        $services->set('kongtent.recorded_cache', ArrayAdapter::class);
        $services->set('kongtent.recorded_client', MockHttpClient::class)
            ->factory([RecordedClient::class, 'fromDirectory'])
            ->args([\dirname(__DIR__).'/fixtures']);
    }
}
