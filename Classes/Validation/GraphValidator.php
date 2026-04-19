<?php

declare(strict_types=1);

namespace Dkd\SeoGraph\Validation;

use Dkd\SeoGraph\Assembler\PageContext;
use Dkd\SeoGraph\Validation\Rule\ValidationRuleInterface;
use Psr\Log\LoggerInterface;

final class GraphValidator
{
    /** @var ValidationRuleInterface[] */
    private readonly array $rules;

    /**
     * @param iterable<ValidationRuleInterface> $rules
     */
    public function __construct(
        iterable $rules,
        private readonly LoggerInterface $logger,
    ) {
        $this->rules = [...$rules];
    }

    /** @return ValidationResult[] */
    public function validate(array $graph, PageContext $context): array
    {
        $results = [];
        foreach ($this->rules as $rule) {
            $results = [...$results, ...$rule->validate($graph, $context)];
        }
        return $results;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function validateAndFilter(array $graph, PageContext $context, string $mode): array
    {
        $results = $this->validate($graph, $context);

        if ($results === []) {
            return $graph;
        }

        foreach ($results as $result) {
            $this->logger->log(
                $result->severity === 'error' ? 'error' : 'warning',
                '[SEO Graph] ' . $result->message,
                ['type' => $result->affectedType],
            );
        }

        if ($mode === 'error') {
            $errorTypes = [];
            foreach ($results as $result) {
                if ($result->severity === 'error') {
                    $errorTypes[$result->affectedType] = true;
                }
            }

            $seen = [];
            $filtered = [];
            foreach ($graph as $piece) {
                $type = $piece['@type'] ?? '';
                $id = $piece['@id'] ?? '';
                $key = $type . '|' . $id;

                if (isset($errorTypes[$type]) && isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                $filtered[] = $piece;
            }
            return $filtered;
        }

        return $graph;
    }
}
