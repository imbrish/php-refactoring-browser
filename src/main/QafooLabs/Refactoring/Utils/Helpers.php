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
    protected static $cwd;
    protected static $base;

    public static function setBasePath($base)
    {
        static::$cwd = static::folderPath(getcwd());
        static::$base = static::folderPath(static::$cwd . $base);
    }

    public static function folderPath($path)
    {
        // Convert slashes and add trailing slash.
        return trim(str_replace('\\', '/', trim($path)), '/') . '/';
    }

    public static function removeBasePath($path)
    {
        $pattern = '/^' . preg_quote(static::$base, '/') . '/';

        return preg_replace($pattern, '', str_replace('\\', '/', $path));
    }

    public static function removeCwd($path)
    {
        $pattern = '/^' . preg_quote(static::$cwd, '/') . '/';

        return preg_replace($pattern, '', str_replace('\\', '/', $path));
    }

    public static function relativePathsList($paths, $base = null)
    {
        $base = is_null($base) ? static::$base : static::folderPath($base);

        return array_unique(array_map(function ($path) use ($base) {
            return static::folderPath($base . ltrim(trim($path), '/'));
        }, $paths));
    }

    public static function pathInList($path, $list)
    {
        $path = static::folderPath($path);

        foreach ($list as $item) {
            $item = preg_replace('#/\*/.*#', '/', $item); // remove everything after "/*/"
            $pattern = str_replace('\*', '[^/]*', preg_quote($item)); // convert to pattern and add wildcards
            $pattern = sprintf('#^%s#', $pattern);

            if (preg_match($pattern, $path)) {
                return true;
            }
        }

        return false;
    }

    public static function splitOption($option)
    {
        return array_filter(explode(',', $option));
    }

    public static function readGitIgnore($dir)
    {
        $path = rtrim($dir, '/') . '/.gitignore';

        if (! file_exists($path)) {
            return [];
        }

        $content = file_get_contents($path);

        $content = preg_replace('/#.*$/m', '', $content); // remove comments
        $content = preg_replace('/^(.*?)\.(.*?)$/m', '', $content); // remove files
        $content = preg_replace('/^\s+$/m', '', $content); // trim lines

        $content = array_filter(preg_split('/\r\n?|\n/', $content));

        return static::relativePathsList($content, $dir);
    }
}
