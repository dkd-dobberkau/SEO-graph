<?php

declare(strict_types=1);

namespace Dkd\SeoGraph\Tests\Unit\Event;

use Dkd\SeoGraph\Assembler\PageContext;
use Dkd\SeoGraph\Configuration\SeoGraphConfiguration;
use Dkd\SeoGraph\Event\BeforeGraphAssembledEvent;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;

final class BeforeGraphAssembledEventTest extends TestCase
{
    #[Test]
    public function piecesCanBePrePopulated(): void
    {
        $context = new PageContext(
            site: $this->createMock(Site::class),
            pageRecord: ['tx_seograph_schema_type' => '', 'tx_seograph_exclude' => 0],
            pageUrl: 'https://example.com/',
            siteBaseUrl: 'https://example.com/',
            language: $this->createMock(SiteLanguage::class),
            configuration: new SeoGraphConfiguration([], 'Site'),
        );
        $event = new BeforeGraphAssembledEvent($context);
        self::assertSame([], $event->getPieces());
        $event->addPiece(['@type' => 'Thing', '@id' => 'https://example.com/#thing']);
        self::assertCount(1, $event->getPieces());
    }
}
