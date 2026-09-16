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
 * An address elsewhere; `provider` and `key` only where kongtent recognized
 * it.
 */
final readonly class EmbedBlock implements Block
{
    public function __construct(
        public string $url,
        public ?string $provider,
        public ?string $key,
    ) {
    }

    public function getType(): string
    {
        return 'embed';
    }
}
