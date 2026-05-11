<?php

namespace ipl\Composer\Service;

use Composer\Composer;
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
        protected Composer $composer
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
            $cwd = getenv('CWD');
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
        static::handlePackage($this->composer->getPackage(), $copy);

        $localRepo = $this->composer->getRepositoryManager()->getLocalRepository();
        foreach ($localRepo->getPackages() as $package) {
            static::handlePackage($package, $copy);
        }

        static::cleanup();
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

                if ($targetPath != $to && ! $this->validatePath($targetPath, $this->getTargetDirectory())) {
                    echo(
                        'Skipping "' . $this->getRelativePath($targetPath, $this->getTargetDirectory())
                        . '" because it targets a directory outside the assets directory' . PHP_EOL
                    );
                    continue;
                }

                if (file_exists($targetPath)) {
                    if (is_file($targetPath)) {
                        unlink($targetPath);
                    }
                }

                echo(
                    $this->getRelativePath($sourcePath) . ' -> ' . $this->getRelativePath($targetPath) . PHP_EOL
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
        } else if (is_file($from)) {
            $targetPath = $this->getTargetDirectory() . '/' . $to;
            if ($from === $targetPath) {
                return;
            }

            if (! $this->validatePath($targetPath, $this->getTargetDirectory())) {
                echo(
                    'Skipping "' . $this->getRelativePath($targetPath, $this->getTargetDirectory())
                    . '" because it targets a directory outside the assets directory' . PHP_EOL
                );
                return;
            }

            if (file_exists($targetPath)) {
                unlink($targetPath);
            }

            echo(
                $this->getRelativePath($from) . ' -> ' . $this->getRelativePath($targetPath) . PHP_EOL
            );

            if (! is_dir(dirname($targetPath))) {
                mkdir(dirname($targetPath), static::DIR_PERMISSIONS, true);
            }

            if ($copy) {
                copy($from, $targetPath);
            } else {
                symlink($from, $targetPath);
            }
        }
    }

    protected function handlePackage(PackageInterface $package, bool $copy): void
    {
        $name = $package->getName();
        $path = $this->getVendorDirectory() . "/$name/" . static::SOURCE_DIR_NAME;
        if (is_dir($path) && is_readable($path)) {
            static::handleCopy($path, $this->getTargetDirectory(), $copy);
        }

        $extra = $package->getExtra();
        if (empty($extra) || empty($extra['ipl/composer'])) {
            return;
        }

        $special = $extra['ipl/composer']['extra'] ?? [];
        foreach ($special as $sourcePath => $targetPath) {
            static::handleCopy(
                $this->getVendorDirectory() . '/' . $sourcePath,
                $targetPath,
                $copy
            );
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
                        rmdir($asset->getPathname());
                    }
                } elseif ($asset->isLink() && ! file_exists($asset->getPathname())) {
                    unlink($asset->getPathname());
                }
            }
        }
    }
}
