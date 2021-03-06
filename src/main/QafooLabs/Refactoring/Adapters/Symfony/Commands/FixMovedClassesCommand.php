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

namespace QafooLabs\Refactoring\Adapters\Symfony\Commands;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Command\Command;

use QafooLabs\Refactoring\Application\FixMovedClasses;
use QafooLabs\Refactoring\Adapters\PHPParser\ParserPhpNameScanner;
use QafooLabs\Refactoring\Adapters\TokenReflection\StaticCodeAnalysis;
use QafooLabs\Refactoring\Adapters\PatchBuilder\PatchEditor;
use QafooLabs\Refactoring\Adapters\Symfony\OutputPatchCommand;
use QafooLabs\Refactoring\Domain\Model\Directory;
use QafooLabs\Refactoring\Domain\Model\File;

use QafooLabs\Refactoring\Utils\Helpers;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Process\Process;
use QafooLabs\Refactoring\Utils\PrettyDiff;

class FixMovedClassesCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('fix-moved-classes')
            ->setDescription('Update codebase after changing project structure.')
            ->addArgument('dir', InputArgument::IS_ARRAY | InputArgument::OPTIONAL, 'Directory that contains the source code to refactor', [''])
            ->addOption('base', 'b', InputOption::VALUE_OPTIONAL, 'Project base directory', '')
            ->addOption('skip', 's', InputOption::VALUE_OPTIONAL, 'Directories relative to base directory that should be skipped', '')
            ->addOption('ignore', 'i', InputOption::VALUE_OPTIONAL, 'Directories in which invalid namespace should be ignored', '')
            ->addOption('loose', 'l', InputOption::VALUE_NONE, 'Automatically ignore files without namespace')
            ->addOption('patch', null, InputOption::VALUE_NONE, 'Apply generated diff')
            ->addOption('pretty', 'p', InputOption::VALUE_NONE, 'Print pretty diff')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        Helpers::setBasePath($input->getOption('base'));

        File::setCodeAnalysis(new StaticCodeAnalysis());
        File::setOptions(
            Helpers::splitOption($input->getOption('ignore')),
            $input->getOption('loose')
        );

        $directory = new Directory($input->getArgument('dir'));
        $directory->setExcludedDirs(Helpers::splitOption($input->getOption('skip')));

        $diffOutput = new BufferedOutput;

        $phpNameScanner = new ParserPhpNameScanner();
        $editor = new PatchEditor(new OutputPatchCommand($diffOutput));

        $fixMovedClasses = new FixMovedClasses($editor, $phpNameScanner);
        $fixMovedClasses->refactor($directory);

        if (! $input->getOption('patch')) {
            if ($input->getOption('pretty')) {
                $pretty = new PrettyDiff($diffOutput->fetch());
                $pretty->out($output);
            }
            else {
                $output->write($diffOutput->fetch());
            }
        }
        else {
            $process = new Process('patch -p1 --binary');
            $process->setInput($diffOutput->fetch());
            $process->run();

            $output->write($process->getOutput());
        }
    }
}
