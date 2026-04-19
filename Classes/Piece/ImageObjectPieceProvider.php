<?php

declare(strict_types=1);

namespace Dkd\SeoGraph\Piece;

use Dkd\SeoGraph\Assembler\PageContext;
use Dkd\SeoGraph\Id\IdGenerator;

final class ImageObjectPieceProvider implements GraphPieceProviderInterface
{
    public function __construct(
        private readonly IdGenerator $idGenerator,
    ) {}

    public function supports(PageContext $context): bool
    {
        return $this->resolveImageUrl($context) !== null;
    }

    public function provide(PageContext $context): iterable
    {
        $imageUrl = $this->resolveImageUrl($context);
        if ($imageUrl === null) {
            return;
        }

        yield [
            '@type' => 'ImageObject',
            '@id' => $this->idGenerator->forPage($context->pageUrl, 'primaryimage'),
            'url' => $imageUrl,
        ];
    }

    public function getPriority(): int
    {
        return 50;
    }

    private function resolveImageUrl(PageContext $context): ?string
    {
        $primaryImage = $context->pageRecord['tx_seograph_primary_image'] ?? '';
        if ($primaryImage !== '') {
            return $primaryImage;
        }

        $media = $context->pageRecord['_media'] ?? [];
        if ($media !== []) {
            return $media[0];
        }

        return null;
    }
}
