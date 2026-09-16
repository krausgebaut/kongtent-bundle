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

use Krausgebaut\KongtentBundle\Block\QuoteBlock;
use PHPUnit\Framework\TestCase;

final class QuoteTest extends TestCase
{
    public function testTheSpeakerCannotCarryMarkup(): void
    {
        $html = Templates::twig()->render('@Kongtent/blocks/quote.html.twig', [
            'block' => new QuoteBlock('A sentence.', '<script>alert(1)</script>'),
        ]);

        self::assertStringNotContainsString('<script>', $html);
        self::assertStringContainsString('&lt;script&gt;', $html);
    }
}
