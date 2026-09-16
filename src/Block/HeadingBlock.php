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
 * A heading of the second to fourth level; the first is the headline.
 */
final readonly class HeadingBlock implements Block
{
    public function __construct(
        public int $level,
        public string $html,
    ) {
    }

    public function getType(): string
    {
        return 'heading';
    }
}
