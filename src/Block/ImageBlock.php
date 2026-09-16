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

use Krausgebaut\KongtentBundle\Picture;

final readonly class ImageBlock implements Block
{
    public function __construct(public Picture $picture)
    {
    }

    public function getType(): string
    {
        return 'image';
    }
}
