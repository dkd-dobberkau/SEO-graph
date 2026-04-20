<?php

declare(strict_types=1);

namespace Dkd\SeoGraph\Tests\Unit\Assembler;

use Dkd\SeoGraph\Assembler\PageContext;
use Dkd\SeoGraph\Assembler\StandalonePageContextFactory;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\UriInterface;
use TYPO3\CMS\Core\Resource\FileReference;
use TYPO3\CMS\Core\Resource\FileRepository;
use TYPO3\CMS\Core\Routing\RouterInterface;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;

final class StandalonePageContextFactoryTest extends TestCase
{
    private function createMockSite(string $base = 'https://example.com/'): Site
    {
        $uri = $this->createMock(UriInterface::class);
        $uri->method('__toString')->willReturn($base);

        $router = $this->createMock(RouterInterface::class);
        $router->method('generateUri')->willReturn($uri);

        $site = $this->createMock(Site::class);
        $site->method('getBase')->willReturn($uri);
        $site->method('getConfiguration')->willReturn([
            'websiteTitle' => 'Example Site',
            'seoGraph' => [],
        ]);
        $site->method('getRouter')->willReturn($router);

        return $site;
    }

    private function createMockLanguage(): SiteLanguage
    {
        return $this->createMock(SiteLanguage::class);
    }

    #[Test]
    public function createForPageReturnsPageContext(): void
    {
        $pageRecord = [
            'uid' => 42,
            'title' => 'About Us',
            'tx_seograph_schema_type' => '',
            'tx_seograph_exclude' => 0,
            'tx_seograph_author' => '',
        ];

        $pageRepository = $this->createMock(\TYPO3\CMS\Core\Domain\Repository\PageRepository::class);
        $pageRepository->method('getPage')->with(42)->willReturn($pageRecord);

        $fileRepository = $this->createMock(FileRepository::class);
        $fileRepository->method('findByRelation')->willReturn([]);

        $site = $this->createMockSite();
        $language = $this->createMockLanguage();

        $factory = new StandalonePageContextFactory($fileRepository, $pageRepository);
        $context = $factory->createForPage(42, $site, $language);

        self::assertInstanceOf(PageContext::class, $context);
        self::assertSame(42, $context->pageRecord['uid']);
        self::assertSame('About Us', $context->pageRecord['title']);
    }

    #[Test]
    public function createForPageSetsCorrectSiteBaseUrl(): void
    {
        $pageRecord = [
            'uid' => 1,
            'title' => 'Home',
            'tx_seograph_schema_type' => '',
            'tx_seograph_exclude' => 0,
            'tx_seograph_author' => '',
        ];

        $pageRepository = $this->createMock(\TYPO3\CMS\Core\Domain\Repository\PageRepository::class);
        $pageRepository->method('getPage')->willReturn($pageRecord);

        $fileRepository = $this->createMock(FileRepository::class);
        $fileRepository->method('findByRelation')->willReturn([]);

        $site = $this->createMockSite('https://example.com/');
        $language = $this->createMockLanguage();

        $factory = new StandalonePageContextFactory($fileRepository, $pageRepository);
        $context = $factory->createForPage(1, $site, $language);

        self::assertSame('https://example.com/', $context->siteBaseUrl);
    }

    #[Test]
    public function createForPageResolvesMediaUrlsViaFal(): void
    {
        $pageRecord = [
            'uid' => 5,
            'title' => 'Gallery',
            'tx_seograph_schema_type' => '',
            'tx_seograph_exclude' => 0,
            'tx_seograph_author' => '',
        ];

        $fileRef = $this->createMock(FileReference::class);
        $fileRef->method('getPublicUrl')->willReturn('https://example.com/fileadmin/hero.jpg');

        $pageRepository = $this->createMock(\TYPO3\CMS\Core\Domain\Repository\PageRepository::class);
        $pageRepository->method('getPage')->willReturn($pageRecord);

        $fileRepository = $this->createMock(FileRepository::class);
        $fileRepository->method('findByRelation')
            ->willReturnCallback(function (string $table, string $field, int $uid) use ($fileRef) {
                if ($field === 'tx_seograph_primary_image') {
                    return [$fileRef];
                }
                return [];
            });

        $site = $this->createMockSite();
        $language = $this->createMockLanguage();

        $factory = new StandalonePageContextFactory($fileRepository, $pageRepository);
        $context = $factory->createForPage(5, $site, $language);

        self::assertSame('https://example.com/fileadmin/hero.jpg', $context->pageRecord['tx_seograph_primary_image']);
    }
}
