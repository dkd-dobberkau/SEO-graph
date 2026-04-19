<?php

declare(strict_types=1);

namespace Dkd\SeoGraph\Validation\Rule;

use Dkd\SeoGraph\Assembler\PageContext;
use Dkd\SeoGraph\Validation\ValidationResult;

final class ReferencesResolveRule implements ValidationRuleInterface
{
    public function validate(array $graph, PageContext $context): array
    {
        $knownIds = [];
        foreach ($graph as $piece) {
            if (isset($piece['@id'])) {
                $knownIds[$piece['@id']] = true;
            }
        }

        $results = [];
        foreach ($graph as $piece) {
            $references = $this->extractReferences($piece);
            foreach ($references as $refId) {
                if (!isset($knownIds[$refId])) {
                    $results[] = ValidationResult::warning(
                        sprintf('Unresolved @id reference "%s" in %s', $refId, $piece['@type'] ?? 'unknown'),
                        $piece['@type'] ?? 'unknown',
                    );
                }
            }
        }

        return $results;
    }

    /** @return string[] */
    private function extractReferences(array $piece): array
    {
        $refs = [];
        foreach ($piece as $key => $value) {
            if ($key === '@id' || $key === '@type' || $key === '@context') {
                continue;
            }
            if (is_array($value) && isset($value['@id']) && count($value) === 1) {
                $refs[] = $value['@id'];
            }
        }
        return $refs;
    }
}
