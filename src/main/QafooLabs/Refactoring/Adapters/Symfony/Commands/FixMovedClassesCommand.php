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

use QafooLabs\Refactoring\Utils\NameFixer;

class FixMovedClassesCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('fix-moved-classes')
            ->setDescription('Update codebase after changing project structure.')
            ->addArgument('dir', InputArgument::IS_ARRAY | InputArgument::REQUIRED, 'Directory that contains the source code to refactor')
            ->addOption('base', 'b', InputOption::VALUE_OPTIONAL, 'Project base directory', '')
            ->addOption('skip', 's', InputOption::VALUE_OPTIONAL, 'Directories relative to base directory that should be skipped', '')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $directory = new Directory($input->getArgument('dir'), getcwd());
        $base = NameFixer::folderPath($input->getOption('base'));
        $skip = array_filter(explode(',', $input->getOption('skip')));

        $codeAnalysis = new StaticCodeAnalysis();
        $phpNameScanner = new ParserPhpNameScanner();
        $editor = new PatchEditor(new OutputPatchCommand($output));

        $fixMovedClasses = new FixMovedClasses($codeAnalysis, $editor, $phpNameScanner);
        $fixMovedClasses->refactor($directory, $base, $skip);
    }
}
