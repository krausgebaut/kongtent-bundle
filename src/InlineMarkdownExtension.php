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

use League\CommonMark\Environment\EnvironmentBuilderInterface;
use League\CommonMark\Event\DocumentParsedEvent;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\CommonMark\Delimiter\Processor\EmphasisDelimiterProcessor;
use League\CommonMark\Extension\CommonMark\Node\Inline\Code;
use League\CommonMark\Extension\CommonMark\Node\Inline\Emphasis;
use League\CommonMark\Extension\CommonMark\Node\Inline\HtmlInline;
use League\CommonMark\Extension\CommonMark\Node\Inline\Image;
use League\CommonMark\Extension\CommonMark\Node\Inline\Link;
use League\CommonMark\Extension\CommonMark\Node\Inline\Strong;
use League\CommonMark\Extension\CommonMark\Parser\Inline\AutolinkParser;
use League\CommonMark\Extension\CommonMark\Parser\Inline\BacktickParser;
use League\CommonMark\Extension\CommonMark\Parser\Inline\BangParser;
use League\CommonMark\Extension\CommonMark\Parser\Inline\CloseBracketParser;
use League\CommonMark\Extension\CommonMark\Parser\Inline\EntityParser;
use League\CommonMark\Extension\CommonMark\Parser\Inline\EscapableParser;
use League\CommonMark\Extension\CommonMark\Parser\Inline\HtmlInlineParser;
use League\CommonMark\Extension\CommonMark\Parser\Inline\OpenBracketParser;
use League\CommonMark\Extension\CommonMark\Renderer\Inline\CodeRenderer;
use League\CommonMark\Extension\CommonMark\Renderer\Inline\EmphasisRenderer;
use League\CommonMark\Extension\CommonMark\Renderer\Inline\HtmlInlineRenderer;
use League\CommonMark\Extension\CommonMark\Renderer\Inline\LinkRenderer;
use League\CommonMark\Extension\CommonMark\Renderer\Inline\StrongRenderer;
use League\CommonMark\Extension\ConfigurableExtensionInterface;
use League\CommonMark\Node\Block\Document;
use League\CommonMark\Node\Block\Paragraph;
use League\CommonMark\Node\Inline\Newline;
use League\CommonMark\Node\Inline\Text;
use League\CommonMark\Node\Node;
use League\CommonMark\Parser\Inline\NewlineParser;
use League\CommonMark\Renderer\Block\DocumentRenderer;
use League\CommonMark\Renderer\Block\ParagraphRenderer;
use League\CommonMark\Renderer\Inline\NewlineRenderer;
use League\CommonMark\Renderer\Inline\TextRenderer;
use League\Config\ConfigurationBuilderInterface;

/**
 * CommonMark with its inline half only: no block can start, and a picture in
 * the text becomes a link.
 */
final class InlineMarkdownExtension implements ConfigurableExtensionInterface
{
    public function configureSchema(ConfigurationBuilderInterface $builder): void
    {
        (new CommonMarkCoreExtension())->configureSchema($builder);
    }

    public function register(EnvironmentBuilderInterface $environment): void
    {
        $environment
            ->addInlineParser(new NewlineParser(), 200)
            ->addInlineParser(new BacktickParser(), 150)
            ->addInlineParser(new EscapableParser(), 80)
            ->addInlineParser(new EntityParser(), 70)
            ->addInlineParser(new AutolinkParser(), 50)
            ->addInlineParser(new HtmlInlineParser(), 40)
            ->addInlineParser(new CloseBracketParser(), 30)
            ->addInlineParser(new OpenBracketParser(), 20)
            ->addInlineParser(new BangParser(), 10)
            ->addDelimiterProcessor(new EmphasisDelimiterProcessor('*'))
            ->addDelimiterProcessor(new EmphasisDelimiterProcessor('_'))
            ->addRenderer(Code::class, new CodeRenderer())
            ->addRenderer(Document::class, new DocumentRenderer())
            ->addRenderer(Emphasis::class, new EmphasisRenderer())
            ->addRenderer(HtmlInline::class, new HtmlInlineRenderer())
            ->addRenderer(Link::class, new LinkRenderer())
            ->addRenderer(Newline::class, new NewlineRenderer())
            ->addRenderer(Paragraph::class, new ParagraphRenderer())
            ->addRenderer(Strong::class, new StrongRenderer())
            ->addRenderer(Text::class, new TextRenderer())
            ->addEventListener(DocumentParsedEvent::class, $this->picturesToLinks(...));
    }

    private function picturesToLinks(DocumentParsedEvent $event): void
    {
        $pictures = [];

        foreach ($event->getDocument()->iterator() as $node) {
            if ($node instanceof Image) {
                $pictures[] = $node;
            }
        }

        foreach ($pictures as $picture) {
            $children = [...$picture->children()];

            if ($this->insideALinkOrPicture($picture)) {
                foreach ($children as $child) {
                    $picture->insertBefore($child);
                }

                $picture->detach();

                continue;
            }

            $link = new Link($picture->getUrl(), null, $picture->getTitle());

            foreach ($children as $child) {
                $link->appendChild($child);
            }

            $picture->replaceWith($link);
        }
    }

    /**
     * A picture counts, because every picture outside a link becomes one.
     */
    private function insideALinkOrPicture(Node $node): bool
    {
        for ($parent = $node->parent(); null !== $parent; $parent = $parent->parent()) {
            if ($parent instanceof Link || $parent instanceof Image) {
                return true;
            }
        }

        return false;
    }
}
