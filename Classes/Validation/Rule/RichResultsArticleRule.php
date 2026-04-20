<?php

declare(strict_types=1);

namespace Dkd\SeoGraph\Validation\Rule;

use Dkd\SeoGraph\Assembler\PageContext;
use Dkd\SeoGraph\Validation\ValidationResult;

/**
 * Validates Article/BlogPosting/NewsArticle pieces against Google Rich Results requirements.
 *
 * @see https://developers.google.com/search/docs/appearance/structured-data/article
 */
final class RichResultsArticleRule implements ValidationRuleInterface
{
    private const ARTICLE_TYPES = ['Article', 'BlogPosting', 'NewsArticle'];
    private const MAX_HEADLINE_LENGTH = 110;

    public function validate(array $graph, PageContext $context): array
    {
        $results = [];

        foreach ($graph as $piece) {
            $type = $piece['@type'] ?? '';
            if (!in_array($type, self::ARTICLE_TYPES, true)) {
                continue;
            }
            $results = [...$results, ...$this->validateArticlePiece($piece, $type)];
        }

        return $results;
    }

    /** @return ValidationResult[] */
    private function validateArticlePiece(array $piece, string $type): array
    {
        $results = [];

        // headline: required, max 110 characters
        if (!isset($piece['headline']) || $piece['headline'] === '') {
            $results[] = ValidationResult::warning(
                sprintf('Rich Results: "%s" is missing required property "headline"', $type),
                $type,
            );
        } elseif (mb_strlen((string)$piece['headline']) > self::MAX_HEADLINE_LENGTH) {
            $results[] = ValidationResult::warning(
                sprintf(
                    'Rich Results: "%s" headline exceeds %d characters (current: %d)',
                    $type,
                    self::MAX_HEADLINE_LENGTH,
                    mb_strlen((string)$piece['headline']),
                ),
                $type,
            );
        }

        // image: required
        if (!isset($piece['image']) || $piece['image'] === '' || $piece['image'] === []) {
            $results[] = ValidationResult::warning(
                sprintf('Rich Results: "%s" is missing required property "image"', $type),
                $type,
            );
        }

        // datePublished: required
        if (!isset($piece['datePublished']) || $piece['datePublished'] === '') {
            $results[] = ValidationResult::warning(
                sprintf('Rich Results: "%s" is missing required property "datePublished"', $type),
                $type,
            );
        }

        // author: required — accept @id reference OR inline object with name or @id
        if (!$this->hasValidAuthor($piece)) {
            $results[] = ValidationResult::warning(
                sprintf('Rich Results: "%s" is missing required property "author" (must have @id or name)', $type),
                $type,
            );
        }

        return $results;
    }

    private function hasValidAuthor(array $piece): bool
    {
        $author = $piece['author'] ?? null;
        if ($author === null || $author === [] || $author === '') {
            return false;
        }

        if (!is_array($author)) {
            return false;
        }

        // Accepts: {"@id": "..."} or {"@type": "Person", "name": "..."} or {"name": "..."}
        return isset($author['@id']) || isset($author['name']);
    }
}
