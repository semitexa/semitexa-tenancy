<?php

declare(strict_types=1);

namespace Semitexa\Tenancy\Tests\Unit\CLI;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Semitexa\Tenancy\Application\Console\Command\TenantRunCommand;
use Semitexa\Tenancy\Context\CoroutineContextStore;
use Semitexa\Tenancy\Application\Service\ConfigTenantRepository;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;

final class TenantRunCommandTest extends TestCase
{
    protected function tearDown(): void
    {
        CoroutineContextStore::clearFallback();
    }

    #[Test]
    public function fails_for_unknown_tenant(): void
    {
        $repo = new ConfigTenantRepository([
            'tenants_compact' => 'acme:Acme Corp:active',
        ]);

        $command = new TenantRunCommand($repo);
        $app = new Application();
        $app->add($command);

        $tester = new CommandTester($command);
        $tester->execute([
            'tenant' => 'unknown',
            'cmd' => ['list'],
        ]);

        $this->assertStringContainsString('not found or not active', $tester->getDisplay());
        $this->assertSame(1, $tester->getStatusCode());
    }

    #[Test]
    public function fails_for_suspended_tenant(): void
    {
        $repo = new ConfigTenantRepository([
            'tenants_compact' => 'suspended:Suspended Corp:suspended',
        ]);

        $command = new TenantRunCommand($repo);
        $app = new Application();
        $app->add($command);

        $tester = new CommandTester($command);
        $tester->execute([
            'tenant' => 'suspended',
            'cmd' => ['list'],
        ]);

        $this->assertStringContainsString('not found or not active', $tester->getDisplay());
        $this->assertSame(1, $tester->getStatusCode());
    }

    #[Test]
    public function sets_context_and_runs_command(): void
    {
        $repo = new ConfigTenantRepository([
            'tenants_compact' => 'acme:Acme Corp:active',
        ]);

        // Create a test command that captures the tenant context
        $capturedTenantId = null;
        $testCommand = new class($capturedTenantId) extends Command {
            public function __construct(private ?string &$capturedTenantId)
            {
                parent::__construct('test:context');
            }

            protected function execute(InputInterface $input, OutputInterface $output): int
            {
                $context = CoroutineContextStore::get();
                $this->capturedTenantId = $context?->tenantId;
                $output->writeln('OK');
                return Command::SUCCESS;
            }
        };

        $runCommand = new TenantRunCommand($repo);
        $app = new Application();
        $app->setAutoExit(false);
        $app->add($runCommand);
        $app->add($testCommand);

        $tester = new CommandTester($runCommand);
        $tester->execute([
            'tenant' => 'acme',
            'cmd' => ['test:context'],
        ]);

        $this->assertSame('acme', $capturedTenantId);
        $this->assertSame(0, $tester->getStatusCode());
    }

    #[Test]
    public function clears_context_after_execution(): void
    {
        $repo = new ConfigTenantRepository([
            'tenants_compact' => 'acme:Acme Corp:active',
        ]);

        $runCommand = new TenantRunCommand($repo);
        $app = new Application();
        $app->setAutoExit(false);
        $app->add($runCommand);
        $app->add(new class extends Command {
            public function __construct()
            {
                parent::__construct('test:noop');
            }

            protected function execute(InputInterface $input, OutputInterface $output): int
            {
                return Command::SUCCESS;
            }
        });

        $tester = new CommandTester($runCommand);
        $tester->execute([
            'tenant' => 'acme',
            'cmd' => ['test:noop'],
        ]);

        // Context should be cleared after tenant:run completes
        $this->assertNull(CoroutineContextStore::get());
    }
}
