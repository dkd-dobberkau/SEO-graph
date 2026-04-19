<?php

declare(strict_types=1);

namespace Dkd\SeoGraph\Validation\Rule;

use Dkd\SeoGraph\Assembler\PageContext;
use Dkd\SeoGraph\Validation\ValidationResult;

final class NoDuplicateIdsRule implements ValidationRuleInterface
{
    public function validate(array $graph, PageContext $context): array
    {
        $seen = [];
        $results = [];

        foreach ($graph as $piece) {
            $id = $piece['@id'] ?? null;
            if ($id === null) {
                continue;
            }
            if (isset($seen[$id])) {
                $results[] = ValidationResult::error(
                    sprintf('Duplicate @id "%s" found in graph', $id),
                    $piece['@type'] ?? 'unknown',
                );
            }
            $seen[$id] = true;
        }

        return $results;
    }
}
