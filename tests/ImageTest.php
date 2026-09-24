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

use Krausgebaut\KongtentBundle\Block\ImageBlock;
use Krausgebaut\KongtentBundle\Picture;
use PHPUnit\Framework\TestCase;

final class ImageTest extends TestCase
{
    public function testTheAlternativeTextCannotCarryMarkup(): void
    {
        $html = Templates::twig()->render('@Kongtent/blocks/image.html.twig', [
            'block' => new ImageBlock(new Picture(
                '"><script>alt</script>',
                null,
                null,
                [['url' => 'https://k.example/a/large.jpg', 'width' => 800, 'height' => 600]],
            )),
        ]);

        self::assertStringNotContainsString('<script>', $html);
        self::assertStringContainsString('&quot;&gt;&lt;script&gt;alt', $html);
    }

    public function testCaptionAndCreditArePrintedAsTheConverterLeftThem(): void
    {
        $html = Templates::twig()->render('@Kongtent/blocks/image.html.twig', [
            'block' => new ImageBlock(new Picture(
                'alt',
                'A <a href="https://example.com">caption</a>',
                '<em>credit</em>',
                [['url' => 'https://k.example/a/large.jpg', 'width' => 800, 'height' => 600]],
            )),
        ]);

        self::assertStringContainsString('A <a href="https://example.com">caption</a>', $html);
        self::assertStringContainsString('<small><em>credit</em></small>', $html);
    }
}
