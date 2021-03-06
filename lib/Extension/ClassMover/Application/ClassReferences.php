<?php

namespace Phpactor\Extension\ClassMover\Application;

use Phpactor\Filesystem\Domain\Filesystem;
use Phpactor\Phpactor;
use Phpactor\Extension\Core\Application\Helper\ClassFileNormalizer;
use Phpactor\ClassMover\Domain\SourceCode;
use Phpactor\ClassMover\Domain\ClassFinder;
use Phpactor\ClassMover\Domain\ClassRef;
use Phpactor\ClassMover\Domain\ClassReplacer;
use Phpactor\ClassMover\Domain\Name\FullyQualifiedName;
use Phpactor\ClassMover\Domain\Reference\NamespacedClassReferences;
use Phpactor\ClassMover\Domain\Reference\ClassReference;
use Phpactor\Filesystem\Domain\FilesystemRegistry;

class ClassReferences
{
    /**
     * @var FilesystemRegistry
     */
    private $filesystemRegistry;

    /**
     * @var ClassFinder
     */
    private $refFinder;

    /**
     * @var ClassFileNormalizer
     */
    private $classFileNormalizer;

    /**
     * @var ClassReplacer
     */
    private $refReplacer;

    public function __construct(
        ClassFileNormalizer $classFileNormalizer,
        ClassFinder $refFinder,
        ClassReplacer $refReplacer,
        FilesystemRegistry $filesystemRegistry
    ) {
        $this->classFileNormalizer = $classFileNormalizer;
        $this->filesystemRegistry = $filesystemRegistry;
        $this->refFinder = $refFinder;
        $this->refReplacer = $refReplacer;
    }

    public function replaceReferences(string $filesystemName, string $class, string $replace, bool $dryRun)
    {
        return $this->findOrReplaceReferences($filesystemName, $class, $replace, $dryRun);
    }

    public function findReferences(string $filesystemName, string $class)
    {
        return $this->findOrReplaceReferences($filesystemName, $class);
    }

    public function findOrReplaceReferences(string $filesystemName, string $class, string $replace = null, bool $dryRun = false)
    {
        $classPath = $this->classFileNormalizer->normalizeToFile($class);
        $classPath = Phpactor::normalizePath($classPath);
        $className = $this->classFileNormalizer->normalizeToClass($class);
        $filesystem = $this->filesystemRegistry->get($filesystemName);

        $results = [];
        foreach ($filesystem->fileList()->phpFiles() as $filePath) {
            $references = $this->fileReferences($filesystem, $filePath, $className, $replace, $dryRun);

            if (empty($references['references'])) {
                continue;
            }

            $references['file'] = (string) $filePath;
            $results[] = $references;
        }

        return [
            'references' => $results
        ];
    }

    private function fileReferences(Filesystem $filesystem, $filePath, $className, $replace = null, $dryRun = false)
    {
        $code = $filesystem->getContents($filePath);

        $referenceList = $this->refFinder
            ->findIn(SourceCode::fromString($code))
            ->filterForName(FullyQualifiedName::fromString($className));

        $result = [
            'references' => [],
            'replacements' => [],
        ];

        if ($referenceList->isEmpty()) {
            return $result;
        }

        if ($replace) {
            $updatedSource = $this->replaceReferencesInCode($code, $referenceList, $className, $replace);

            if (false === $dryRun) {
                file_put_contents($filePath, (string) $updatedSource);
            }
        }

        $result['references'] = $this->serializeReferenceList($code, $referenceList);

        if ($replace) {
            $newReferenceList = $this->refFinder
                ->findIn(SourceCode::fromString((string) $updatedSource))
                ->filterForName(FullyQualifiedName::fromString($replace));

            $result['replacements'] = $this->serializeReferenceList((string) $updatedSource, $newReferenceList);
        }

        return $result;
    }

    private function serializeReferenceList(string $code, NamespacedClassReferences $referenceList)
    {
        $references = [];
        /** @var $reference ClassRef */
        foreach ($referenceList as $reference) {
            $ref = $this->serializeReference($code, $reference);

            $references[] = $ref;
        }

        return $references;
    }

    private function serializeReference(string $code, ClassReference $reference)
    {
        list($lineNumber, $colNumber, $line) = $this->line($code, $reference->position()->start());
        return [
            'start' => $reference->position()->start(),
            'end' => $reference->position()->end(),
            'line' => $line,
            'line_no' => $lineNumber,
            'col_no' => $colNumber,
            'reference' => (string) $reference->name()
        ];
    }

    private function line(string $code, int $offset)
    {
        $lines = explode(PHP_EOL, $code);
        $number = 0;
        $startPosition = 0;

        foreach ($lines as $number => $line) {
            $number = $number + 1;
            $endPosition = $startPosition + strlen($line) + 1;

            if ($offset >= $startPosition && $offset <= $endPosition) {
                $col = $offset - $startPosition;
                return [ $number, $col, $line ];
            }

            $startPosition = $endPosition;
        }

        return [$number, 0, ''];
    }

    private function replaceReferencesInCode(string $code, NamespacedClassReferences $list, string $class, string $replace): SourceCode
    {
        $class = FullyQualifiedName::fromString($class);
        $replace = FullyQualifiedName::fromString($replace);
        $code = SourceCode::fromString($code);

        return $this->refReplacer->replaceReferences($code, $list, $class, $replace);
    }
}
