<?php

namespace ipl\Composer\Service;

use Composer\Composer;
use Composer\Package\PackageInterface;
use Composer\Util\Filesystem;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

class AssetMirror
{
    protected const DIR_PERMISSIONS = 0755;

    protected const SOURCE_DIR_NAME = 'asset';

    protected const TARGET_DIR_NAME = 'asset';

    static public function mirror(Composer $composer, bool $copy = false): void
    {
        static::handlePackage($composer->getPackage(), $copy);

        $localRepo = $composer->getRepositoryManager()->getLocalRepository();
        foreach ($localRepo->getPackages() as $package) {
            static::handlePackage($package, $copy);
        }

        static::cleanup();
    }

    static protected function handleCopy(string $from, string $to, bool $copy): void
    {
        if (! is_readable($from)) {
            return;
        }

        $fromBasePath = getcwd() . '/' . $from;
        $toBasePath = getcwd() . '/' . $to;

        if (is_dir($fromBasePath)) {
            $libAssets = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(
                $fromBasePath,
                FilesystemIterator::CURRENT_AS_FILEINFO | FilesystemIterator::SKIP_DOTS
            ), RecursiveIteratorIterator::SELF_FIRST);
            /** @var SplFileInfo $sourcePath */
            foreach ($libAssets as $sourcePath) {
                $sourcePath = $sourcePath->getPath() . '/' . $sourcePath->getFilename();
                $targetPath = $toBasePath . substr($sourcePath, strlen($fromBasePath));

                if ($sourcePath === $targetPath) {
                    continue;
                }

                if (file_exists($targetPath)) {
                    if (is_file($targetPath)) {
                        unlink($targetPath);
                    }
                }

                if (is_dir($sourcePath)) {
                    if (! file_exists($targetPath)) {
                        mkdir($targetPath, static::DIR_PERMISSIONS, true);
                    }
                } elseif (is_file($sourcePath)) {
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
        } else if (is_file($from)) {
            if ($from === $to) {
                return;
            }

            if (file_exists($toBasePath)) {
                unlink($toBasePath);
            }

            if (! is_dir(dirname($toBasePath))) {
                mkdir(dirname($toBasePath), static::DIR_PERMISSIONS, true);
            }

            if ($copy) {
                copy($fromBasePath, $toBasePath);
            } else {
                symlink($fromBasePath, $toBasePath);
            }
        }
    }

    static protected function handlePackage(PackageInterface $package, bool $copy): void
    {
        $name = $package->getName();
        $path = "vendor/$name/" . static::SOURCE_DIR_NAME;
        if (is_dir($path) && is_readable($path)) {
            static::handleCopy($path, static::TARGET_DIR_NAME, $copy);
        }

        $extra = $package->getExtra();
        if (empty($extra) || empty($extra['ipl/composer'])) {
            return;
        }

        $special = $extra['ipl/composer']['extra'] ?? [];
        foreach ($special as $sourcePath => $targetPath) {
            static::handleCopy($sourcePath, static::TARGET_DIR_NAME . "/" . $targetPath, $copy);
        }
    }

    static protected function cleanup(): void
    {
        // Check for removed files
        if (is_dir(static::TARGET_DIR_NAME)) {
            $fs = new Filesystem();
            $assets = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(
                static::TARGET_DIR_NAME,
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
}
