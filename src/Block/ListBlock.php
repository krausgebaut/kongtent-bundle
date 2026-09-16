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
 * A list, `bullet` or `numbered`, its items rendered.
 */
final readonly class ListBlock implements Block
{
    /**
     * @param list<string> $items
     */
    public function __construct(
        public string $style,
        public array $items,
    ) {
    }

    public function getType(): string
    {
        return 'list';
    }

    public function isOrdered(): bool
    {
        return 'numbered' === $this->style;
    }
}
