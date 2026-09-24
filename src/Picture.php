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

/**
 * A picture in the sizes it exists in, measured by the payload.
 */
final readonly class Picture
{
    /**
     * The sources are ordered small to large and never empty. Caption and
     * credit went through `Markdown`; the alternative text is plain text.
     *
     * @param list<array{url: string, width: int, height: int}> $sources
     */
    public function __construct(
        public string $alternativeText,
        public ?string $caption,
        public ?string $credit,
        public array $sources,
    ) {
    }

    /**
     * The largest size.
     */
    public function getUrl(): string
    {
        return $this->largest()['url'];
    }

    public function getWidth(): int
    {
        return $this->largest()['width'];
    }

    public function getHeight(): int
    {
        return $this->largest()['height'];
    }

    public function getSourceSet(): ?string
    {
        if (\count($this->sources) < 2) {
            return null;
        }

        return implode(', ', array_map(
            static fn (array $one): string => \sprintf('%s %dw', $one['url'], $one['width']),
            $this->sources,
        ));
    }

    /**
     * @return array{url: string, width: int, height: int}
     */
    private function largest(): array
    {
        return $this->sources[\count($this->sources) - 1];
    }
}
