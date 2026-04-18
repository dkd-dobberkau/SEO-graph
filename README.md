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
      "logo": { "@id": "https://example.com/#logo" }
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
      "itemListElement": [ ... ]
    },
    {
      "@type": "ImageObject",
      "@id": "https://example.com/about/#primaryimage",
      "url": "https://example.com/fileadmin/about-hero.jpg"
    }
  ]
}
```

For article, news, or blog pages, an Article piece is added automatically with `isPartOf`, `mainEntityOfPage`, `author`, `publisher`, and `image` wired to the other entities by `@id`.

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

Per-page overrides are available through TCA fields added to the `pages` table: schema type, primary image, author override, and a toggle to exclude a page from graph emission.

-----

## Extending the graph

Two extension points, both following TYPO3 service configuration conventions.

### Adding a piece

Implement `GraphPieceProviderInterface` and register the service with the `dkd_seo_graph.piece_provider` tag:

```php
use Dkd\SeoGraph\Piece\GraphPieceProviderInterface;
use Dkd\SeoGraph\Assembler\PageContext;

final class ProductPieceProvider implements GraphPieceProviderInterface
{
    public function supports(PageContext $context): bool
    {
        return $context->hasPlugin('shopware_product_detail');
    }

    public function provide(PageContext $context): iterable
    {
        // Use EXT:schema's TypeFactory to build a Product piece,
        // assign a deterministic @id, return it.
    }

    public function getPriority(): int
    {
        return 50;
    }
}
```

```yaml
# Services.yaml
Vendor\Ext\Piece\ProductPieceProvider:
  tags:
    - name: dkd_seo_graph.piece_provider
```

### Modifying an existing piece

Implement `GraphPieceModifierInterface` when you want to decorate a piece another provider created, rather than compete with it:

```php
final class AggregateRatingModifier implements GraphPieceModifierInterface
{
    public function supports(TypeInterface $piece, PageContext $context): bool
    {
        return $piece->getType() === 'Product';
    }

    public function modify(TypeInterface $piece, PageContext $context): void
    {
        // Add aggregateRating to the existing Product piece.
    }
}
```

### Events

For cases where the provider/modifier pattern is not enough, a PSR-14 event is dispatched before graph serialization:

- `Dkd\SeoGraph\Event\BeforeGraphAssembledEvent`
- `Dkd\SeoGraph\Event\AfterGraphAssembledEvent`

-----

## Validation

The graph validator runs against assembled graphs and reports issues. Runtime validation is configured per site in `seoGraph.validation`. Offline validation is available via CLI:

```bash
vendor/bin/typo3 seo:graph:validate --site=main --url=https://example.com/about/
```

Built-in rules in v0.1:

- `references_resolve`: every `@id` reference points to an entity in the graph or a configured external identifier
- `no_duplicate_ids`: no two entities share an `@id`
- `required_properties`: each piece type has its required properties set (configurable per type)

Planned for v0.2: `rich_results_article` (Google Rich Results constraints for Article), CI integration with non-zero exit codes.

-----

## Backend module

Web > SEO Graph shows the assembled graph for the selected page, including:

- The raw JSON-LD output
- Validation results for the current page
- A direct link to Google’s Rich Results Test for the page URL

A node-and-edge visualization is planned for v0.3.

-----

## Relationship to other extensions

- **`brotkrueml/schema`** (required): provides the schema.org type system and view helpers. This extension uses its `TypeFactory` and `SchemaManager` under the hood. If you are already using EXT:schema directly in custom extensions, those types can be integrated into the graph via the piece provider pattern.
- **`typo3/cms-seo`**: fully compatible. EXT:seo continues to handle meta tags, canonical URLs, sitemap, and hreflang. This extension only governs JSON-LD.
- **Planned companion packages**: `dkd/typo3-seo-graph-news` (EXT:news integration), `dkd/typo3-seo-graph-solr` (entity indexing).

-----

## Roadmap

- **v0.1** (current): core piece providers, runtime validation, configuration model, basic documentation
- **v0.2**: CLI validator, Person piece, modifier interface, Rich Results rule, backend module (raw view)
- **v0.3**: graph visualization, EXT:news companion, Solr companion
- **v1.0**: stable API, TER release, migration helpers from plain EXT:schema usage

-----

## Design principles

1. **Do not replace EXT:schema.** Compose with it. EXT:schema has years of accumulated correctness around the schema.org vocabulary; competing with it is a dead end.
1. **Opinionated defaults, permissive extension.** The default graph should be correct for 80% of TYPO3 sites without configuration. The extension points should make the remaining 20% straightforward.
1. **`@id` stability is non-negotiable.** The URI convention is the contract. Changing it is a breaking change.
1. **Validate, do not silently emit broken data.** In error mode, an invalid piece is dropped rather than shipped. Broken structured data is worse than missing structured data.
1. **Editor affordances matter.** The backend module is not an afterthought. Editors who cannot see the graph cannot reason about it.

-----

## License

GPL-2.0-or-later

-----

## Credits

Conceptual lineage: Joost de Valk’s Yoast SEO graph architecture, extracted in [`@jdevalk/seo-graph-core`](https://github.com/jdevalk/seo-graph). This extension ports the opinion, not the code, to the TYPO3 ecosystem.

Built on top of [`brotkrueml/schema`](https://github.com/brotkrueml/schema) by Chris Müller, which provides the underlying schema.org type system.

Maintained by [dkd Internet Service GmbH](https://www.dkd.de/).
