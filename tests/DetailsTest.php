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

use Krausgebaut\KongtentBundle\Block\DetailsBlock;
use PHPUnit\Framework\TestCase;

final class DetailsTest extends TestCase
{
    public function testTheNameCannotCarryMarkup(): void
    {
        $html = Templates::twig()->render('@Kongtent/blocks/details.html.twig', [
            'block' => new DetailsBlock([['label' => '<script>alert(1)</script>', 'html' => '<em>heavy</em>']]),
        ]);

        self::assertStringNotContainsString('<script>', $html);
        self::assertStringContainsString('&lt;script&gt;', $html);
        self::assertStringContainsString('<em>heavy</em>', $html);
    }
}
