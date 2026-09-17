<?php

/*
 * This file is part of the kongtent bundle.
 *
 * (c) krausgebaut von Marcel Kraus <mail@krausgebaut.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Krausgebaut\KongtentBundle;

use Krausgebaut\KongtentBundle\Block\Block;

/**
 * One content – a summary out of the list carries no blocks.
 */
final readonly class Content
{
    /**
     * @param list<Block> $blocks
     */
    public function __construct(
        public string $slug,
        public \DateTimeImmutable $date,
        public string $headline,
        public string $teaser,
        public string $metaTitle,
        public string $metaDescription,
        public ?string $category,
        public ?Picture $coverImage,
        public array $blocks = [],
        public bool $listed = true,
    ) {
    }

    public function getYear(): string
    {
        return $this->date->format('Y');
    }
}
