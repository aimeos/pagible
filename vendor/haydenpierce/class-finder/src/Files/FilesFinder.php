<?php

namespace HaydenPierce\ClassFinder\Files;

use HaydenPierce\ClassFinder\Exception\ClassFinderException;
use HaydenPierce\ClassFinder\FinderInterface;

class FilesFinder implements FinderInterface
{
    /** @var FilesEntryFactory */
    private $factory;

    /**
     * @param FilesEntryFactory $factory
     * @throws ClassFinderException
     */
    public function __construct(FilesEntryFactory $factory)
    {
        $this->factory = $factory;

        if (!function_exists('exec')) {
            throw new ClassFinderException(sprintf(
                'FilesFinder requires that exec() is available. Check your php.ini to see if it is disabled. See "%s" for details.',
                'https://gitlab.com/hpierce1102/ClassFinder/blob/master/docs/exceptions/filesExecNotAvailable.md'
            ));
        }
    }

    /**
     * @param string $namespace
     * @return bool
     */
    public function isNamespaceKnown($namespace)
    {
        $filesEntries = $this->factory->getFilesEntries();

        foreach($filesEntries as $filesEntry) {
            if ($filesEntry->knowsNamespace($namespace)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param string $namespace
     * @param int $options
     * @return string[]
     */
    public function findClasses($namespace, $options)
    {
        $filesEntries = $this->factory->getFilesEntries();

        return array_reduce($filesEntries, function($carry, FilesEntry $entry) use ($namespace){
            return array_merge($carry, $entry->getClasses($namespace));
        }, array());
    }
}
