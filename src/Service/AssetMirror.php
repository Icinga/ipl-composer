<?php

namespace ipl\Composer\Service;

use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\Package\PackageInterface;
use Composer\Util\Filesystem;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;
use Symfony\Component\Filesystem\Path;

class AssetMirror
{
    protected const DIR_PERMISSIONS = 0755;

    protected const SOURCE_DIR_NAME = 'asset';

    protected const TARGET_DIR_NAME = 'asset';

    public function __construct(
        protected Composer $composer,
        protected IOInterface $io,
    ) {
    }

    protected function getVendorDirectory(): string
    {
        $vendor = $this->composer->getConfig()->get('vendor-dir') ?? false;
        if ($vendor === false) {
            throw new RuntimeException('Could not determine vendor directory');
        }

        return $vendor;
    }

    protected function getCwd(): string
    {
        $cwd = getcwd();

        if ($cwd === false) {
            $cwd = getenv('PWD');
        }

        if ($cwd === false) {
            $cwd = $_SERVER['PWD'] ?? false;
        }

        if ($cwd === false) {
            $cwd = $this->composer->getConfig()->get('home') ?? false;
        }

        if ($cwd === false) {
            throw new RuntimeException('Could not determine current working directory');
        }

        return $cwd;
    }

    protected function getTargetDirectory(): string
    {
        return $this->getCwd() . '/' . static::TARGET_DIR_NAME;
    }

    public function mirror(bool $copy = false): void
    {
        // Note: There is no need to copy asset files to the exact same location.
        $this->handleExtraFiles($this->composer->getPackage(), $copy);

        $localRepo = $this->composer->getRepositoryManager()->getLocalRepository();
        foreach ($localRepo->getPackages() as $package) {
            $this->handleAssetDirectory($package, $copy);
            $this->handleExtraFiles($package, $copy);
        }

        $this->cleanup();
    }

    protected function getRelativePath(string $path, ?string $base = null): string
    {
        $base ??= $this->getCwd();
        $path = Path::canonicalize($path);
        if (! $this->validatePath($path, $base)) {
            return $path;
        }
        return substr($path, strlen($base) + 1);
    }

    protected function validatePath(string $path, string $base): bool
    {
        $path = Path::canonicalize($path);
        $base = Path::canonicalize($base);
        return str_starts_with($path, $base . '/');
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
                $sourcePath = $sourcePath->getPathname();
                $targetPath = $to . substr($sourcePath, strlen($from));

                if ($sourcePath === $targetPath) {
                    continue;
                }

                if (! $this->validatePath($sourcePath, $this->getVendorDirectory())) {
                    $this->io->write(
                        'Skipping "' . $this->getRelativePath($sourcePath, $this->getVendorDirectory())
                        . '" because it sources a directory outside the vendor directory',
                    );
                    continue;
                }

                if ($targetPath !== $to && ! $this->validatePath($targetPath, $this->getTargetDirectory())) {
                    $this->io->write(
                        'Skipping "' . $this->getRelativePath($targetPath, $this->getTargetDirectory())
                        . '" because it targets a directory outside the assets directory',
                    );
                    continue;
                }

                if (file_exists($targetPath)) {
                    if (is_file($targetPath)) {
                        unlink($targetPath);
                    }
                }

                $this->io->write(
                    $this->getRelativePath($sourcePath) . ' -> ' . $this->getRelativePath($targetPath)
                );

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
        } elseif (is_file($from)) {
            $sourcePath = Path::canonicalize($from);
            $targetPath = $this->getTargetDirectory() . '/' . $to;
            if ($sourcePath === $targetPath) {
                return;
            }

            if (! $this->validatePath($sourcePath, $this->getVendorDirectory())) {
                $this->io->write(
                    'Skipping "' . $this->getRelativePath($sourcePath, $this->getVendorDirectory())
                    . '" because it sources a directory outside the vendor directory',
                );
                return;
            }

            if (! $this->validatePath($targetPath, $this->getTargetDirectory())) {
                $this->io->write(
                    'Skipping "' . $this->getRelativePath($targetPath, $this->getTargetDirectory())
                    . '" because it targets a directory outside the assets directory',
                );
                return;
            }

            if (file_exists($targetPath)) {
                unlink($targetPath);
            }

            $this->io->write(
                $this->getRelativePath($sourcePath) . ' -> ' . $this->getRelativePath($targetPath),
            );

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

    protected function handleAssetDirectory(PackageInterface $package, bool $copy): void
    {
        $name = $package->getName();
        $path = $this->getVendorDirectory() . "/$name/" . static::SOURCE_DIR_NAME;
        if (is_dir($path) && is_readable($path)) {
            $this->handleCopy($path, $this->getTargetDirectory(), $copy);
        }
    }

    protected function handleExtraFiles(PackageInterface $package, bool $copy): void
    {
        $extra = $package->getExtra();
        if (empty($extra) || empty($extra['ipl/composer'])) {
            return;
        }

        $special = $extra['ipl/composer']['extra'] ?? [];
        foreach ($special as $sourcePath => $targetPath) {
            $this->handleCopy(
                $this->getVendorDirectory() . '/' . $sourcePath,
                $targetPath,
                $copy
            );
        }
    }

    protected function cleanup(): void
    {
        // Check for removed files
        if (is_dir($this->getTargetDirectory())) {
            $fs = new Filesystem();
            $assets = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(
                $this->getTargetDirectory(),
                FilesystemIterator::CURRENT_AS_FILEINFO | FilesystemIterator::SKIP_DOTS
            ), RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($assets as $asset) {
                /** @var SplFileInfo $asset */
                if (is_dir($asset->getPathname())) {
                    if ($fs->isDirEmpty($asset->getPathname())) {
                        rmdir($asset->getPathname());
                    }
                } elseif (is_link($asset->getPathname()) && ! file_exists($asset->getPathname())) {
                    unlink($asset->getPathname());
                }
            }
        }
    }
}
