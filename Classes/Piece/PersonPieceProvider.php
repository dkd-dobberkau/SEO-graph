<?php

declare(strict_types=1);

namespace Dkd\SeoGraph\Piece;

use Dkd\SeoGraph\Assembler\PageContext;
use Dkd\SeoGraph\Id\IdGenerator;

final class PersonPieceProvider implements GraphPieceProviderInterface
{
    public function __construct(
        private readonly IdGenerator $idGenerator,
    ) {}

    public function supports(PageContext $context): bool
    {
        $tcaAuthor = $context->pageRecord['tx_seograph_author'] ?? '';
        if ($tcaAuthor !== '') {
            return true;
        }

        return $context->configuration->getDefaultAuthorName() !== null;
    }

    public function provide(PageContext $context): iterable
    {
        if (!$this->supports($context)) {
            return;
        }

        $baseUrl = $context->siteBaseUrl;
        [$name, $slug] = $this->resolveNameAndSlug($context);

        yield [
            '@type' => 'Person',
            '@id'   => $this->idGenerator->forAuthor($baseUrl, $slug),
            'name'  => $name,
        ];
    }

    public function getPriority(): int
    {
        return 55;
    }

    /**
     * Returns [name, slug] for the author.
     * TCA field takes precedence over site configuration.
     *
     * @return array{0: string, 1: string}
     */
    private function resolveNameAndSlug(PageContext $context): array
    {
        $tcaAuthor = $context->pageRecord['tx_seograph_author'] ?? '';
        if ($tcaAuthor !== '') {
            return [$tcaAuthor, $this->idGenerator->slugify($tcaAuthor)];
        }

        $config = $context->configuration;
        $name = (string)$config->getDefaultAuthorName();
        $slug = $config->getDefaultAuthorSlug();
        if ($slug === '') {
            $slug = $this->idGenerator->slugify($name);
        }

        return [$name, $slug];
    }
}
