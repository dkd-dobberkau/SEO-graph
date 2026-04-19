<?php

declare(strict_types=1);

namespace Dkd\SeoGraph\Validation\Rule;

use Dkd\SeoGraph\Assembler\PageContext;
use Dkd\SeoGraph\Validation\ValidationResult;

interface ValidationRuleInterface
{
    /** @return ValidationResult[] */
    public function validate(array $graph, PageContext $context): array;
}
