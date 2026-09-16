<?php

/*
 * This file is part of the kongtent bundle.
 *
 * (c) krausgebaut von Marcel Kraus <mail@krausgebaut.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Krausgebaut\KongtentBundle\Block;

/**
 * A quotation; the speaker is a field, not the last line.
 */
final readonly class QuoteBlock implements Block
{
    public function __construct(
        public string $html,
        public ?string $source,
    ) {
    }

    public function getType(): string
    {
        return 'quote';
    }
}
