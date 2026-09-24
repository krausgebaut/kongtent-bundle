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
use Krausgebaut\KongtentBundle\Block\DetailsBlock;
use Krausgebaut\KongtentBundle\Block\EmbedBlock;
use Krausgebaut\KongtentBundle\Block\GalleryBlock;
use Krausgebaut\KongtentBundle\Block\HeadingBlock;
use Krausgebaut\KongtentBundle\Block\ImageBlock;
use Krausgebaut\KongtentBundle\Block\ListBlock;
use Krausgebaut\KongtentBundle\Block\ParagraphBlock;
use Krausgebaut\KongtentBundle\Block\QuoteBlock;
use Psr\Log\LoggerInterface;

/**
 * One answer of kongtent, turned into objects – a defect throws a
 * `RuntimeException`, so the last good answer can stand in.
 */
final readonly class Reader
{
    public function __construct(
        private Markdown $markdown,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function toContent(array $payload): Content
    {
        $slug = $this->text($payload, 'slug');

        return new Content(
            $slug,
            $this->date($payload, $slug),
            $this->text($payload, 'headline', $slug),
            $this->markdown->toInlineHtml($this->text($payload, 'teaser', $slug)),
            $this->text($payload, 'meta_title', $slug),
            $this->text($payload, 'meta_description', $slug),
            $this->optionalText($payload, 'category'),
            \is_array($payload['cover_image'] ?? null) ? $this->picture($payload['cover_image'], $slug) : null,
            $this->blocks($this->listOf($payload, 'blocks', $slug), $slug),
            false !== ($payload['listed'] ?? true),
        );
    }

    /**
     * @param array<mixed> $rows
     *
     * @return list<Content>
     */
    public function toList(array $rows): array
    {
        return array_map(function (mixed $row): Content {
            if (false === \is_array($row)) {
                throw new \RuntimeException('An entry of the list of contents is not a content.');
            }

            return $this->toContent($row);
        }, array_values($rows));
    }

    /**
     * @param array<mixed> $blocks
     *
     * @return list<Block>
     */
    private function blocks(array $blocks, string $slug): array
    {
        $built = [];

        foreach ($blocks as $block) {
            $type = \is_array($block) && \is_string($block['type'] ?? null) ? $block['type'] : '';

            $built[] = match ($type) {
                'details' => new DetailsBlock(array_map(
                    fn (array $item): array => [
                        'label' => $this->text($item, 'label', $slug),
                        'html' => $this->markdown->toInlineHtml($this->text($item, 'value', $slug)),
                    ],
                    $this->rows($block, 'items', $slug),
                )),
                'embed' => new EmbedBlock(
                    $this->address($block, $slug),
                    $this->optionalText($block, 'provider'),
                    $this->optionalText($block, 'key'),
                ),
                'gallery' => new GalleryBlock(array_map(
                    fn (array $image): ImageBlock => new ImageBlock($this->picture($image, $slug)),
                    $this->rows($block, 'images', $slug),
                )),
                'heading' => new HeadingBlock(
                    max(2, min(4, (int) ($block['level'] ?? 2))),
                    $this->markdown->toInlineHtml($this->text($block, 'text', $slug)),
                ),
                'image' => new ImageBlock($this->picture($block, $slug)),
                'list' => new ListBlock(
                    $this->text($block, 'style', $slug),
                    array_map(
                        fn (mixed $item): string => \is_string($item)
                            ? $this->markdown->toInlineHtml($item)
                            : throw new \RuntimeException(\sprintf('An item of a list of "%s" is not text.', $slug)),
                        array_values($this->listOf($block, 'items', $slug)),
                    ),
                ),
                'paragraph' => new ParagraphBlock($this->markdown->toHtml($this->text($block, 'text', $slug))),
                'quote' => new QuoteBlock(
                    $this->markdown->toInlineHtml($this->text($block, 'text', $slug)),
                    $this->optionalText($block, 'source'),
                ),
                default => $this->unknown($type, $slug),
            };
        }

        return array_values(array_filter($built));
    }

    /**
     * An error, not a warning: a buffering handler would never write a warning.
     */
    private function unknown(string $type, string $slug): ?Block
    {
        $this->logger->error('A block of an unknown type is skipped.', [
            'type' => $type,
            'slug' => $slug,
        ]);

        return null;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function picture(array $data, string $slug): Picture
    {
        return new Picture(
            $this->optionalText($data, 'alt') ?? '',
            $this->optionalInlineHtml($data, 'caption'),
            $this->optionalInlineHtml($data, 'credit'),
            $this->sources($data, $slug),
        );
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return list<array{url: string, width: int, height: int}>
     */
    private function sources(array $data, string $slug): array
    {
        $sources = [];

        foreach (array_values((array) ($data['sources'] ?? [])) as $one) {
            if (false === \is_array($one)
                || false === \is_string($one['url'] ?? null)
                || '' === $one['url']
                || false === \is_int($one['width'] ?? null)
                || false === \is_int($one['height'] ?? null)
            ) {
                throw new \RuntimeException(\sprintf('A size of a picture of "%s" is incomplete.', $slug));
            }

            $sources[] = ['url' => $one['url'], 'width' => $one['width'], 'height' => $one['height']];
        }

        if ([] === $sources) {
            throw new \RuntimeException(\sprintf('A picture of "%s" carries no size.', $slug));
        }

        return $sources;
    }

    /**
     * The offset comes out of the payload, never out of this machine.
     *
     * @param array<string, mixed> $data
     */
    private function date(array $data, string $slug): \DateTimeImmutable
    {
        $date = \DateTimeImmutable::createFromFormat(\DateTimeInterface::ATOM, $this->text($data, 'date', $slug));

        return false === $date
            ? throw new \RuntimeException(\sprintf('The date of "%s" is not a moment.', $slug)) : $date;
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<mixed>
     */
    private function listOf(array $data, string $key, string $slug): array
    {
        $value = $data[$key] ?? [];

        if (false === \is_array($value)) {
            throw new \RuntimeException(\sprintf('The field "%s" of "%s" is not a list.', $key, $slug));
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return list<array<string, mixed>>
     */
    private function rows(array $data, string $key, string $slug): array
    {
        $rows = array_values($this->listOf($data, $key, $slug));

        foreach ($rows as $row) {
            if (false === \is_array($row)) {
                throw new \RuntimeException(\sprintf('An entry of "%s" of "%s" is not a record.', $key, $slug));
            }
        }

        return [] === $rows
            ? throw new \RuntimeException(\sprintf('The field "%s" of "%s" is empty.', $key, $slug)) : $rows;
    }

    /**
     * Only `http` and `https`, so no link can run a script.
     *
     * @param array<string, mixed> $data
     */
    private function address(array $data, string $slug): string
    {
        $url = $this->text($data, 'url', $slug);

        return 1 === preg_match('~^https?://~i', $url)
            ? $url
            : throw new \RuntimeException(\sprintf('The address of an embed of "%s" is no web address.', $slug));
    }

    /**
     * @param array<string, mixed> $data
     */
    private function text(array $data, string $key, ?string $slug = null): string
    {
        $value = $data[$key] ?? null;

        if (false === \is_string($value) || '' === $value) {
            $message = null === $slug
                ? \sprintf('The field "%s" is missing.', $key)
                : \sprintf('The field "%s" of "%s" is missing.', $key, $slug);

            throw new \RuntimeException($message);
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function optionalText(array $data, string $key): ?string
    {
        $value = $data[$key] ?? null;

        return \is_string($value) && '' !== $value ? $value : null;
    }

    /**
     * A blank line would start a second paragraph, which an inline text
     * refuses; it is read as a line break instead.
     *
     * @param array<string, mixed> $data
     */
    private function optionalInlineHtml(array $data, string $key): ?string
    {
        $text = trim($this->optionalText($data, $key) ?? '');

        if ('' === $text) {
            return null;
        }

        return $this->markdown->toInlineHtml(preg_replace('/\R\s*\R/', "\n", $text) ?? $text);
    }
}
