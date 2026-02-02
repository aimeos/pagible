<?php

namespace HaydenPierce\ClassFinder\Classmap;

use HaydenPierce\ClassFinder\FinderInterface;

class ClassmapFinder implements FinderInterface
{
    /** @var ClassmapEntryFactory */
    private $factory;

    public function __construct(ClassmapEntryFactory $factory)
    {
        $this->factory = $factory;
    }

    /**
     * @param string $namespace
     * @return bool
     */
    public function isNamespaceKnown($namespace)
    {
        $classmapEntries = $this->factory->getClassmapEntries();

        foreach($classmapEntries as $classmapEntry) {
            if ($classmapEntry->knowsNamespace($namespace)) {
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
        $classmapEntries = $this->factory->getClassmapEntries();

        $matchingEntries = array_filter($classmapEntries, function(ClassmapEntry $entry) use ($namespace, $options) {
            return $entry->matches($namespace, $options);
        });

        return array_map(function(ClassmapEntry $entry) {
            return $entry->getClassName();
        }, $matchingEntries);
    }
}
