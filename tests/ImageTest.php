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
    public function testTheTextsOfAPictureCannotCarryMarkup(): void
    {
        $html = Templates::twig()->render('@Kongtent/blocks/image.html.twig', [
            'block' => new ImageBlock(new Picture(
                '"><script>alt</script>',
                '<script>caption</script>',
                '<script>credit</script>',
                [['url' => 'https://k.example/a/large.jpg', 'width' => 800, 'height' => 600]],
            )),
        ]);

        self::assertStringNotContainsString('<script>', $html);
        self::assertStringContainsString('&quot;&gt;&lt;script&gt;alt', $html);
        self::assertStringContainsString('&lt;script&gt;caption', $html);
        self::assertStringContainsString('&lt;script&gt;credit', $html);
    }
}
