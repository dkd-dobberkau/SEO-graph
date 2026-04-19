# dkd/typo3-seo-graph v0.1 Design Spec

## Scope

v0.1: Core piece providers, runtime validation, site configuration, TCA fields, PSR-15 middleware. No CLI validator, no backend module, no modifier interface (all v0.2+).

## Requirements

- TYPO3 v12 LTS or v13 LTS
- PHP 8.2+
- `brotkrueml/schema` ^3.0 (type classes only, no SchemaManager usage)

## Architecture

Collector pattern with PSR-14 escape hatch:

```
Request → SeoGraphMiddleware → GraphAssembler → [PieceProviders...] → JSON-LD → Response
                                     ↓
                              GraphValidator
```

- **PieceProviders** are registered via Symfony DI tag `dkd_seo_graph.piece_provider`, sorted by priority
- **PSR-14 Events** (`BeforeGraphAssembledEvent`, `AfterGraphAssembledEvent`) fire around the provider loop
- **Middleware** renders JSON-LD into the response `<head>`, removing any existing EXT:schema JSON-LD blocks to avoid duplication
- **EXT:schema integration**: We use EXT:schema as a composer dependency but do NOT use its `SchemaManager` for rendering. We build our own `@graph` array and serialize it ourselves. EXT:schema type classes may be used internally but output is plain associative arrays.

## Extension Metadata

- **Composer name:** `dkd/typo3-seo-graph`
- **Extension key:** `seo_graph`
- **PHP namespace:** `Dkd\SeoGraph\`

## File Structure

```
Classes/
  Assembler/
    GraphAssembler.php
    PageContext.php
  Configuration/
    SeoGraphConfiguration.php
  Event/
    BeforeGraphAssembledEvent.php
    AfterGraphAssembledEvent.php
  Id/
    IdGenerator.php
  Middleware/
    SeoGraphMiddleware.php
  Piece/
    GraphPieceProviderInterface.php
    OrganizationPieceProvider.php
    WebSitePieceProvider.php
    WebPagePieceProvider.php
    BreadcrumbListPieceProvider.php
    ImageObjectPieceProvider.php
    ArticlePieceProvider.php
  Validation/
    GraphValidator.php
    ValidationResult.php
    Rule/
      ValidationRuleInterface.php
      ReferencesResolveRule.php
      NoDuplicateIdsRule.php
      RequiredPropertiesRule.php
Configuration/
  RequestMiddlewares.php
  Services.yaml
  TCA/
    Overrides/
      pages.php
Resources/
  Private/
    Language/
      locallang.xlf
ext_emconf.php
ext_localconf.php
composer.json
```

## Component Details

### PageContext (DTO)

Readonly DTO created per request by the middleware.

Properties:
- `site: Site` — TYPO3 Site object
- `pageRecord: array` — Full page record including TCA fields
- `pageUrl: string` — Canonical URL of the page
- `siteBaseUrl: string` — Base URL of the site
- `language: SiteLanguage` — Current language
- `configuration: SeoGraphConfiguration` — Parsed YAML config

Methods:
- `getSchemaType(): string` — Returns chosen schema type from TCA field (default: `WebPage`)
- `isArticleType(): bool` — Checks if Article/BlogPosting/NewsArticle
- `hasPlugin(string $name): bool` — Checks if a plugin is registered on the page
- `isGraphEnabled(): bool` — Checks the TCA exclude toggle

### GraphAssembler

- Receives all `GraphPieceProviderInterface` implementations via DI (tagged services, sorted by priority)
- `assemble(PageContext $context): array` — Iterates providers, collects pieces, dispatches events, returns `@graph` array
- Flow: `BeforeGraphAssembledEvent` → Providers (sorted by priority, `supports()` check) → `AfterGraphAssembledEvent` → optional Validation → Return

### IdGenerator

Stateless service for `@id` URI generation:

- `forSite(string $baseUrl, string $fragment): string` → `https://example.com/#organization`
- `forPage(string $pageUrl, string $fragment): string` → `https://example.com/about/#webpage`

URI convention follows the Joost de Valk pattern. This is a stable contract — changing it is a breaking change.

### SeoGraphConfiguration

- Reads `seoGraph` block from site config (`config/sites/{identifier}/config.yaml`)
- Access to: `publisher` (type, name, url, logo, sameAs), `defaultAuthor` (type, name, slug), `validation` (mode, rules)
- Fallback defaults when nothing is configured (empty publisher name = site title)

## Piece Providers

All providers implement `GraphPieceProviderInterface`:

```php
interface GraphPieceProviderInterface
{
    public function supports(PageContext $context): bool;
    public function provide(PageContext $context): iterable;
    public function getPriority(): int;
}
```

Each provider returns associative arrays representing JSON-LD nodes.

### OrganizationPieceProvider (Priority: 10)

- `supports()`: always `true`
- Reads publisher data from `SeoGraphConfiguration`
- Produces `Organization` + optional `ImageObject` for logo
- IDs: `{baseUrl}#organization`, `{baseUrl}#logo`

### WebSitePieceProvider (Priority: 20)

- `supports()`: always `true`
- Name from site config or seoGraph config
- References publisher via `{"@id": "...#organization"}`
- ID: `{baseUrl}#website`

### WebPagePieceProvider (Priority: 30)

- `supports()`: always `true` (when `isGraphEnabled()`)
- Reads schema type from TCA field (default: `WebPage`)
- Sets `isPartOf` → WebSite, `breadcrumb` → BreadcrumbList, `primaryImageOfPage` → ImageObject
- ID: `{pageUrl}#webpage`

### BreadcrumbListPieceProvider (Priority: 40)

- `supports()`: always `true` (except root page)
- Builds `itemListElement` from rootline
- ID: `{pageUrl}#breadcrumb`

### ImageObjectPieceProvider (Priority: 50)

- `supports()`: `true` when primary image is set (TCA field or first `media` image of page)
- ID: `{pageUrl}#primaryimage`

### ArticlePieceProvider (Priority: 60)

- `supports()`: `true` when `PageContext::isArticleType()`
- Sets `isPartOf` → WebPage, `mainEntityOfPage` → WebPage, `author` → Person/Org, `publisher` → Organization, `image` → ImageObject
- Author from TCA override or `defaultAuthor` from config
- ID: `{pageUrl}#article`

## Middleware

### SeoGraphMiddleware (PSR-15)

- Registered after `typo3/cms-frontend/site` and before `typo3/cms-frontend/shortcut-and-mountpoint-redirect`
- Checks: Is it a frontend request with `pageId`? Is `isGraphEnabled()`?
- Builds `PageContext`, calls `GraphAssembler::assemble()`
- Serializes `@graph` to JSON-LD `<script>` tag
- Scans response body for existing `<script type="application/ld+json">` from EXT:schema and removes them
- Injects own JSON-LD before `</head>`

## Validation

### GraphValidator

- Called by assembler when `validation.mode` is not `off`
- Receives finished `@graph` array
- Iterates registered `ValidationRuleInterface` implementations
- Returns `ValidationResult` with severity (warning/error) and messages
- `mode: error` → faulty pieces are removed from graph
- `mode: warning` → issues are logged via TYPO3 Logging Framework

```php
interface ValidationRuleInterface
{
    public function validate(array $graph, PageContext $context): array;
}
```

### Built-in Rules (v0.1)

- **ReferencesResolveRule**: Every `@id` reference points to an entity in the graph or a configured external identifier
- **NoDuplicateIdsRule**: No two entities share an `@id`
- **RequiredPropertiesRule**: Each piece type has its required properties set (configurable per type)

## TCA Fields

Four new fields on `pages` table, grouped in a "SEO Graph" tab:

| Field | Type | Description |
|-------|------|-------------|
| `tx_seograph_schema_type` | select | Schema type (WebPage, Article, BlogPosting, NewsArticle, FAQPage, ...) |
| `tx_seograph_primary_image` | file (FAL) | Primary image override |
| `tx_seograph_author` | input | Author name override |
| `tx_seograph_exclude` | check | Exclude page from graph emission |

## Site Configuration

Configuration in `config/sites/{identifier}/config.yaml`:

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
    mode: warning
    rules:
      - references_resolve
      - no_duplicate_ids
      - required_properties
```

## @id URI Convention

Follows the Joost de Valk pattern:

- Site-global entities: `{siteBaseUrl}#{fragment}` (e.g., `https://example.com/#organization`)
- Page-scoped entities: `{pageUrl}#{fragment}` (e.g., `https://example.com/about/#webpage`)

Fragments: `organization`, `logo`, `website`, `webpage`, `breadcrumb`, `primaryimage`, `article`

This is a stable contract. Changing the convention is a breaking change.

## Extension Points

1. **GraphPieceProviderInterface** — Primary extension mechanism. Register via `dkd_seo_graph.piece_provider` DI tag.
2. **BeforeGraphAssembledEvent** — Fired before providers run. Can modify PageContext or pre-populate pieces.
3. **AfterGraphAssembledEvent** — Fired after providers run. Can modify, add, or remove pieces before serialization.

## License

GPL-2.0-or-later
