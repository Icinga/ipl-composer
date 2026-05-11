<?php

namespace ipl\Composer\Service;

use Composer\Composer;
use Composer\Console\Application;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\OutputInterface;

class ComposerService
{
    protected Application $application;

    public function __construct(
        protected Composer $composer,
        protected OutputInterface $output,
    ) {
        $this->application = new Application();
        $this->application->setAutoExit(false);
    }

    public function run(array $command): int
    {
        $in = new StringInput(implode(' ', $command));
        return $this->application->run($in, $this->output);
    }

    public function getRepointPackages(array $repoints): array
    {
        // TODO: This should also include dev packages
        $requirements = $this->composer->getPackage()->getRequires();

        $repointPackages = [];

        foreach ($repoints as $filter => $version) {
            foreach ($requirements as $name => $requirement) {
                if (isset($repointPackages[$name])) {
                    continue;
                }

                if (preg_match("/$filter/", $name)) {
                    $repointPackages[$name] = $version;
                }
            }
        }

        return $repointPackages;
    }
}
