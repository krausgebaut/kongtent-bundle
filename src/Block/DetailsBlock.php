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
 * A term list, not the HTML `<details>` element. `label` is text, `html` is
 * rendered.
 */
final readonly class DetailsBlock implements Block
{
    /**
     * @param list<array{label: string, html: string}> $items
     */
    public function __construct(public array $items)
    {
    }

    public function getType(): string
    {
        return 'details';
    }
}
