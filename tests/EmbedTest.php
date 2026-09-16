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

use Krausgebaut\KongtentBundle\Block\EmbedBlock;
use PHPUnit\Framework\TestCase;

final class EmbedTest extends TestCase
{
    public function testTheAddressCannotLeaveItsAttribute(): void
    {
        $html = Templates::twig()->render('@Kongtent/blocks/embed.html.twig', [
            'block' => new EmbedBlock('https://x.example/"onmouseover="alert(1)', null, null),
        ]);

        self::assertStringNotContainsString('"onmouseover', $html);
        self::assertStringContainsString('&quot;onmouseover', $html);
    }
}
