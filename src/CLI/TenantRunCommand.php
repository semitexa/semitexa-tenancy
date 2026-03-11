<?php

declare(strict_types=1);

namespace Semitexa\Tenancy\CLI;

use Semitexa\Core\Attributes\AsCommand;
use Semitexa\Core\Console\Command\BaseCommand;
use Semitexa\Core\Event\EventDispatcherInterface;
use Semitexa\Tenancy\Context\CoroutineContextStore;
use Semitexa\Tenancy\Context\TenantContext;
use Semitexa\Tenancy\Event\TenantSwitched;
use Semitexa\Tenancy\Identification\TenantRepositoryInterface;
use Semitexa\Tenancy\TenancyBootstrapper;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Execute a CLI command within a specific tenant context.
 *
 * Usage:
 *   bin/semitexa tenant:run acme cache:clear
 *   bin/semitexa tenant:run globex queue:work
 */
#[AsCommand(name: 'tenant:run', description: 'Execute a command in a specific tenant context')]
class TenantRunCommand extends BaseCommand
{
    private TenantRepositoryInterface $repository;
    private ?EventDispatcherInterface $events;

    public function __construct(
        ?TenantRepositoryInterface $repository = null,
        ?EventDispatcherInterface $events = null,
    ) {
        parent::__construct();
        $this->repository = $repository ?? (new TenancyBootstrapper())->getRepository();
        $this->events = $events;
    }

    protected function configure(): void
    {
        $this->setName('tenant:run')
            ->setDescription('Execute a command in a specific tenant context')
            ->addArgument('tenant', InputArgument::REQUIRED, 'Tenant ID')
            ->addArgument('cmd', InputArgument::REQUIRED | InputArgument::IS_ARRAY, 'Command and its arguments');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $tenantId = $input->getArgument('tenant');
        $cmdParts = $input->getArgument('cmd');

        // Validate tenant exists and is active
        $tenant = $this->repository->findActive($tenantId);

        if ($tenant === null) {
            $io->error(sprintf('Tenant "%s" not found or not active.', $tenantId));
            return Command::FAILURE;
        }

        // Set tenant context for the CLI session, preserving previous
        $context = TenantContext::fromResolution($tenantId, 'cli', 'tenant:run');
        $previous = CoroutineContextStore::swapFallback($context);

        $this->events?->dispatch(new TenantSwitched(
            previous: $previous ?? TenantContext::default(),
            current: $context,
        ));

        $io->text(sprintf('Running in tenant context: %s (%s)', $tenant->name, $tenant->id));

        try {
            $application = $this->getApplication();

            if ($application === null) {
                $io->error('Could not access the console application.');
                return Command::FAILURE;
            }

            $commandInput = new StringInput(implode(' ', $cmdParts));

            return $application->run($commandInput, $output);
        } finally {
            CoroutineContextStore::swapFallback($previous);

            $this->events?->dispatch(new TenantSwitched(
                previous: $context,
                current: $previous ?? TenantContext::default(),
            ));
        }
    }
}
