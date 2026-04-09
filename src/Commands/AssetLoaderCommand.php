<?php

namespace ipl\Composer\Commands;

use Composer\Command\BaseCommand;
use ipl\Composer\Service\AssetMirror;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class AssetLoaderCommand extends BaseCommand
{
    protected function configure(): void
    {
        $this->setName('load-assets')
            ->setDescription('Collect and load assets from various asset directories')
            ->addOption("copy", "c", null, "Copy assets instead of creating symlinks");
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $copy = $input->getOption('copy');

        $composer = $this->requireComposer();

        AssetMirror::mirror($composer, $copy);

        return 0;
    }
}
