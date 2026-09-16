<?php

/*
 * This file is part of the kongtent bundle.
 *
 * (c) krausgebaut von Marcel Kraus <mail@krausgebaut.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Krausgebaut\KongtentBundle\Tests;

use Krausgebaut\KongtentBundle\Client;
use Krausgebaut\KongtentBundle\KongtentBundle;
use Krausgebaut\KongtentBundle\Tests\App\Kernel;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Filesystem\Filesystem;
use Twig\Environment;

final class KongtentBundleTest extends KernelTestCase
{
    protected static function getKernelClass(): string
    {
        return Kernel::class;
    }

    public static function setUpBeforeClass(): void
    {
        (new Filesystem())->remove(\dirname(__DIR__).'/var/cache');
    }

    public function testTheClientReadsWhatTheSiteConfigured(): void
    {
        self::bootKernel();

        $client = self::getContainer()->get('test.kongtent.client');

        self::assertCount(2, $client->all());
        self::assertNotNull($client->one('first-article'));
    }

    public function testTheDefaultServicesWireTheClient(): void
    {
        self::bootKernel(['environment' => 'defaults']);

        self::assertInstanceOf(Client::class, self::getContainer()->get('test.kongtent.client'));
    }

    public function testTheAddressAndTheKeyAreRequired(): void
    {
        foreach (['key', 'url'] as $missing) {
            $config = ['key' => 'recorded', 'url' => 'https://kongtent.example'];
            unset($config[$missing]);

            $extension = (new KongtentBundle())->getContainerExtension();

            try {
                $configuration = $extension->getConfiguration([], new ContainerBuilder());
                (new Processor())->processConfiguration($configuration, [$config]);
                self::fail(\sprintf('A configuration without "%s" was accepted.', $missing));
            } catch (InvalidConfigurationException) {
                self::addToAssertionCount(1);
            }
        }
    }

    public function testEveryKindOfBlockRenders(): void
    {
        self::bootKernel();

        $content = self::getContainer()->get('test.kongtent.client')->one('every-block');
        $html = self::getContainer()->get(Environment::class)
            ->render('@Kongtent/blocks.html.twig', ['blocks' => $content->blocks]);

        self::assertCount(8, $content->blocks);

        $expected = [
            '<p>A paragraph',
            '<h2>A heading</h2>',
            '<ul><li>First item</li>',
            '<blockquote class="overridden">',
            '</blockquote><figure class="overridden"><img',
            '<ul><li><figure class="overridden"><img',
            '<dt>Format</dt>',
            '<a href="https://www.youtube.com/watch?v=abcdefghijk">',
        ];

        foreach ($expected as $one) {
            self::assertStringContainsString($one, preg_replace('/>\s+</', '><', $html) ?? '');
        }

        self::assertStringNotContainsString('<iframe', $html);
    }

    public function testTheBlocksRenderAndASiteOverridesOne(): void
    {
        self::bootKernel();

        $content = self::getContainer()->get('test.kongtent.client')->one('first-article');
        $html = self::getContainer()->get(Environment::class)
            ->render('@Kongtent/blocks.html.twig', ['blocks' => $content->blocks]);

        self::assertStringContainsString('<p>', $html);
        self::assertStringContainsString('<blockquote class="overridden">', $html);
    }
}
