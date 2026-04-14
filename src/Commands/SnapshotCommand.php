<?php

namespace ipl\Composer\Commands;

use Composer\Command\BaseCommand;
use ipl\Composer\Service\BuildService;
use ipl\Composer\Service\GitService;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class SnapshotCommand extends BaseCommand
{
    protected function configure(): void
    {
        $this->setName('snapshot')
            ->setDescription('Build bundles and assets for snapshot')
            ->addArgument('branch', InputArgument::REQUIRED, 'The branch to release to (e.g. "snapshot/nightly"');
    }

    protected function getNextVersion(string $version, ?string $suffix = null): string
    {
        $parts = explode('-', $version, 2);
        if (count($parts) === 2) {
            $version = $parts[0];
        }
        $version = trim($version, 'v');
        $parts = explode('.', $version);
        $newVersion = join('.', [$parts[0], (int)$parts[1] + 1, 0]);
        if ($suffix) {
            $newVersion .= '-' . $suffix;
        }
        return $newVersion;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $branch = $input->getArgument('branch');

        if (! GitService::isGitInstalled()) {
            $output->writeln('Git is not installed', OutputInterface::VERBOSITY_NORMAL);
            return 1;
        }

        $git = new GitService($this->getIO(), $this->requireComposer()->getConfig());

        $tags = $git->getTags();
        if (empty($tags)) {
            $output->writeln('No tags found', OutputInterface::VERBOSITY_NORMAL);
            return 1;
        }
        sort($tags);
        $latestTag = end($tags);
        $nextVersion = $this->getNextVersion($latestTag, 'dev');

        $output->writeln("Latest tag: $latestTag", OutputInterface::VERBOSITY_NORMAL);
        $output->writeln("Next version: $nextVersion", OutputInterface::VERBOSITY_NORMAL);

        $git->deleteBranch($branch);
        $git->createBranchAndSwitch($branch);

        $git->merge($latestTag, 'Merge latest tag, package pipelines require it');

        $git->restore('composer.json', true, true);
        $git->restore('composer.lock', true, true);

        $git->commit(noEdit: true);

        $build = new BuildService(
            $git,
            $output,
        );

        return $build->release($nextVersion, false, true);
    }
}
