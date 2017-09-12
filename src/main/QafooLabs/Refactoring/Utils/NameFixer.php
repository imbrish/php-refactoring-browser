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

class NameFixer
{
    public static function folderPath($path)
    {
        // Convert slashes and add trailing slash.
        return preg_replace('/(?:\/)*$/u', '/', str_replace('\\', '/', $path));
    }

    public static function className($name, $base)
    {
        // Remove base path and fix case.
        $pattern = '/^' . preg_quote($base, '/') . '/';

        $name = preg_replace($pattern, '', str_replace('\\', '/', $name));

        return implode('\\', array_map(function ($part) {
            return ucfirst($part);
        }, explode('/', $name)));
    }
}
