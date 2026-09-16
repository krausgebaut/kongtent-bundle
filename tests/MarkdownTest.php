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

use Krausgebaut\KongtentBundle\Markdown;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class MarkdownTest extends TestCase
{
    public function testMarkupArrivesAsText(): void
    {
        $html = (new Markdown())->toHtml('An <script>alert(1)</script> attempt.');

        self::assertStringNotContainsString('<script>', $html);
        self::assertStringContainsString('&lt;script&gt;', $html);
    }

    public function testAnUnsafeLinkLeadsNowhere(): void
    {
        self::assertStringNotContainsString('javascript:', (new Markdown())->toHtml('[Click](javascript:alert(1))'));
    }

    public function testAPictureInTheTextBecomesALink(): void
    {
        $markdown = new Markdown();

        foreach (['![Photo](https://elsewhere.example/picture.jpg)', '\![Photo](https://elsewhere.example/picture.jpg)'] as $text) {
            $html = $markdown->toHtml($text);

            self::assertStringNotContainsString('<img', $html);
            self::assertStringContainsString('href="https://elsewhere.example/picture.jpg"', $html);
        }
    }

    public function testAPictureInsideALinkBecomesItsText(): void
    {
        $markdown = new Markdown();

        self::assertSame('<a href="https://b.example">Photo</a>', $markdown->toInlineHtml('[![Photo](https://a.example/picture.jpg)](https://b.example)'));
        self::assertSame('<a href="c">a</a>', $markdown->toInlineHtml('![![a](b)](c)'));
    }

    #[DataProvider('textsThatOpenLikeABlock')]
    public function testNoTextBecomesABlock(string $markdown, string $inline): void
    {
        self::assertSame($inline, (new Markdown())->toInlineHtml($markdown));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function textsThatOpenLikeABlock(): iterable
    {
        yield 'ordered list' => ['3. March 2020', '3. March 2020'];
        yield 'ordered list with a parenthesis' => ['1) First', '1) First'];
        yield 'bullet list' => ['- no item', '- no item'];
        yield 'heading' => ['# No heading', '# No heading'];
        yield 'quotation' => ['> No quotation', '&gt; No quotation'];
        yield 'HTML comment' => ['<!-- Draft -->', '&lt;!-- Draft --&gt;'];
        yield 'HTML block' => ['<div>Box</div>', '&lt;div&gt;Box&lt;/div&gt;'];
        yield 'thematic break' => ['_ _ _', '_ _ _'];
        yield 'code fence' => ['```', '```'];
        yield 'indented code' => ['    indented', 'indented'];
        yield 'link reference definition' => ['[x]: https://example.com', '[x]: https://example.com'];
        yield 'link reference definition over two lines' => ["[Two\nLines]: https://example.com", "[Two\nLines]: https://example.com"];
        yield 'link reference definition with an escaped bracket' => ['[a\]b]: https://example.com', '[a]b]: https://example.com'];
    }

    public function testASetextUnderlineStaysText(): void
    {
        self::assertSame("<p>Title\n===</p>", (new Markdown())->toHtml("Title\n==="));
    }

    public function testEmphasisAndHardBreaksSurvive(): void
    {
        self::assertSame(
            "<p><strong>Bold</strong><br />\n<em>Italic</em><br />\nPlain – dashed</p>",
            (new Markdown())->toHtml("**Bold**  \n*Italic*  \nPlain – dashed"),
        );
    }

    public function testLinksAndInlineCodeSurvive(): void
    {
        $markdown = new Markdown();

        self::assertSame(
            '<a href="/details">The details</a> tell more.',
            $markdown->toInlineHtml('[The details](/details) tell more.'),
        );
        self::assertSame('<code>![Picture](x)</code>', $markdown->toInlineHtml('`![Picture](x)`'));
    }
}
