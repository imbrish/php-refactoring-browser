<?php
/**
 * Qafoo PHP Refactoring Browser
 *
 * LICENSE
 *
 * This source file is subject to the MIT license that is bundled
 * with this package in the file LICENSE.txt.
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to kontakt@beberlei.de so I can send you a copy immediately.
 */

namespace QafooLabs\Refactoring\Domain\Model;

use QafooLabs\Refactoring\Utils\CallbackFilterIterator;
use QafooLabs\Refactoring\Utils\CallbackTransformIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use AppendIterator;
use CallbackFilterIterator as StandardCallbackFilterIterator;
use FilesystemIterator;

use QafooLabs\Refactoring\Utils\Helpers;
use QafooLabs\Refactoring\Utils\DirectoryFilterIterator;

/**
 * A directory in a project.
 */
class Directory
{
    private $paths;
    private $exclude;

    public function __construct($paths)
    {
        if (is_string($paths)) {
            $paths = array($paths);
        }

        $this->paths = $paths;
    }

    public function setExcludedDirs($exclude)
    {
        $this->exclude = Helpers::relativePathsList(array_merge(['.git'], $exclude));
    }

    /**
     * @return File[]
     */
    public function findAllPhpFilesRecursivly()
    {
        $iterator = new AppendIterator;

        foreach ($this->paths as $path) {
            $iterator->append(
                new CallbackFilterIterator(
                    new RecursiveIteratorIterator(
                        new DirectoryFilterIterator(
                            new RecursiveDirectoryIterator(
                                realpath($path),
                                FilesystemIterator::SKIP_DOTS | FilesystemIterator::UNIX_PATHS
                            ),
                            $this->exclude
                        ),
                        RecursiveIteratorIterator::LEAVES_ONLY
                    ),
                    function (SplFileInfo $file) {
                        return substr($name = $file->getPathname(), -4) === '.php';
                    }
                )
            );
        }

        return array_map(function ($file) {
            return File::createFromPath($file->getPathname());
        }, iterator_to_array($iterator));
    }
}
