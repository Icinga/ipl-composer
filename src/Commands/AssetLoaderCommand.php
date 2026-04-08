<?php

namespace ipl\Composer\Commands;

use Composer\Command\BaseCommand;
use Composer\Util\Filesystem;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class AssetLoaderCommand extends BaseCommand
{
    protected const DIR_PERMISSIONS = 0755;

    protected function configure(): void
    {
        $this->setName('load-assets')
            ->setDescription('Collect and load assets from various asset directories')
            ->addOption("copy", "c", null, "Copy assets instead of creating symlinks");
    }

    protected function handleCopy(string $from, string $to, bool $copy): void
    {
        if (! is_readable($from)) {
            return;
        }

        if (is_dir($from)) {
            $libAssets = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(
                $from,
                FilesystemIterator::CURRENT_AS_FILEINFO | FilesystemIterator::SKIP_DOTS
            ), RecursiveIteratorIterator::SELF_FIRST);
            /** @var SplFileInfo $sourcePath */
            foreach ($libAssets as $sourcePath) {
                $targetPath = getcwd() . '/' . $to . substr($sourcePath->getRealPath(), strlen(getcwd() . '/' . $from));

                if ($sourcePath->getRealPath() === $targetPath) {
                    continue;
                }

                if (file_exists($targetPath)) {
                    continue;
                }

                if ($sourcePath->isDir()) {
                    mkdir($targetPath, static::DIR_PERMISSIONS, true);
                } elseif ($sourcePath->isFile()) {
                    if (! is_dir(dirname($targetPath))) {
                        mkdir(dirname($targetPath), static::DIR_PERMISSIONS, true);
                    }
                    if ($copy) {
                        copy($sourcePath->getRealPath(), $targetPath);
                    } else {
                        symlink($sourcePath->getRealPath(), $targetPath);
                    }
                }
            }
        } else if (is_file($from)) {
            if ($from === $to) {
                return;
            }

            $sourcePath = getcwd() . '/' . $from;
            $targetPath = getcwd() . '/' . $to;

            if (file_exists($targetPath)) {
                return;
            }

            if (! is_dir(dirname($targetPath))) {
                mkdir(dirname($targetPath), static::DIR_PERMISSIONS, true);
            }

            if ($copy) {
                copy($sourcePath, $targetPath);
            } else {
                symlink($sourcePath, $targetPath);
            }
        }
    }

    protected function handlePackage($package, bool $copy): void
    {
        $name = $package->getName();
        $path = "vendor/$name/asset";
        if (is_dir($path) && is_readable($path)) {
            $this->handleCopy($path, "asset", $copy);
        }

        $extra = $package->getExtra();
        if (empty($extra) || empty($extra['ipl-composer'])) {
            return;
        }

        $special = $extra['ipl-composer']['special'] ?? [];
        foreach ($special as $sourcePath => $targetPath) {
            $this->handleCopy($sourcePath, "asset/" . $targetPath, $copy);
        }
    }

    protected function cleanup(): void
    {
        // Check for removed files
        if (is_dir('asset')) {
            $fs = new Filesystem();
            $assets = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(
                'asset',
                FilesystemIterator::CURRENT_AS_FILEINFO | FilesystemIterator::SKIP_DOTS
            ), RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($assets as $asset) {
                /** @var SplFileInfo $asset */
                if ($asset->isDir()) {
                    if ($fs->isDirEmpty($asset->getPathname())) {
                        rmdir($asset);
                    }
                } elseif (! $asset->isReadable()) {
                    unlink($asset);
                }
            }
        }
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $copy = $input->getOption('copy');

        $composer = $this->requireComposer();

        $this->handlePackage($composer->getPackage(), $copy);

        $localRepo = $composer->getRepositoryManager()->getLocalRepository();
        foreach ($localRepo->getPackages() as $package) {
            $this->handlePackage($package, $copy);
        }

        $this->cleanup();

        return 0;
    }
}
