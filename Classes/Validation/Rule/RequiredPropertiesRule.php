<?php

declare(strict_types=1);

namespace Dkd\SeoGraph\Validation\Rule;

use Dkd\SeoGraph\Assembler\PageContext;
use Dkd\SeoGraph\Validation\ValidationResult;

final class RequiredPropertiesRule implements ValidationRuleInterface
{
    private const REQUIRED = [
        'Organization' => ['name'],
        'WebSite' => ['name', 'url'],
        'WebPage' => ['name', 'url'],
        'Article' => ['headline'],
        'BlogPosting' => ['headline'],
        'NewsArticle' => ['headline'],
        'BreadcrumbList' => ['itemListElement'],
        'ImageObject' => ['url'],
    ];

    public function validate(array $graph, PageContext $context): array
    {
        $results = [];

        foreach ($graph as $piece) {
            $type = $piece['@type'] ?? '';
            $required = self::REQUIRED[$type] ?? [];

            foreach ($required as $property) {
                if (!isset($piece[$property]) || $piece[$property] === '' || $piece[$property] === []) {
                    $results[] = ValidationResult::warning(
                        sprintf('Missing required property "%s" on %s', $property, $type),
                        $type,
                    );
                }
            }
        }

        return $results;
    }
}
