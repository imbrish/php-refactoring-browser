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

use Symfony\Component\Console\Output\OutputInterface;

class PrettyDiff
{
    protected $lines = [];

    public function __construct($diff)
    {
        $this->lines = preg_split('/\r\n?|\n/', trim($diff));
    }

    public function out(OutputInterface $output)
    {
        foreach ($this->lines as $line) {
            if (preg_match('/^\+[^\+]/', $line)) {
                $output->writeln(sprintf('<fg=green>%s</>', $line));
            }
            else if (preg_match('/^\-[^\-]/', $line)) {
                $output->writeln(sprintf('<fg=red>%s</>', $line));
            }
            else if (preg_match('/^(@@[^@]+@@)(.*)$/', $line, $match)) {
                // $output->writeln(sprintf('<fg=cyan>%s</>%s', $match[1], $match[2]));
                continue;
            }
            else if (preg_match('/^(?:\-\-\-|\+\+\+)/', $line)) {
                $output->writeln(sprintf('<fg=cyan>%s</>', $line));
            }
            else {
                $output->writeln($line);
            }
        }
    }
}
