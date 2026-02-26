<?php

declare(strict_types=1);

namespace Semitexa\Tenancy\CLI;

use Semitexa\Core\Attributes\AsCommand;
use Semitexa\Core\Console\Command\BaseCommand;
use Semitexa\Tenancy\Identification\TenantRepositoryInterface;
use Semitexa\Tenancy\TenancyBootstrapper;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'tenant:list', description: 'List all registered tenants')]
class TenantListCommand extends BaseCommand
{
    private TenantRepositoryInterface $repository;

    public function __construct(?TenantRepositoryInterface $repository = null)
    {
        parent::__construct();
        $this->repository = $repository ?? (new TenancyBootstrapper())->getRepository();
    }

    protected function configure(): void
    {
        $this->setName('tenant:list')
            ->setDescription('List all registered tenants');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $tenants = $this->repository->findAll();

        if ($tenants === []) {
            $io->warning('No tenants registered. Configure TENANTS env variable or TENANT_{ID}_NAME/STATUS pairs.');
            return Command::SUCCESS;
        }

        $rows = [];
        foreach ($tenants as $tenant) {
            $rows[] = [
                $tenant->id,
                $tenant->name,
                $tenant->status->value,
            ];
        }

        $io->table(['ID', 'Name', 'Status'], $rows);
        $io->text(sprintf('Total: %d tenant(s)', count($tenants)));

        return Command::SUCCESS;
    }
}
