<?php

declare(strict_types=1);

namespace Semitexa\Tenancy\Tests\Unit\CLI;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Semitexa\Tenancy\CLI\TenantListCommand;
use Semitexa\Tenancy\Identification\ConfigTenantRepository;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

final class TenantListCommandTest extends TestCase
{
    #[Test]
    public function lists_tenants_in_table(): void
    {
        $repo = new ConfigTenantRepository([
            'tenants_compact' => 'acme:Acme Corp:active,globex:Globex Inc:suspended',
        ]);

        $command = new TenantListCommand($repo);
        $app = new Application();
        $app->add($command);

        $tester = new CommandTester($command);
        $tester->execute([]);

        $output = $tester->getDisplay();
        $this->assertStringContainsString('acme', $output);
        $this->assertStringContainsString('Acme Corp', $output);
        $this->assertStringContainsString('active', $output);
        $this->assertStringContainsString('globex', $output);
        $this->assertStringContainsString('Globex Inc', $output);
        $this->assertStringContainsString('suspended', $output);
        $this->assertStringContainsString('Total: 2 tenant(s)', $output);
        $this->assertSame(0, $tester->getStatusCode());
    }

    #[Test]
    public function shows_warning_when_no_tenants(): void
    {
        $repo = new ConfigTenantRepository([]);

        $command = new TenantListCommand($repo);
        $app = new Application();
        $app->add($command);

        $tester = new CommandTester($command);
        $tester->execute([]);

        $output = $tester->getDisplay();
        $this->assertStringContainsString('No tenants registered', $output);
        $this->assertSame(0, $tester->getStatusCode());
    }
}
