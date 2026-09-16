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
 * Running text, rendered from Markdown while the block is built.
 */
final readonly class ParagraphBlock implements Block
{
    public function __construct(public string $html)
    {
    }

    public function getType(): string
    {
        return 'paragraph';
    }
}
