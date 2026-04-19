<?php

declare(strict_types=1);

namespace Dkd\SeoGraph\Piece;

use Dkd\SeoGraph\Assembler\PageContext;
use Dkd\SeoGraph\Id\IdGenerator;

final class OrganizationPieceProvider implements GraphPieceProviderInterface
{
    public function __construct(
        private readonly IdGenerator $idGenerator,
    ) {}

    public function supports(PageContext $context): bool
    {
        return true;
    }

    public function provide(PageContext $context): iterable
    {
        $config = $context->configuration;
        $baseUrl = $context->siteBaseUrl;

        $org = [
            '@type' => $config->getPublisherType(),
            '@id' => $this->idGenerator->forSite($baseUrl, 'organization'),
            'name' => $config->getPublisherName(),
        ];

        $url = $config->getPublisherUrl();
        if ($url !== '') {
            $org['url'] = $url;
        }

        $sameAs = $config->getPublisherSameAs();
        if ($sameAs !== []) {
            $org['sameAs'] = $sameAs;
        }

        $logo = $config->getPublisherLogo();
        if ($logo !== '') {
            $org['logo'] = ['@id' => $this->idGenerator->forSite($baseUrl, 'logo')];
            yield $org;

            yield [
                '@type' => 'ImageObject',
                '@id' => $this->idGenerator->forSite($baseUrl, 'logo'),
                'url' => $logo,
            ];
            return;
        }

        yield $org;
    }

    public function getPriority(): int
    {
        return 10;
    }
}
