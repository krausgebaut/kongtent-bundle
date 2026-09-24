# kongtent bundle

Reads contents out of kongtent and renders their blocks in a Symfony site.

kongtent holds the articles and the pictures and hands them out over `/api/v1/content`. This bundle asks for them, turns the answer into objects and renders every block with a template of its own, which a site overrides where it wants its own markup.

## Installation

The bundle is not on Packagist and carries no version tags. A site names the repository and requires the `main` branch; its `composer.lock` holds the commit, and `composer update` moves it:

```json
{
    "repositories": [
        { "type": "vcs", "url": "https://github.com/krausgebaut/kongtent-bundle" }
    ],
    "require": {
        "krausgebaut/kongtent-bundle": "dev-main"
    }
}
```

Then register it in `config/bundles.php`:

```php
Krausgebaut\KongtentBundle\KongtentBundle::class => ['all' => true],
```

## Configuration

```yaml
# config/packages/kongtent.yaml
kongtent:
    url: '%env(KONGTENT_URL)%'
    key: '%env(KONGTENT_KEY)%'
```

| # | Setting | Default | |
| --- | --- | --- | --- |
| 1 | `cache` | `cache.app` | The service the answers are held in. It implements both `CacheInterface` and `CacheItemPoolInterface`, as every Symfony cache pool does |
| 2 | `http_client` | `http_client` | The service kongtent is asked through. Never a caching client: the answer is `private`, and a shared cache refuses it silently |
| 3 | `key` | – | The key of the site's channel. Required |
| 4 | `url` | – | The address of kongtent, without a path. Required |

## Reading

Inject `Krausgebaut\KongtentBundle\Client`:

| # | Method | Answers |
| --- | --- | --- |
| 1 | `all()` | Every visible and listed content of the channel, newest first, **without blocks** |
| 2 | `one($slug)` | One content with its blocks, or `null` where the channel has no such slug |

A list of contents is not a list of texts: whoever draws an overview works from `all()`, whoever shows a text asks `one()`.

**Some fields arrive as HTML, the rest as text.** HTML has been through the Markdown converter and is printed with `|raw`; text is printed as it is and escaped by Twig.

| # | Class | HTML | Text |
| --- | --- | --- | --- |
| 1 | `Content` | `teaser` | `category`, `headline`, `metaDescription`, `metaTitle`, `slug` |
| 2 | `DetailsBlock` | `items[].html` | `items[].label` |
| 3 | `EmbedBlock` | – | `key`, `provider`, `url` |
| 4 | `HeadingBlock` | `html` | – |
| 5 | `ListBlock` | `items` | `style` |
| 6 | `ParagraphBlock` | `html` | – |
| 7 | `Picture` | `caption`, `credit` | `alternativeText` |
| 8 | `QuoteBlock` | `html` | `source` |

`Content` also carries `date`, `coverImage` as a `Picture` or `null`, `blocks`, `listed` and `getYear()`. `listed` is `false` only where kongtent says so: a content left out of the lists and read by its slug alone. A `Picture` answers `getUrl()`, `getWidth()` and `getHeight()` for its largest size and `getSourceSet()` for a `srcset`, or `null` where there is only one size. An `ImageBlock` carries its `picture`, a `GalleryBlock` its `images`.

## Rendering

```twig
{% include '@Kongtent/blocks.html.twig' with { blocks: content.blocks } only %}
```

Every block is rendered by `@Kongtent/blocks/<type>.html.twig`, in blank markup:

| # | Type | Class | Markup |
| --- | --- | --- | --- |
| 1 | `details` | `DetailsBlock` | `<dl>` – a term list, not the HTML `<details>` element |
| 2 | `embed` | `EmbedBlock` | A link to the address. Nothing is loaded from the provider |
| 3 | `gallery` | `GalleryBlock` | `<ul>`, each picture through the `image` template |
| 4 | `heading` | `HeadingBlock` | `<h2>` to `<h4>` |
| 5 | `image` | `ImageBlock` | `<figure>` with `<img>`, `srcset` and `<figcaption>` |
| 6 | `list` | `ListBlock` | `<ul>` or `<ol>` |
| 7 | `paragraph` | `ParagraphBlock` | `<p>` |
| 8 | `quote` | `QuoteBlock` | `<figure>` with `<blockquote>` and `<figcaption>` |

A `GalleryBlock` carries its pictures as `ImageBlock`s in the order they were put in, so a site that overrides the `image` template styles its galleries along with it. An `EmbedBlock` carries the `url` as typed, plus `provider` and `key` where kongtent recognized the address – YouTube today – and `null` for both otherwise; a site that wants a player builds it in its own version of the template.

### Overriding a template

A site puts its own version under `templates/bundles/KongtentBundle/blocks/<type>.html.twig`. It receives `block` and nothing else.

**The `image` template sets `sizes="auto, 100vw"`**, because the bundle does not know how wide a site's column is. A browser that supports `auto` measures the frame of the lazily loaded picture; any other one fetches the size for the whole window, so a site that knows its frame says so in its own version.

**A block type kongtent adds later renders nothing** until the bundle knows it: the reader skips it and logs an error, so no site template is ever asked for it.

## Tests

A site's suite never reaches the network. `Krausgebaut\KongtentBundle\Test\RecordedClient::fromDirectory()` answers out of recorded files – `list.json` for the list, `<slug>.json` for one content, and `404` for everything else:

```yaml
# config/packages/kongtent.yaml
when@test:
    kongtent:
        cache: kongtent.recorded_cache
        http_client: kongtent.recorded_client
        key: recorded
        url: https://kongtent.example
    services:
        kongtent.recorded_cache:
            class: Symfony\Component\Cache\Adapter\ArrayAdapter
        kongtent.recorded_client:
            class: Symfony\Component\HttpClient\MockHttpClient
            factory: ['Krausgebaut\KongtentBundle\Test\RecordedClient', 'fromDirectory']
            arguments: ['%kernel.project_dir%/tests/fixtures/kongtent']
```

The bundle's own suite runs with `composer update` and `vendor/bin/phpunit`; the coding style is Symfony's, checked with `vendor/bin/php-cs-fixer check`.

## What the code enforces

**The key travels in the `X-Api-Key` header and nowhere else.** In the address it would work, and land in every access log.

**A slug is asked for only in kongtent's own grammar** – lower-case letters and digits between single hyphens. Anything else answers `null` without a request, so an address a site hands over unchecked can carry neither a query nor a way up onto kongtent.

**A call ends after 5 seconds of silence or 10 in total**, so a kongtent that trickles does not hold a page open.

**An answer is held as long as kongtent says**, in its `Cache-Control: max-age`, in the configured cache. An answer without `max-age`, a `404` among them, is not held.

**When kongtent cannot be read, the last good answer stands in** and an error is logged. It is overwritten by every answer that could be read, and never by one that could not. A `404` leaves no copy behind.

**A fault is never an empty list.** A refusal, an unreachable system or an answer without the list throws, unless a last good answer stands in.

**A required field that is missing throws, and so does a list or an item of a list of the wrong shape, and a gallery or a term list that is empty.** Thrown as a `RuntimeException`, so the last good answer can stand in. An optional field of the wrong shape counts as absent.

**Four things are tolerated.** A block of an unknown type is skipped and logged, a picture without an alternative text gets an empty one, a heading outside the second to fourth level is set at the nearest one, and a blank line in a caption or a credit is read as a line break, since both stay one paragraph. The log level is `error`, not `warning`: a production handler that buffers everything below `error` would otherwise never write it.

**What went through `Markdown` may be printed raw, and nothing else.** The converter escapes HTML, refuses unsafe links and reads inline Markdown only: no text turns into a heading, a list, a quotation, code or an HTML block. A picture in the text becomes a link. A speaker, the name of a term and an alternative text are plain text and are escaped. An embedded address is refused unless it is `http` or `https`.

**A date carries the offset out of the payload.** Print it with `false` as its zone, `|date('c', false)`: converted into the zone of the machine, an article dated just after midnight stands under the wrong year.

## License

MIT, see [LICENSE](LICENSE).
