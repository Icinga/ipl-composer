<?php

namespace ipl\Composer\Commands;

use Composer\Command\BaseCommand;
use ipl\Composer\Service\BuildService;
use ipl\Composer\Service\ComposerService;
use ipl\Composer\Service\GitService;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ReleaseCommand extends BaseCommand
{
    protected function configure(): void
    {
        $this->setName('release')
            ->setDescription('Build bundles and assets for release')
            ->addArgument('version', InputArgument::REQUIRED, 'The version to release')
            ->addOption('no-tag', 't', null, 'Do not tag the release')
            ->addOption('no-checkout', 'c', null, 'Do not checkout the release tag');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $version = $input->getArgument('version');
        $noTag = $input->getOption('no-tag');
        $noCheckout = $input->getOption('no-checkout');

        if (! GitService::isGitInstalled()) {
            $output->writeln('Git is not installed', OutputInterface::VERBOSITY_NORMAL);
            return 1;
        }

        $git = new GitService($this->getIO(), $this->requireComposer()->getConfig());

        $composerService = new ComposerService($this->requireComposer(), $output);
        $build = new BuildService(
            $git,
            $composerService,
            $output,
        );

        $extra = $this->requireComposer()->getPackage()->getExtra()['ipl/composer']['release'] ?? [];
        return $build->release(
            $version,
            !$noCheckout,
            !$noTag,
            $extra['include'] ?? [],
            $extra['exclude'] ?? [],
        );
    }
}
