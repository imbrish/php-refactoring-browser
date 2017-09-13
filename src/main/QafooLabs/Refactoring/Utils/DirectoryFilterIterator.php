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

namespace QafooLabs\Refactoring\Utils;

use RecursiveFilterIterator;
use QafooLabs\Refactoring\Utils\Helpers;

class DirectoryFilterIterator extends RecursiveFilterIterator
{
    protected $exclude;

    public function __construct($iterator, array $exclude)
    {
        parent::__construct($iterator);
        $this->exclude = $this->joinGitIgnore($exclude, $this->getPath());
    }

    protected function joinGitIgnore($exclude, $path)
    {
        return array_merge($exclude, Helpers::readGitIgnore($path));
    }

    public function accept()
    {
        if (! $this->isDir()) {
            return true;
        }

        $path = $this->getPathname();
        $exclude = $this->joinGitIgnore($this->exclude, $path);

        return ! Helpers::pathInList($path, $exclude);
    }

    public function getChildren()
    {
        return new static($this->getInnerIterator()->getChildren(), $this->exclude);
    }
}

