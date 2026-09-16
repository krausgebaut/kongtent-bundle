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
 * Several pictures in their order, each an `ImageBlock`.
 */
final readonly class GalleryBlock implements Block
{
    /**
     * @param list<ImageBlock> $images
     */
    public function __construct(public array $images)
    {
    }

    public function getType(): string
    {
        return 'gallery';
    }
}
