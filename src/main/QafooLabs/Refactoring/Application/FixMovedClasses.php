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

class FixMovedClasses
{
    private $editor;
    private $nameScanner;

    public function __construct($editor, $nameScanner)
    {
        $this->editor = $editor;
        $this->nameScanner = $nameScanner;
    }

    public function refactor(Directory $directory)
    {
        // Find all files.
        $phpFiles = $directory->findAllPhpFilesRecursivly();

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

    public function fixClassesNames(array $phpFiles)
    {
        $renames = new Set();

        foreach ($phpFiles as $phpFile) {
            if (! $phpFile->shouldFixNamespace()) {
                continue;
            }

            $currentClassName = $phpFile->getClass()->declarationName();
            $expectedClassName = $phpFile->extractPsr0ClassName();

            $buffer = $this->editor->openBuffer($phpFile); // This is weird to be required here

            if ($expectedClassName->shortName() !== $currentClassName->shortName()) {
                $renames->add(new PhpNameChange($currentClassName, $expectedClassName));
            }

            if (!$expectedClassName->namespaceName()->equals($currentClassName->namespaceName())) {
                $renames->add(new PhpNameChange($currentClassName->fullyQualified(), $expectedClassName->fullyQualified()));

                if ($phpFile->hasNamespaceDeclaration()) {
                    $buffer->replaceString(
                        $phpFile->namespaceDeclarationLine(), 
                        $currentClassName->fullyQualifiedNamespace(),
                        $expectedClassName->fullyQualifiedNamespace()
                    );
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

        // Find namespace from file path, as we fixed invalid namespaces already.
        if ($phpFile->shouldFixNamespace()) {
            $namespace = $phpFile->extractPsr0ClassName()->fullyQualifiedNamespace();
        }
        // If file was skipped from fixing namespaces we will revert to defined one.
        else {
            $namespace = $phpFile->fullyQualifiedNamespace();
        }

        // This variables are used purely for formating of use statements.
        $hadUses = false;
        $hasUses = false;

        // Fix use statements and create list of used classes.
        $lastUseStatementLine = $phpFile->namespaceDeclarationLine() + 1;

        $uses = [];

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
