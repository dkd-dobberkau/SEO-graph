# dkd/t3-seo-graph v0.2 Design Spec

## Scope

v0.2: GraphPieceModifierInterface, PersonPieceProvider, CLI validator, Rich Results validation rule, backend module (raw JSON-LD view).

## Requirements

- All v0.1 requirements (TYPO3 v12/v13, PHP 8.2+, brotkrueml/schema ^3.0)
- No new external dependencies

## Features

### 1. GraphPieceModifierInterface

A new extension point for decorating pieces that another provider created.

```php
interface GraphPieceModifierInterface
{
    public function supports(array $piece, PageContext $context): bool;
    public function modify(array $piece, PageContext $context): array;
    public function getPriority(): int;
}
```

- `supports()` checks `$piece['@type']` to decide whether to modify
- `modify()` receives and returns the piece array (immutable pattern, no by-reference)
- DI tag: `dkd_seo_graph.piece_modifier`
- Modifiers are sorted by priority (ascending), same as providers

**Assembler flow changes from:**
```
BeforeEvent → Providers → AfterEvent → Validation
```
**To:**
```
BeforeEvent → Providers → Modifiers (per piece) → AfterEvent → Validation
```

The modifier loop iterates over each piece and applies all matching modifiers:

```php
foreach ($pieces as $index => $piece) {
    foreach ($this->modifiers as $modifier) {
        if ($modifier->supports($piece, $context)) {
            $pieces[$index] = $modifier->modify($piece, $context);
            $piece = $pieces[$index];
        }
    }
}
```

### 2. PersonPieceProvider

A new provider that emits a deduplicated Person entity with a stable `@id`.

- **Priority:** 55 (after ImageObject, before Article)
- `supports()`: true when `defaultAuthor` is configured OR `tx_seograph_author` is set in TCA
- Produces a Person entity referenced by Article pieces

**@id convention:**
- Config author: `{baseUrl}#author-{slug}` (e.g., `https://example.com/#author-jane-doe`)
- TCA override: `{baseUrl}#author-{slugified-name}` (name converted to slug)

**Output:**
```json
{
    "@type": "Person",
    "@id": "https://example.com/#author-jane-doe",
    "name": "Jane Doe"
}
```

**IdGenerator gets a new method:**
- `forAuthor(string $baseUrl, string $slug): string` → `https://example.com/#author-jane-doe`

**ArticlePieceProvider changes:**
- Instead of inline `{"@type": "Person", "name": "..."}`, references via `{"@id": "...#author-{slug}"}`
- Uses the same slug logic as PersonPieceProvider

**Slug generation:** A private helper method `slugify(string $name): string` converts names to URL-safe slugs (lowercase, hyphens, strip special chars). Shared between PersonPieceProvider and ArticlePieceProvider, extracted to a trait or utility if needed.

### 3. CLI Validator Command

**Command:** `seo:graph:validate`

**Options:**
- `--site=<identifier>` (required) — Site identifier
- `--page=<uid>` (optional) — Validate single page. Without: all pages of the site.
- `--format=text|json` (optional, default: `text`) — Output format for CI integration

**Flow:**
1. Load site via `SiteFinder`
2. Determine pages: single page by UID or all pages via `PageRepository`
3. Per page: build `PageContext` via `StandalonePageContextFactory` (no frontend request needed)
4. Call `GraphAssembler::assemble()`, then `GraphValidator::validate()` separately to collect issues
5. Output results

**Exit codes:**
- `0` — No errors
- `1` — Validation errors found
- `2` — Technical error (site not found, etc.)

**Text output:**
```
Validating site "main" (42 pages)...

Page 5 "About us" (https://example.com/about/)
  ⚠ Missing required property "name" on Organization
  ✗ Duplicate @id "https://example.com/#organization"

Page 12 "Blog" (https://example.com/blog/)
  ✓ OK

Result: 1 error, 1 warning across 42 pages
```

**JSON output:**
```json
{
    "pages": [
        {
            "uid": 5,
            "title": "About us",
            "url": "https://example.com/about/",
            "issues": [
                {"severity": "warning", "message": "Missing required property \"name\" on Organization", "type": "Organization"},
                {"severity": "error", "message": "Duplicate @id", "type": "Organization"}
            ]
        }
    ],
    "summary": {
        "errors": 1,
        "warnings": 1,
        "pages_checked": 42
    }
}
```

**New class:** `Classes/Command/ValidateGraphCommand.php`

### 4. Rich Results Article Validation Rule

**New rule:** `RichResultsArticleRule`

Checks whether Article pieces meet Google Rich Results requirements:

- `headline` must be present (max 110 characters)
- `image` must be present (as reference or inline)
- `datePublished` must be present
- `author` must be present (with `name` or as `@id` reference)

**Implementation:**
- Only applies to `@type` in `[Article, BlogPosting, NewsArticle]`
- Returns `ValidationResult::warning()` per missing/invalid field
- Rule name in config: `rich_results_article`
- DI tag: `dkd_seo_graph.validation_rule` (same as existing rules)

### 5. Backend Module

**Module registration:** Under "Web" as "SEO Graph", with the page tree as navigation context.

**TYPO3 v13 pattern:**
- Controller: `Classes/Controller/SeoGraphController.php`
- Template: `Resources/Private/Templates/SeoGraph/Index.html`
- Registration: `Configuration/Backend/Modules.php`

**Functionality (v0.2 = raw view):**

For the selected page in the page tree:

1. **Raw JSON-LD** — The complete `@graph` JSON, displayed in a `<pre><code>` block
2. **Validation results** — List of warnings/errors for this page
3. **Rich Results Test link** — Button linking to `https://search.google.com/test/rich-results?url={pageUrl}`

**Controller logic:**
- Reads page ID from request (`id` parameter from page tree)
- Builds `PageContext` via `StandalonePageContextFactory`
- Calls `GraphAssembler::assemble()` and `GraphValidator::validate()`
- Passes JSON-LD string + validation results to template

## StandalonePageContextFactory

Both the CLI command and the backend module need a `PageContext` without a frontend request. A shared factory handles this:

```php
final class StandalonePageContextFactory
{
    public function __construct(
        private readonly FileRepository $fileRepository,
        private readonly PageRepository $pageRepository,
    ) {}

    public function createForPage(int $pageUid, Site $site, SiteLanguage $language): PageContext
}
```

This uses:
- `PageRepository` for the page record
- `RootlineUtility` for the rootline
- `FileRepository` for FAL resolution
- `Site::getRouter()` for URL generation

## New File Structure (additions to v0.1)

```
Classes/
  Assembler/
    StandalonePageContextFactory.php    # New: shared factory for CLI + Backend
  Command/
    ValidateGraphCommand.php            # New: CLI validator
  Controller/
    SeoGraphController.php              # New: Backend module controller
  Piece/
    GraphPieceModifierInterface.php     # New: modifier interface
    PersonPieceProvider.php             # New: Person piece
  Validation/
    Rule/
      RichResultsArticleRule.php        # New: Rich Results rule
Configuration/
  Backend/
    Modules.php                         # New: Backend module registration
Resources/
  Private/
    Templates/
      SeoGraph/
        Index.html                      # New: Backend module template
    Language/
      locallang.xlf                     # Updated: new labels
```

## Changes to Existing Files

- `Classes/Assembler/GraphAssembler.php` — Add modifier loop between providers and AfterEvent
- `Classes/Piece/ArticlePieceProvider.php` — Reference Person by `@id` instead of inline object
- `Classes/Id/IdGenerator.php` — Add `forAuthor()` method
- `Configuration/Services.yaml` — Add modifier and new rule tags, register command + controller
- `ext_emconf.php` — Bump version to 0.2.0

## @id URI Convention (additions)

- Author: `{baseUrl}#author-{slug}` (e.g., `https://example.com/#author-jane-doe`)

## License

GPL-2.0-or-later
