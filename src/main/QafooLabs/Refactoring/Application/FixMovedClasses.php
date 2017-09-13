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

namespace QafooLabs\Refactoring\Application;

use QafooLabs\Collections\Set;
use QafooLabs\Refactoring\Domain\Model\Directory;
use QafooLabs\Refactoring\Domain\Model\File;
use QafooLabs\Refactoring\Domain\Model\PhpName;
use QafooLabs\Refactoring\Domain\Model\PhpNameChange;

use CallbackFilterIterator;
use QafooLabs\Refactoring\Utils\Helpers;

class FixMovedClasses
{
    private $codeAnalysis;
    private $editor;
    private $nameScanner;
    private $base;
    private $skip;
    private $ignore;

    public function __construct($codeAnalysis, $editor, $nameScanner)
    {
        $this->codeAnalysis = $codeAnalysis;
        $this->editor = $editor;
        $this->nameScanner = $nameScanner;
    }

    public function setParameters($base, $skip, $ignore)
    {
        $this->base = $base;
        $this->skip = $skip;
        $this->ignore = $ignore;
    }

    public function refactor(Directory $directory)
    {
        // Get paths to ignore from .gitignore file.
        $exclude = $this->pathsToExclude();

        // Find all files.
        $phpFiles = $directory->findAllPhpFilesRecursivly($exclude);

        // Fix namespaces of all moved classes and get list of changes.
        $renames = $this->fixClassesNames($phpFiles);

        // Update old use statements in other files.
        // Remove unnecessary use statements in files that are now at the same path.
        // Add missing use statements for files that used to be at the same path.
        // Update usage of fully qualified class names.
        foreach ($phpFiles as $phpFile) {
            $this->updateClassNames($phpFile, $renames);
        }

        // Generate diff.
        $this->editor->save();
    }

    public function pathsToExclude()
    {
        $exclude = array_merge(
            ['.git'],
            $this->readGitIgnore(),
            $this->skip
        );

        return array_unique(array_map(function ($path) {
            return $this->base . Helpers::folderPath(ltrim(trim($path), '/'));
        }, $exclude));
    }

    public function readGitIgnore()
    {
        if (! file_exists($this->base . '.gitignore')) {
            return [];
        }

        $content = file_get_contents($this->base . '.gitignore');

        $content = preg_replace('/#.*$/m', '', $content);
        $content = preg_replace('/^(.*?)\.(.*?)$/m', '', $content);
        $content = trim(preg_replace('/\n{2,}/', '', $content));

        return preg_split('/\n/', $content);
    }

    public function fixClassesNames(CallbackFilterIterator $phpFiles)
    {
        $renames = new Set();

        foreach ($phpFiles as $phpFile) {
            if (Helpers::pathInList($phpFile->getRelativePath(), $this->ignore)) {
                continue;
            }

            $classes = $this->codeAnalysis->findClasses($phpFile);
            $class = array_shift($classes);

            if (! $class) {
                continue;
            }

            $hasNamespace = ! empty($class->declarationName()->fullyQualifiedNamespace());
            $line = $class->namespaceDeclarationLine();

            $currentClassName = $class->declarationName();
            $expectedClassName = $phpFile->extractPsr0ClassName();

            $buffer = $this->editor->openBuffer($phpFile); // This is weird to be required here

            if ($expectedClassName->shortName() !== $currentClassName->shortName()) {
                $renames->add(new PhpNameChange($currentClassName, $expectedClassName));
            }

            if (!$expectedClassName->namespaceName()->equals($currentClassName->namespaceName())) {
                $renames->add(new PhpNameChange($currentClassName->fullyQualified(), $expectedClassName->fullyQualified()));

                if ($hasNamespace) {
                    $buffer->replaceString($line, $currentClassName->fullyQualifiedNamespace(), $expectedClassName->fullyQualifiedNamespace());
                }
                else {
                    $buffer->append(1, ['', sprintf('namespace %s;', $expectedClassName->fullyQualifiedNamespace())]);
                }
            }
        }

        return $renames;
    }

    public function updateClassNames(File $phpFile, Set $renames)
    {
        $occurances = $this->nameScanner->findNames($phpFile);
        $buffer = $this->editor->openBuffer($phpFile);

        $classes = $this->codeAnalysis->findClasses($phpFile);
        $class = array_shift($classes);

        // Find namespace from file path, as we fixed invalid namespaces already.
        if (! Helpers::pathInList($phpFile->getRelativePath(), $this->ignore)) {
            $namespace = $phpFile->extractPsr0ClassName()->fullyQualifiedNamespace();
        }
        // If file was skipped from fixing namespaces we will revert to defined one.
        else {
            $namespace = array_filter($occurances, function ($occurance) {
                return $occurance->name()->type() === PhpName::TYPE_NAMESPACE;
            });

            $namespace = $namespace ? reset($namespace)->name()->fullyQualifiedName() : null;
        }

        // This variables are used purely for formating of use statements.
        $hadUses = false;
        $hasUses = false;

        // Fix use statements and create list of used classes.
        $uses = [];
        $lastUseStatementLine = ($class ? $class->namespaceDeclarationLine() : 0) + 1;

        foreach ($occurances as $occurance) {
            $name = $occurance->name();
            $line = $occurance->declarationLine();

            if ($name->type() !== PhpName::TYPE_USE) {
                continue;
            }

            $hadUses = true;

            foreach ($renames as $rename) {
                if ($rename->affects($name)) {

                    $change = $rename->change($name);

                    if ($namespace === $change->fullyQualifiedNamespace()) {
                        // Use statement is no longer necessary if classes are now under the same namespace.
                        $buffer->removeLine($line);
                    }
                    else {
                        // Replace use statement with updated class name.
                        $buffer->replaceString($line, $name->relativeName(), $change->relativeName());

                        $uses[] = $change->fullyQualifiedName();

                        $lastUseStatementLine = $line;
                        $hasUses = true;
                    }

                    continue 2;
                }
            }

            $uses[] = $name->fullyQualifiedName();

            $lastUseStatementLine = $line;
            $hasUses = true;
        }

        // Fix usage of the changed classes and add missing use statements.
        foreach ($occurances as $occurance) {
            $name = $occurance->name();
            $line = $occurance->declarationLine();

            if ($name->type() !== PhpName::TYPE_USAGE) {
                continue;
            }

            foreach ($renames as $rename) {
                if ($rename->affects($name)) {
                    $change = $rename->change($name);

                    if ($namespace !== $change->fullyQualifiedNamespace()) {
                        if ($name->isFullyQualified()) {
                            // Update class name in usage of fully qualified class.
                            $buffer->replaceString($line, $name->relativeName(), $change->relativeName());
                        }
                        else if (! in_array($change->fullyQualifiedName(), $uses)) {
                            // Add missing use statements for usage of non fully qualified class.
                            $buffer->append($lastUseStatementLine, [sprintf('use %s;', $change->fullyQualifiedName())]);

                            $uses[] = $change->fullyQualifiedName();

                            $hasUses = true;
                        }
                    }

                    continue 2;
                }
            }
        }

        // Fix formating of use statements.
        if ($hadUses && ! $hasUses) {
            // Delete unnecessary empty line after namespace.
            $buffer->removeEmptyLine($lastUseStatementLine + 2);
        }
        else if (! $hadUses && $hasUses) {
            // Add empty line after use statements.
            $buffer->append($lastUseStatementLine, ['']);
        }
    }
}
