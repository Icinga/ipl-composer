<?php

namespace ipl\Composer;

use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\Plugin\Capability\CommandProvider;
use Composer\Plugin\Capable;
use Composer\Plugin\PluginInterface;
use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\Script\Event;
use ipl\Composer\Service\AssetMirror;

class IplComposerPlugin implements PluginInterface, Capable, EventSubscriberInterface
{
    public function activate(Composer $composer, IOInterface $io): void
    {
    }

    public function deactivate(Composer $composer, IOInterface $io): void
    {
    }

    public function uninstall(Composer $composer, IOInterface $io): void
    {
    }

    public function getCapabilities(): array
    {
        return [
            CommandProvider::class => IplComposerCommandProvider::class,
        ];
    }

    public static function getSubscribedEvents(): array
    {
        return [
            'post-install-cmd' => 'onEvent',
            'post-update-cmd' => 'onEvent',
        ];
    }

    public function onEvent(Event $event): void
    {
        $composer = $event->getComposer();
        $devMode = $event->isDevMode();
        $output = $event->getIO();
        $output->write(($devMode ? 'Linking' : 'Copying') . ' asset directory');
        $mirror = new AssetMirror($composer, $output);
        $mirror->mirror(! $devMode);
    }
}
