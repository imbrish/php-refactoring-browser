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

class FixMovedClasses
{
    private $codeAnalysis;
    private $editor;
    private $nameScanner;

    public function __construct($codeAnalysis, $editor, $nameScanner)
    {
        $this->codeAnalysis = $codeAnalysis;
        $this->editor = $editor;
        $this->nameScanner = $nameScanner;
    }

    public function refactor(Directory $directory, $base)
    {
        // Find all files.
        $phpFiles = $directory->findAllPhpFilesRecursivly();

        // Fix namespaces of all moved classes and get list of changes.
        // This will FAIL if file has no namespace defined in the first place.
        $renames = $this->fixClassesNames($phpFiles, $base);

        // Update old namespaces used by other files.
        foreach ($phpFiles as $phpFile) {
            $this->updateOldNamespaces($phpFile, $renames);
        }

        // Add missing namespaces for files that used to be at the same path.

        // Remove unnecessary namespaces in files that ar not at the same path.

        // Generate diff.
        $this->editor->save();
    }

    public function fixClassesNames(CallbackFilterIterator $phpFiles, $base)
    {
        $renames = new Set();

        foreach ($phpFiles as $phpFile) {
            $classes = $this->codeAnalysis->findClasses($phpFile);

            if (count($classes) !== 1) {
                continue;
            }

            $class = $classes[0];
            $currentClassName = $class->declarationName()->fixNames($base);
            $expectedClassName = $phpFile->extractPsr0ClassName()->fixNames($base);

            $buffer = $this->editor->openBuffer($phpFile); // This is weird to be required here

            if ($expectedClassName->shortName() !== $currentClassName->shortName()) {
                $renames->add(new PhpNameChange($currentClassName, $expectedClassName));
            }

            if (!$expectedClassName->namespaceName()->equals($currentClassName->namespaceName())) {
                $renames->add(new PhpNameChange($currentClassName->fullyQualified(), $expectedClassName->fullyQualified()));

                $buffer->replaceString(
                    $class->namespaceDeclarationLine(),
                    $currentClassName->namespaceName()->fullyQualifiedName(),
                    $expectedClassName->namespaceName()->fullyQualifiedName()
                );
            }
        }

        return $renames;
    }

    public function updateOldNamespaces(File $phpFile, Set $renames)
    {
        $classes = $this->codeAnalysis->findClasses($phpFile);
        $occurances = $this->nameScanner->findNames($phpFile);

        // Find file namespace.
        $namespace = array_filter($occurances, function ($occurance) {
            return $occurance->name()->type() === PhpName::TYPE_NAMESPACE;
        });

        $namespace = $namespace ? reset($namespace)->name()->fullyQualifiedName() : null;

        // Fix use statements and create list of used classes.
        $uses = [];

        foreach ($occurances as $occurance) {
            $name = $occurance->name();
            $line = $occurance->declarationLine();

            if ($name->type() !== PhpName::TYPE_USE) {
                continue;
            }

            foreach ($renames as $rename) {
                if ($rename->affects($name)) {
                    $buffer = $this->editor->openBuffer($occurance->file());
                    $change = $rename->change($name);

                    if ($namespace === $change->namespaceName()->fullyQualifiedName()) {
                        // Use statement is no longer necessary if classes are now under the same namespace.
                        $buffer->removeLine($line);
                    }
                    else {
                        // Replace class in use statement.
                        $buffer->replaceString($line, $name->relativeName(), $change->relativeName());
                        $uses[] = $rename->change($name)->fullyQualifiedName();
                    }

                    continue 2;
                }
            }

            $uses[] = $name->fullyQualifiedName();
        }

        // Fix usage of the class and add new use statements if necessary.
        
    }
}
