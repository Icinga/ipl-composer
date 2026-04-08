<?php

namespace ipl\Composer;

use Composer\Plugin\Capability\CommandProvider;
use Composer\Plugin\Capable;
use Composer\Plugin\PluginInterface;
use Composer\Composer;
use Composer\IO\IOInterface;

class IplComposerPlugin implements PluginInterface, Capable
{
    public function activate(Composer $composer, IOInterface $io)
    {

    }

    public function deactivate(Composer $composer, IOInterface $io)
    {

    }

    public function uninstall(Composer $composer, IOInterface $io)
    {

    }

    public function getCapabilities(): array
    {
        return [
            CommandProvider::class => IplComposerCommandProvider::class,
        ];
    }
}
