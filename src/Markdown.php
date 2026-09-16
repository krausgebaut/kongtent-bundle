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

use League\CommonMark\Environment\Environment;
use League\CommonMark\MarkdownConverter;

/**
 * The one place Markdown out of kongtent becomes HTML, escaping HTML and
 * refusing unsafe links – what makes a `raw` in a template safe.
 */
final readonly class Markdown
{
    // The paragraph parser removes a link reference definition, line and all.
    private const string REFERENCE = '/^([ \t]*)\[(?=(?:\\\\.|[^\\\\\]])*\]:)/m';

    private MarkdownConverter $converter;

    public function __construct()
    {
        $environment = new Environment([
            'html_input' => 'escape',
            'allow_unsafe_links' => false,
        ]);
        $environment->addExtension(new InlineMarkdownExtension());

        $this->converter = new MarkdownConverter($environment);
    }

    /**
     * A whole paragraph, wrapper included.
     */
    public function toHtml(string $markdown): string
    {
        $text = preg_replace(self::REFERENCE, '$1\\[', $markdown) ?? $markdown;

        return trim($this->converter->convert($text)->getContent());
    }

    /**
     * A single paragraph without its wrapper; anything else is refused.
     */
    public function toInlineHtml(string $markdown): string
    {
        $html = $this->toHtml($markdown);

        if (false === str_starts_with($html, '<p>')
            || false === str_ends_with($html, '</p>')
            || substr_count($html, '<p>') > 1
        ) {
            throw new \RuntimeException(\sprintf('An inline text has to be one paragraph, "%s" is not.', $markdown));
        }

        return substr($html, 3, -4);
    }
}
