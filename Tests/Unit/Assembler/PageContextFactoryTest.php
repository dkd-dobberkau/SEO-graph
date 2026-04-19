<?php

declare(strict_types=1);

namespace Dkd\SeoGraph\Tests\Unit\Assembler;

use Dkd\SeoGraph\Assembler\PageContextFactory;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Resource\FileRepository;
use TYPO3\CMS\Core\Routing\PageArguments;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use TYPO3\CMS\Frontend\Page\PageInformation;

final class PageContextFactoryTest extends TestCase
{
    #[Test]
    public function createFromRequestReturnsNullWithoutSite(): void
    {
        $fileRepository = $this->createMock(FileRepository::class);
        $factory = new PageContextFactory($fileRepository);

        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getAttribute')->willReturn(null);

        self::assertNull($factory->createFromRequest($request));
    }

    #[Test]
    public function createFromRequestReturnsNullWithoutRouting(): void
    {
        $fileRepository = $this->createMock(FileRepository::class);
        $factory = new PageContextFactory($fileRepository);

        $site = $this->createMock(Site::class);
        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getAttribute')->willReturnCallback(fn(string $name) => match ($name) {
            'site' => $site,
            default => null,
        });

        self::assertNull($factory->createFromRequest($request));
    }

    #[Test]
    public function createFromRequestReturnsNullWithoutPageInformation(): void
    {
        $fileRepository = $this->createMock(FileRepository::class);
        $factory = new PageContextFactory($fileRepository);

        $site = $this->createMock(Site::class);
        $routing = $this->createMock(PageArguments::class);
        $language = $this->createMock(SiteLanguage::class);

        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getAttribute')->willReturnCallback(fn(string $name) => match ($name) {
            'site' => $site,
            'routing' => $routing,
            'language' => $language,
            default => null,
        });

        self::assertNull($factory->createFromRequest($request));
    }
}
