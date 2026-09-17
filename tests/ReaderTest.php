<?php

/*
 * This file is part of the kongtent bundle.
 *
 * (c) krausgebaut von Marcel Kraus <mail@krausgebaut.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Krausgebaut\KongtentBundle\Tests;

use Krausgebaut\KongtentBundle\Block\DetailsBlock;
use Krausgebaut\KongtentBundle\Block\EmbedBlock;
use Krausgebaut\KongtentBundle\Block\GalleryBlock;
use Krausgebaut\KongtentBundle\Block\HeadingBlock;
use Krausgebaut\KongtentBundle\Block\ImageBlock;
use Krausgebaut\KongtentBundle\Block\ListBlock;
use Krausgebaut\KongtentBundle\Block\ParagraphBlock;
use Krausgebaut\KongtentBundle\Block\QuoteBlock;
use Krausgebaut\KongtentBundle\Markdown;
use Krausgebaut\KongtentBundle\Reader;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class ReaderTest extends TestCase
{
    private Reader $reader;

    protected function setUp(): void
    {
        $this->reader = new Reader(new Markdown(), new NullLogger());
    }

    public function testAContentIsListedUnlessKongtentSaysOtherwise(): void
    {
        self::assertTrue($this->reader->toContent($this->payload([]))->listed);
        self::assertFalse($this->reader->toContent(['listed' => false] + $this->payload([]))->listed);
    }

    public function testABlockNobodyKnowsIsSkippedAndTheRestStands(): void
    {
        $content = $this->reader->toContent($this->payload([
            ['type' => 'paragraph', 'text' => 'Before.'],
            ['type' => 'poll', 'question' => 'Which one?'],
            ['type' => 'paragraph', 'text' => 'After.'],
        ]));

        self::assertCount(2, $content->blocks);
        self::assertSame('<p>Before.</p>', $content->blocks[0]->html);
    }

    public function testABlockWhoseTypeIsNoTextIsSkipped(): void
    {
        $content = $this->reader->toContent($this->payload([
            ['type' => ['paragraph'], 'text' => 'Nothing.'],
            ['type' => 'paragraph', 'text' => 'After.'],
        ]));

        self::assertCount(1, $content->blocks);
    }

    public function testEveryKnownBlockBecomesItsObject(): void
    {
        $content = $this->reader->toContent($this->payload([
            ['type' => 'paragraph', 'text' => 'A **paragraph**.'],
            ['type' => 'heading', 'level' => 3, 'text' => 'A heading'],
            ['type' => 'list', 'style' => 'numbered', 'items' => ['One', 'Two']],
            ['type' => 'quote', 'text' => 'A quotation.', 'source' => 'A speaker'],
        ]));

        [$paragraph, $heading, $list, $quote] = $content->blocks;

        self::assertInstanceOf(ParagraphBlock::class, $paragraph);
        self::assertSame('<p>A <strong>paragraph</strong>.</p>', $paragraph->html);

        self::assertInstanceOf(HeadingBlock::class, $heading);
        self::assertSame(3, $heading->level);
        self::assertSame('A heading', $heading->html);

        self::assertInstanceOf(ListBlock::class, $list);
        self::assertTrue($list->isOrdered(), 'A numbered list came out bulleted.');
        self::assertSame(['One', 'Two'], $list->items);

        self::assertInstanceOf(QuoteBlock::class, $quote);
        self::assertSame('A speaker', $quote->source);
    }

    public function testTextThatOpensLikeABlockIsRead(): void
    {
        $payload = $this->payload([
            ['type' => 'heading', 'level' => 2, 'text' => '3. March 2020'],
            ['type' => 'list', 'style' => 'bullet', 'items' => ['1. Place']],
            ['type' => 'quote', 'text' => '> Twice', 'source' => null],
        ]);
        $payload['teaser'] = '1) First';

        $content = $this->reader->toContent($payload);

        self::assertSame('1) First', $content->teaser);
        self::assertSame('3. March 2020', $content->blocks[0]->html);
        self::assertSame(['1. Place'], $content->blocks[1]->items);
        self::assertSame('&gt; Twice', $content->blocks[2]->html);
    }

    public function testMarkupOutOfThePayloadArrivesAsText(): void
    {
        $content = $this->reader->toContent($this->payload([
            ['type' => 'paragraph', 'text' => 'An <script>alert(1)</script> attempt.'],
        ]));

        self::assertStringNotContainsString('<script>', $content->blocks[0]->html);
        self::assertStringContainsString('&lt;script&gt;', $content->blocks[0]->html);
    }

    public function testAMissingFieldIsRefused(): void
    {
        $payload = $this->payload([]);
        unset($payload['headline']);

        $this->expectException(\RuntimeException::class);

        $this->reader->toContent($payload);
    }

    #[DataProvider('blocksOfTheWrongShape')]
    public function testBlocksOfTheWrongShapeAreRefused(mixed $blocks): void
    {
        $payload = $this->payload([]);
        $payload['blocks'] = $blocks;

        $this->expectException(\RuntimeException::class);

        $this->reader->toContent($payload);
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function blocksOfTheWrongShape(): iterable
    {
        yield 'blocks as text' => ['x'];
        yield 'an item that is nothing' => [[['type' => 'list', 'style' => 'bullet', 'items' => [null]]]];
        yield 'an item that is a list' => [[['type' => 'list', 'style' => 'bullet', 'items' => [['a']]]]];
        yield 'items as text' => [[['type' => 'list', 'style' => 'bullet', 'items' => 'a']]];
        yield 'a gallery without a picture' => [[['type' => 'gallery', 'images' => []]]];
        yield 'a gallery whose picture is text' => [[['type' => 'gallery', 'images' => ['a']]]];
        yield 'a term without a value' => [[['type' => 'details', 'items' => [['label' => 'Weight']]]]];
        yield 'a term list without a term' => [[['type' => 'details', 'items' => []]]];
        yield 'an embed without an address' => [[['type' => 'embed', 'provider' => null, 'key' => null]]];
        yield 'an embed pointing at a script' => [[
            ['type' => 'embed', 'url' => 'javascript:alert(1)', 'provider' => null, 'key' => null],
        ]];
    }

    public function testAGalleryCarriesItsPicturesInOrder(): void
    {
        $content = $this->reader->toContent($this->payload([
            ['type' => 'gallery', 'images' => [$this->image('first'), $this->image('second')]],
        ]));

        $gallery = $content->blocks[0];

        self::assertInstanceOf(GalleryBlock::class, $gallery);
        self::assertSame(['first', 'second'], array_map(
            static fn (ImageBlock $one): string => $one->picture->alternativeText,
            $gallery->images,
        ));
    }

    public function testATermListCarriesItsNamesAsTextAndItsValuesRendered(): void
    {
        $content = $this->reader->toContent($this->payload([
            ['type' => 'details', 'items' => [['label' => 'Weight <b>', 'value' => '*Heavy* paper']]],
        ]));

        $details = $content->blocks[0];

        self::assertInstanceOf(DetailsBlock::class, $details);
        self::assertSame([['label' => 'Weight <b>', 'html' => '<em>Heavy</em> paper']], $details->items);
    }

    public function testAnEmbedKeepsItsAddressWithOrWithoutAProvider(): void
    {
        $content = $this->reader->toContent($this->payload([
            ['type' => 'embed', 'url' => 'https://youtu.be/abc', 'provider' => 'youtube', 'key' => 'abc'],
            ['type' => 'embed', 'url' => 'https://example.com/film', 'provider' => null, 'key' => null],
        ]));

        [$known, $unknown] = $content->blocks;

        self::assertInstanceOf(EmbedBlock::class, $known);
        self::assertSame(['https://youtu.be/abc', 'youtube', 'abc'], [$known->url, $known->provider, $known->key]);
        self::assertSame(['https://example.com/film', null, null], [$unknown->url, $unknown->provider, $unknown->key]);
    }

    public function testTheOffsetComesOutOfThePayload(): void
    {
        $zone = date_default_timezone_get();
        date_default_timezone_set('UTC');

        try {
            $payload = $this->payload([]);
            $payload['date'] = '2026-01-01T00:30:00+02:00';

            $content = $this->reader->toContent($payload);

            self::assertSame('+02:00', $content->date->format('P'));
            self::assertSame('2026', $content->getYear());
        } finally {
            date_default_timezone_set($zone);
        }
    }

    public function testAListEntryThatIsNoContentIsRefused(): void
    {
        $this->expectException(\RuntimeException::class);

        $this->reader->toList(['x']);
    }

    public function testAPictureCarriesEverySizeItHas(): void
    {
        $content = $this->reader->toContent($this->payload([
            [
                'type' => 'image',
                'alt' => 'A picture',
                'caption' => null,
                'credit' => null,
                'sources' => [
                    ['url' => 'https://k.example/a/small.jpg', 'width' => 480, 'height' => 270],
                    ['url' => 'https://k.example/a/large.jpg', 'width' => 1920, 'height' => 1080],
                ],
            ],
        ]));

        $block = $content->blocks[0];

        self::assertInstanceOf(ImageBlock::class, $block);
        self::assertSame('https://k.example/a/large.jpg', $block->picture->getUrl());
        self::assertSame(1920, $block->picture->getWidth());
        self::assertSame(
            'https://k.example/a/small.jpg 480w, https://k.example/a/large.jpg 1920w',
            $block->picture->getSourceSet(),
        );
    }

    public function testOneSizeCarriesNoSourceSet(): void
    {
        $content = $this->reader->toContent($this->payload([
            [
                'type' => 'image',
                'alt' => 'A picture',
                'sources' => [['url' => 'https://k.example/a/large.jpg', 'width' => 800, 'height' => 600]],
            ],
        ]));

        self::assertNull($content->blocks[0]->picture->getSourceSet());
    }

    public function testAPictureWithoutAlternativeTextIsShownWithAnEmptyOne(): void
    {
        $sources = [['url' => 'https://k.example/a/large.jpg', 'width' => 800, 'height' => 600]];
        $payload = $this->payload([['type' => 'image', 'alt' => null, 'sources' => $sources]]);
        $payload['cover_image'] = ['type' => 'image', 'alt' => null, 'sources' => $sources];

        $content = $this->reader->toContent($payload);

        self::assertSame('', $content->coverImage?->alternativeText);
        self::assertInstanceOf(ImageBlock::class, $content->blocks[0]);
        self::assertSame('', $content->blocks[0]->picture->alternativeText);
    }

    public function testASizeWithoutItsMeasurementsIsRefused(): void
    {
        $this->expectException(\RuntimeException::class);

        $this->reader->toContent($this->payload([
            ['type' => 'image', 'alt' => 'A picture', 'sources' => [['url' => 'https://k.example/a/large.jpg']]],
        ]));
    }

    public function testAHeadingStaysBetweenTheSecondAndTheFourthLevel(): void
    {
        $content = $this->reader->toContent($this->payload([
            ['type' => 'heading', 'level' => 1, 'text' => 'Too high'],
            ['type' => 'heading', 'level' => 6, 'text' => 'Too deep'],
        ]));

        self::assertSame(2, $content->blocks[0]->level);
        self::assertSame(4, $content->blocks[1]->level);
    }

    /**
     * @return array<string, mixed>
     */
    private function image(string $alternativeText): array
    {
        return [
            'type' => 'image',
            'alt' => $alternativeText,
            'caption' => null,
            'credit' => null,
            'sources' => [['url' => 'https://k.example/a/large.jpg', 'width' => 800, 'height' => 600]],
        ];
    }

    /**
     * @param list<array<string, mixed>> $blocks
     *
     * @return array<string, mixed>
     */
    private function payload(array $blocks): array
    {
        return [
            'date' => '2026-07-13T09:00:00+02:00',
            'headline' => 'An article',
            'teaser' => 'A **teaser**.',
            'meta_title' => 'An article',
            'meta_description' => 'A description.',
            'category' => 'Notes',
            'slug' => 'an-article',
            'cover_image' => null,
            'blocks' => $blocks,
        ];
    }
}
