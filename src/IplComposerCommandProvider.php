<?php

namespace ipl\Composer;

use Composer\Plugin\Capability\CommandProvider as CommandProviderCapability;
use ipl\Composer\Commands\AssetCommand;

class IplComposerCommandProvider implements CommandProviderCapability
{
    public function getCommands(): array
    {
        return [
            new AssetCommand(),
        ];
    }
}
