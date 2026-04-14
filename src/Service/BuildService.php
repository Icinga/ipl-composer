<?php

namespace ipl\Composer\Service;

use Composer\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\OutputInterface;

class BuildService
{
    public function __construct(
        protected ?GitService $git,
        protected OutputInterface $output,
    ) {
    }

    public function release(
        string $version,
        bool $checkout,
        bool $tag,
    ): bool {
        $tags = $this->git->getTags();
        if (in_array($version, $tags)) {
            $this->output->writeln("Version $version has already been tagged!", OutputInterface::VERBOSITY_NORMAL);
            return false;
        }

        if(! $this->composerValidate()) {
            $this->output->writeln("Composer validate failed", OutputInterface::VERBOSITY_NORMAL);
            return false;
        }

        if ($checkout) {
            $branchName = "stable/$version";
            if (! $this->git->createBranchAndSwitch($branchName)) {
                $this->output->writeln("Version branch $branchName already exists", OutputInterface::VERBOSITY_NORMAL);
                return false;
            }
        } else {
            $branchName = $this->git->getCurrentBranch();
            $this->output->writeln("Selected version branch: $branchName", OutputInterface::VERBOSITY_NORMAL);
        }

        if (! is_dir('vendor')) {
            $this->output->writeln('No vendor directory found.', OutputInterface::VERBOSITY_NORMAL);
            return false;
        }

        $this->git->add('vendor');
        $this->git->add('asset/*');

        if (! file_put_contents('VERSION', "v$version")) {
            $this->output->writeln("Could not write version file", OutputInterface::VERBOSITY_NORMAL);
            return false;
        }

        $this->git->add('VERSION');

        $this->git->commit("Version v$version");

        if ($tag) {
            $this->git->tag("v$version", "Version v$version");

            $this->output->writeln("Finished and tagged v$version", OutputInterface::VERBOSITY_NORMAL);
        } else {
            $this->output->writeln('Not tagging the release', OutputInterface::VERBOSITY_NORMAL);
            $this->output->writeln('Finished, but not tagged yet', OutputInterface::VERBOSITY_NORMAL);

            $this->output->writeln(
                "Please run: git tag -s v$version -m \"Version v$version\"",
                OutputInterface::VERBOSITY_NORMAL,
            );
        }

        $this->output->writeln(
            "Please run: git push origin \"$branchName:$branchName\" && git push --tags",
            OutputInterface::VERBOSITY_NORMAL,
        );

        return true;
    }

    protected function composerValidate(): bool
    {
        $application = new Application();
        $application->setAutoExit(false);

        $in = new ArrayInput(['command' => 'validate', '--no-check-all' => true, '--strict' => true]);
        $code = $application->run($in, $this->output);

        return $code === 0;
    }
}
