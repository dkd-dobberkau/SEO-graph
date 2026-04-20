# dkd/typo3-seo-graph

**An opinionated JSON-LD graph layer for TYPO3, built on top of `brotkrueml/schema`.**

TYPO3 has had excellent schema.org tooling for years. What it has lacked is a default opinion about how the pieces fit together. This extension fills that gap: instead of emitting isolated JSON-LD blocks per page, it assembles a single linked `@graph` with stable `@id`s, cross-referenced entities, and optional validation. It does not replace EXT:schema. It orchestrates it.

-----

## Why

Modern search engines and AI agents de-duplicate entities across a site by `@id`. If every page mints a fresh identifier for the same Organization, an agent walking your site sees N organizations, not one. A linked graph, where WebSite references its publisher Organization, WebPage references its parent WebSite, Article references its author Person, and all of them resolve inside a shared `@graph`, is what gets you treated as a coherent knowledge source rather than a pile of pages.

This is the pattern Joost de Valk refined in Yoast SEO from 2019 onward, and has now extracted as `@jdevalk/seo-graph-core` for the Astro ecosystem. `dkd/typo3-seo-graph` brings the same opinion to TYPO3, without reinventing the schema.org type system that EXT:schema already handles well.

-----

## Requirements

- TYPO3 v12 LTS or v13 LTS
- PHP 8.2 or higher
- `brotkrueml/schema` ^3.0

-----

## Installation

```bash
composer require dkd/typo3-seo-graph
```

No further setup required for a baseline graph. The extension registers default piece providers, and every rendered page will emit a linked `@graph` containing at minimum a WebSite, Organization, and WebPage node.

For configuration beyond the defaults, see **Configuration** below.

-----

## What you get out of the box

For a typical content page, the emitted JSON-LD looks like this:

```json
{
  "@context": "https://schema.org",
  "@graph": [
    {
      "@type": "Organization",
      "@id": "https://example.com/#organization",
      "name": "Example GmbH",
      "url": "https://example.com/",
      "sameAs": [
        "https://www.linkedin.com/company/example",
        "https://github.com/example"
      ],
      "logo": { "@id": "https://example.com/#logo" }
    },
    {
      "@type": "ImageObject",
      "@id": "https://example.com/#logo",
      "url": "https://example.com/logo.png"
    },
    {
      "@type": "WebSite",
      "@id": "https://example.com/#website",
      "url": "https://example.com/",
      "name": "Example",
      "publisher": { "@id": "https://example.com/#organization" }
    },
    {
      "@type": "WebPage",
      "@id": "https://example.com/about/#webpage",
      "url": "https://example.com/about/",
      "name": "About us",
      "isPartOf": { "@id": "https://example.com/#website" },
      "breadcrumb": { "@id": "https://example.com/about/#breadcrumb" },
      "primaryImageOfPage": { "@id": "https://example.com/about/#primaryimage" }
    },
    {
      "@type": "BreadcrumbList",
      "@id": "https://example.com/about/#breadcrumb",
      "itemListElement": [
        { "@type": "ListItem", "position": 1, "name": "Home", "item": "https://example.com/" },
        { "@type": "ListItem", "position": 2, "name": "About us", "item": "https://example.com/about/" }
      ]
    },
    {
      "@type": "ImageObject",
      "@id": "https://example.com/about/#primaryimage",
      "url": "https://example.com/fileadmin/about-hero.jpg"
    }
  ]
}
```

For article or blog pages (configured via the TCA schema type field), an Article piece is added automatically with `isPartOf`, `mainEntityOfPage`, `author`, `publisher`, `image`, `datePublished`, and `dateModified` wired to the other entities by `@id`.

-----

## Architecture

The extension uses a collector pattern with a PSR-15 middleware:

```
Request → SeoGraphMiddleware → GraphAssembler → [PieceProviders...] → JSON-LD → Response
                                     ↓
                              GraphValidator
```

- **Piece providers** are registered via Symfony DI tag `dkd_seo_graph.piece_provider`, sorted by priority
- **PSR-14 events** (`BeforeGraphAssembledEvent`, `AfterGraphAssembledEvent`) fire around the provider loop
- **The middleware** renders the assembled `@graph` as a single JSON-LD `<script>` tag into the response `<head>`, replacing any existing JSON-LD blocks from EXT:schema
- **EXT:schema** is a composer dependency but its `SchemaManager` is not used for rendering. Piece providers return plain associative arrays representing JSON-LD nodes.

### Built-in piece providers

| Provider | Priority | `@id` | Description |
|----------|----------|-------|-------------|
| OrganizationPieceProvider | 10 | `{baseUrl}#organization` | Publisher from site config, optional logo |
| WebSitePieceProvider | 20 | `{baseUrl}#website` | Site with publisher reference |
| WebPagePieceProvider | 30 | `{pageUrl}#webpage` | Current page with isPartOf, breadcrumb, primaryImage refs |
| BreadcrumbListPieceProvider | 40 | `{pageUrl}#breadcrumb` | Breadcrumb from rootline (skipped on root page) |
| ImageObjectPieceProvider | 50 | `{pageUrl}#primaryimage` | Primary image from TCA field or page media |
| ArticlePieceProvider | 60 | `{pageUrl}#article` | Article/BlogPosting/NewsArticle with author, dates |

-----

## Configuration

Site-level configuration in `config/sites/{identifier}/config.yaml`:

```yaml
seoGraph:
  publisher:
    type: Organization
    name: 'Example GmbH'
    url: 'https://example.com/'
    logo: 'https://example.com/logo.png'
    sameAs:
      - 'https://www.linkedin.com/company/example'
      - 'https://github.com/example'
  defaultAuthor:
    type: Person
    name: 'Jane Doe'
    slug: 'jane-doe'
  validation:
    mode: warning  # warning | error | off
    rules:
      - references_resolve
      - no_duplicate_ids
      - required_properties
```

When no `seoGraph` block is configured, the extension still works: the publisher name falls back to the site title, and validation is off.

### Per-page overrides (TCA)

Four fields are added to the `pages` table in a "SEO Graph" tab:

| Field | Type | Description |
|-------|------|-------------|
| Schema Type | select | WebPage (default), Article, BlogPosting, NewsArticle, FAQPage, etc. |
| Primary Image | file (FAL) | Overrides the primary image for this page |
| Author Override | text | Author name for this page (overrides `defaultAuthor`) |
| Exclude from SEO Graph | toggle | Suppresses graph emission for this page |

-----

## Extending the graph

### Adding a piece

Implement `GraphPieceProviderInterface` and register the service with the `dkd_seo_graph.piece_provider` tag:

```php
use Dkd\SeoGraph\Piece\GraphPieceProviderInterface;
use Dkd\SeoGraph\Assembler\PageContext;
use Dkd\SeoGraph\Id\IdGenerator;

final class ProductPieceProvider implements GraphPieceProviderInterface
{
    public function __construct(
        private readonly IdGenerator $idGenerator,
    ) {}

    public function supports(PageContext $context): bool
    {
        return $context->hasPlugin('shopware_product_detail');
    }

    public function provide(PageContext $context): iterable
    {
        yield [
            '@type' => 'Product',
            '@id' => $this->idGenerator->forPage($context->pageUrl, 'product'),
            'name' => 'Example Product',
            'isPartOf' => ['@id' => $this->idGenerator->forPage($context->pageUrl, 'webpage')],
        ];
    }

    public function getPriority(): int
    {
        return 70;
    }
}
```

```yaml
# Configuration/Services.yaml
Vendor\Ext\Piece\ProductPieceProvider:
  tags:
    - name: dkd_seo_graph.piece_provider
```

### Events

For cases where the provider pattern is not enough, PSR-14 events are dispatched before and after graph assembly:

- **`BeforeGraphAssembledEvent`** — fired before providers run. Can pre-populate pieces.
- **`AfterGraphAssembledEvent`** — fired after providers run. Can modify, add, or remove pieces before serialization.

-----

## Validation

The graph validator runs against assembled graphs and reports issues. Runtime validation is configured per site in `seoGraph.validation`.

Built-in rules:

- `references_resolve`: every `@id` reference points to an entity in the graph
- `no_duplicate_ids`: no two entities share an `@id`
- `required_properties`: each piece type has its required properties set

In `warning` mode, issues are logged via the TYPO3 logging framework. In `error` mode, pieces with validation errors are removed from the graph before emission.

-----

## Relationship to other extensions

- **`brotkrueml/schema`** (required): provides the schema.org type system. This extension uses it as a dependency but does its own JSON-LD rendering. Existing EXT:schema JSON-LD blocks are replaced by the assembled `@graph`. If you are using EXT:schema directly in custom extensions, those types can be integrated into the graph via the piece provider pattern.
- **`typo3/cms-seo`**: fully compatible. EXT:seo continues to handle meta tags, canonical URLs, sitemap, and hreflang. This extension only governs JSON-LD.

-----

## Roadmap

- **v0.1** (current): core piece providers (Organization, WebSite, WebPage, BreadcrumbList, ImageObject, Article), runtime validation, site configuration, TCA fields, PSR-15 middleware
- **v0.2**: CLI validator with non-zero exit codes, Person piece, `GraphPieceModifierInterface`, Rich Results validation rule, backend module (raw JSON-LD view)
- **v0.3**: graph visualization in backend module, EXT:news companion (`dkd/typo3-seo-graph-news`), Solr companion (`dkd/typo3-seo-graph-solr`)
- **v1.0**: stable API, TER release, migration helpers from plain EXT:schema usage

-----

## Design principles

1. **Do not replace EXT:schema.** Compose with it. EXT:schema has years of accumulated correctness around the schema.org vocabulary; competing with it is a dead end.
1. **Opinionated defaults, permissive extension.** The default graph should be correct for 80% of TYPO3 sites without configuration. The extension points should make the remaining 20% straightforward.
1. **`@id` stability is non-negotiable.** The URI convention (`{url}#{fragment}`) is the contract. Changing it is a breaking change.
1. **Validate, do not silently emit broken data.** In error mode, an invalid piece is dropped rather than shipped. Broken structured data is worse than missing structured data.

-----

## License

GPL-2.0-or-later

-----

## Credits

Conceptual lineage: Joost de Valk's Yoast SEO graph architecture, extracted in [`@jdevalk/seo-graph-core`](https://github.com/jdevalk/seo-graph). This extension ports the opinion, not the code, to the TYPO3 ecosystem.

Built on top of [`brotkrueml/schema`](https://github.com/brotkrueml/schema) by Chris Mueller, which provides the underlying schema.org type system.

Maintained by [dkd Internet Service GmbH](https://www.dkd.de/).
