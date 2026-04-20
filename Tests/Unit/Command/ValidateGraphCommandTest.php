<?php

declare(strict_types=1);

namespace Dkd\SeoGraph\Tests\Unit\Command;

use Dkd\SeoGraph\Assembler\GraphAssembler;
use Dkd\SeoGraph\Assembler\PageContext;
use Dkd\SeoGraph\Assembler\StandalonePageContextFactory;
use Dkd\SeoGraph\Command\ValidateGraphCommand;
use Dkd\SeoGraph\Configuration\SeoGraphConfiguration;
use Dkd\SeoGraph\Validation\GraphValidator;
use Dkd\SeoGraph\Validation\Rule\ValidationRuleInterface;
use Dkd\SeoGraph\Validation\ValidationResult;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\UriInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use TYPO3\CMS\Core\Domain\Repository\PageRepository;
use TYPO3\CMS\Core\Resource\FileRepository;
use TYPO3\CMS\Core\Routing\RouterInterface;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use TYPO3\CMS\Core\Site\SiteFinder;

final class ValidateGraphCommandTest extends TestCase
{
    /**
     * Creates a Site mock that is fully configured for use with StandalonePageContextFactory.
     */
    private function createFullSiteMock(string $base = 'https://example.com/'): Site
    {
        $uri = $this->createMock(UriInterface::class);
        $uri->method('__toString')->willReturn($base);

        $router = $this->createMock(RouterInterface::class);
        $router->method('generateUri')->willReturn($uri);

        $site = $this->createMock(Site::class);
        $site->method('getBase')->willReturn($uri);
        $site->method('getConfiguration')->willReturn(['websiteTitle' => 'Test Site']);
        $site->method('getRouter')->willReturn($router);
        $site->method('getDefaultLanguage')->willReturn($this->createMock(SiteLanguage::class));

        return $site;
    }

    /**
     * Creates a GraphAssembler that always returns the given pieces regardless of context.
     */
    private function createAssembler(array $pieces): GraphAssembler
    {
        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher->method('dispatch')->willReturnCallback(function ($event) use ($pieces) {
            if ($event instanceof \Dkd\SeoGraph\Event\BeforeGraphAssembledEvent) {
                return $event;
            }
            if ($event instanceof \Dkd\SeoGraph\Event\AfterGraphAssembledEvent) {
                return new \Dkd\SeoGraph\Event\AfterGraphAssembledEvent(
                    $event->getContext(),
                    $pieces,
                );
            }
            return $event;
        });

        $validator = new GraphValidator([], new NullLogger());
        return new GraphAssembler([], [], $dispatcher, $validator);
    }

    /**
     * Creates a GraphValidator that always returns the given results.
     */
    private function createValidatorWithResults(array $results): GraphValidator
    {
        $rule = $this->createMock(ValidationRuleInterface::class);
        $rule->method('validate')->willReturn($results);
        return new GraphValidator([$rule], new NullLogger());
    }

    /**
     * Creates a StandalonePageContextFactory backed by mock repositories.
     */
    private function createContextFactory(array $pageRecord): StandalonePageContextFactory
    {
        $fileRepository = $this->createMock(FileRepository::class);
        $fileRepository->method('findByRelation')->willReturn([]);

        $pageRepository = $this->createMock(PageRepository::class);
        $pageRepository->method('getPage')->willReturn($pageRecord);

        return new StandalonePageContextFactory($fileRepository, $pageRepository);
    }

    #[Test]
    public function commandReturnsExitCode2WhenSiteNotFound(): void
    {
        $siteFinder = $this->createMock(SiteFinder::class);
        $siteFinder->method('getSiteByIdentifier')
            ->willThrowException(new \TYPO3\CMS\Core\Exception\SiteNotFoundException('Site not found'));

        $assembler = $this->createAssembler([]);
        $validator = new GraphValidator([], new NullLogger());
        $pageRepository = $this->createMock(PageRepository::class);

        $fileRepository = $this->createMock(FileRepository::class);
        $innerPageRepository = $this->createMock(PageRepository::class);
        $factory = new StandalonePageContextFactory($fileRepository, $innerPageRepository);

        $command = new ValidateGraphCommand($siteFinder, $assembler, $validator, $factory, $pageRepository);
        $command->setName('seo:graph:validate');

        $input = new ArrayInput(['--site' => 'nonexistent']);
        $input->bind($command->getDefinition());
        $output = new BufferedOutput();

        $exitCode = $command->run($input, $output);
        self::assertSame(2, $exitCode);
    }

    #[Test]
    public function commandReturnsExitCode0WhenNoErrors(): void
    {
        $site = $this->createFullSiteMock();
        $siteFinder = $this->createMock(SiteFinder::class);
        $siteFinder->method('getSiteByIdentifier')->willReturn($site);

        $pageRecord = ['uid' => 1, 'title' => 'Home', 'tx_seograph_schema_type' => '', 'tx_seograph_exclude' => 0];
        $pageRepository = $this->createMock(PageRepository::class);
        $pageRepository->method('getPage')->willReturn($pageRecord);

        $assembler = $this->createAssembler([['@type' => 'WebPage', '@id' => '#wp']]);
        $validator = new GraphValidator([], new NullLogger());
        $factory = $this->createContextFactory($pageRecord);

        $command = new ValidateGraphCommand($siteFinder, $assembler, $validator, $factory, $pageRepository);
        $command->setName('seo:graph:validate');

        $input = new ArrayInput(['--site' => 'main', '--page' => '1']);
        $input->bind($command->getDefinition());
        $output = new BufferedOutput();

        $exitCode = $command->run($input, $output);
        self::assertSame(0, $exitCode);
    }

    #[Test]
    public function commandReturnsExitCode1WhenValidationErrorsFound(): void
    {
        $site = $this->createFullSiteMock();
        $siteFinder = $this->createMock(SiteFinder::class);
        $siteFinder->method('getSiteByIdentifier')->willReturn($site);

        $pageRecord = ['uid' => 5, 'title' => 'About Us', 'tx_seograph_schema_type' => '', 'tx_seograph_exclude' => 0];
        $pageRepository = $this->createMock(PageRepository::class);
        $pageRepository->method('getPage')->willReturn($pageRecord);

        $assembler = $this->createAssembler([]);
        $validator = $this->createValidatorWithResults([
            ValidationResult::error('Duplicate @id', 'Organization'),
        ]);
        $factory = $this->createContextFactory($pageRecord);

        $command = new ValidateGraphCommand($siteFinder, $assembler, $validator, $factory, $pageRepository);
        $command->setName('seo:graph:validate');

        $input = new ArrayInput(['--site' => 'main', '--page' => '5']);
        $input->bind($command->getDefinition());
        $output = new BufferedOutput();

        $exitCode = $command->run($input, $output);
        self::assertSame(1, $exitCode);
    }

    #[Test]
    public function commandOutputsJsonWhenFormatIsJson(): void
    {
        $site = $this->createFullSiteMock();
        $siteFinder = $this->createMock(SiteFinder::class);
        $siteFinder->method('getSiteByIdentifier')->willReturn($site);

        $pageRecord = ['uid' => 1, 'title' => 'Home', 'tx_seograph_schema_type' => '', 'tx_seograph_exclude' => 0];
        $pageRepository = $this->createMock(PageRepository::class);
        $pageRepository->method('getPage')->willReturn($pageRecord);

        $assembler = $this->createAssembler([]);
        $validator = new GraphValidator([], new NullLogger());
        $factory = $this->createContextFactory($pageRecord);

        $command = new ValidateGraphCommand($siteFinder, $assembler, $validator, $factory, $pageRepository);
        $command->setName('seo:graph:validate');

        $input = new ArrayInput(['--site' => 'main', '--page' => '1', '--format' => 'json']);
        $input->bind($command->getDefinition());
        $output = new BufferedOutput();

        $command->run($input, $output);

        $json = json_decode($output->fetch(), true);
        self::assertIsArray($json);
        self::assertArrayHasKey('pages', $json);
        self::assertArrayHasKey('summary', $json);
    }
}
