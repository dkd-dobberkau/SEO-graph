<?php

declare(strict_types=1);

namespace Dkd\SeoGraph\Tests\Unit\Piece;

use Dkd\SeoGraph\Assembler\PageContext;
use Dkd\SeoGraph\Configuration\SeoGraphConfiguration;
use Dkd\SeoGraph\Id\IdGenerator;
use Dkd\SeoGraph\Piece\PersonPieceProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;

final class PersonPieceProviderTest extends TestCase
{
    private IdGenerator $idGenerator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->idGenerator = new IdGenerator();
    }

    private function createContext(
        string $tcaAuthor = '',
        array $authorConfig = [],
        string $pageUrl = 'https://example.com/blog/post/',
    ): PageContext {
        $siteConfig = [];
        if ($authorConfig !== []) {
            $siteConfig = ['seoGraph' => ['defaultAuthor' => $authorConfig]];
        }
        return new PageContext(
            site: $this->createMock(Site::class),
            pageRecord: [
                'uid' => 10,
                'title' => 'Test Post',
                'tx_seograph_schema_type' => 'BlogPosting',
                'tx_seograph_exclude' => 0,
                'tx_seograph_author' => $tcaAuthor,
            ],
            pageUrl: $pageUrl,
            siteBaseUrl: 'https://example.com/',
            language: $this->createMock(SiteLanguage::class),
            configuration: new SeoGraphConfiguration($siteConfig, 'Site'),
        );
    }

    #[Test]
    public function priorityIsFiftyFive(): void
    {
        $subject = new PersonPieceProvider($this->idGenerator);
        self::assertSame(55, $subject->getPriority());
    }

    #[Test]
    public function supportsReturnsFalseWhenNoAuthorConfigured(): void
    {
        $subject = new PersonPieceProvider($this->idGenerator);
        self::assertFalse($subject->supports($this->createContext()));
    }

    #[Test]
    public function supportsReturnsTrueWhenTcaAuthorSet(): void
    {
        $subject = new PersonPieceProvider($this->idGenerator);
        self::assertTrue($subject->supports($this->createContext('Jane Doe')));
    }

    #[Test]
    public function supportsReturnsTrueWhenDefaultAuthorConfigured(): void
    {
        $subject = new PersonPieceProvider($this->idGenerator);
        $context = $this->createContext('', ['name' => 'Jane Doe', 'slug' => 'jane-doe', 'type' => 'Person']);
        self::assertTrue($subject->supports($context));
    }

    #[Test]
    public function provideEmitsPersonWithStableIdFromTcaAuthor(): void
    {
        $subject = new PersonPieceProvider($this->idGenerator);
        $pieces = [...$subject->provide($this->createContext('Jane Doe'))];

        self::assertCount(1, $pieces);
        $person = $pieces[0];
        self::assertSame('Person', $person['@type']);
        self::assertSame('https://example.com/#author-jane-doe', $person['@id']);
        self::assertSame('Jane Doe', $person['name']);
    }

    #[Test]
    public function provideUsesSlugFromConfigWhenAvailable(): void
    {
        $subject = new PersonPieceProvider($this->idGenerator);
        $context = $this->createContext('', [
            'name' => 'Jane Doe',
            'slug' => 'jane-doe',
            'type' => 'Person',
        ]);
        $pieces = [...$subject->provide($context)];

        $person = $pieces[0];
        self::assertSame('https://example.com/#author-jane-doe', $person['@id']);
        self::assertSame('Jane Doe', $person['name']);
    }

    #[Test]
    public function provideUsesSlugifiedNameWhenNoSlugInConfig(): void
    {
        $subject = new PersonPieceProvider($this->idGenerator);
        $context = $this->createContext('', [
            'name' => 'Hans Müller',
            'type' => 'Person',
        ]);
        $pieces = [...$subject->provide($context)];

        $person = $pieces[0];
        self::assertSame('https://example.com/#author-hans-muller', $person['@id']);
        self::assertSame('Hans Müller', $person['name']);
    }

    #[Test]
    public function providePrefersTcaAuthorOverDefaultConfig(): void
    {
        $subject = new PersonPieceProvider($this->idGenerator);
        $context = $this->createContext('John Smith', [
            'name' => 'Jane Doe',
            'slug' => 'jane-doe',
            'type' => 'Person',
        ]);
        $pieces = [...$subject->provide($context)];

        $person = $pieces[0];
        self::assertSame('John Smith', $person['name']);
        self::assertSame('https://example.com/#author-john-smith', $person['@id']);
    }

    #[Test]
    public function provideYieldsNothingWhenNotSupported(): void
    {
        $subject = new PersonPieceProvider($this->idGenerator);
        $pieces = [...$subject->provide($this->createContext())];
        self::assertSame([], $pieces);
    }
}
