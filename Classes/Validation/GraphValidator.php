<?php

declare(strict_types=1);

namespace Dkd\SeoGraph\Validation;

use Dkd\SeoGraph\Assembler\PageContext;

/**
 * Minimal stub for GraphValidator — full implementation in Task 14.
 */
class GraphValidator
{
    /**
     * @param array<int, array<string, mixed>> $graph
     * @param PageContext $context
     * @return ValidationResult[]
     */
    public function validate(array $graph, PageContext $context): array
    {
        return [];
    }

    /**
     * @param array<int, array<string, mixed>> $graph
     * @param PageContext $context
     * @param string $mode
     * @return array<int, array<string, mixed>>
     */
    public function validateAndFilter(array $graph, PageContext $context, string $mode): array
    {
        return $graph;
    }
}
