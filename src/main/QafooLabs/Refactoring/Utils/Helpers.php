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

class Helpers
{
    public static function folderPath($path)
    {
        // Convert slashes and add trailing slash.
        return rtrim(str_replace('\\', '/', $path), '/') . '/';
    }

    public static function removeBasePath($path, $base)
    {
        $pattern = '/^' . preg_quote(str_replace('\\', '/', $base), '/') . '/';

        return preg_replace($pattern, '', str_replace('\\', '/', $path));
    }

    public static function shouldIgnore($path, $ignores)
    {
        $path = str_replace('\\', '/', $path);

        foreach ($ignores as $ignore) {
            $pattern = '/^' . preg_quote($ignore, '/') . '/';

            if (preg_match($pattern, $path)) {
                return true;
            }
        }

        return false;
    }
}
