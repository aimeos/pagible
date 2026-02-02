<?php

namespace HaydenPierce\ClassFinder\Classmap;

use HaydenPierce\ClassFinder\AppConfig;

class ClassmapEntryFactory
{
    /** @var AppConfig */
    private $appConfig;

    public function __construct(AppConfig $appConfig)
    {
        $this->appConfig = $appConfig;
    }

    /**
     * @return ClassmapEntry[]
     */
    public function getClassmapEntries()
    {
        // Composer will compile user declared mappings to autoload_classmap.php. So no additional work is needed
        // to fetch user provided entries.
        $classmap = require($this->appConfig->getAppRoot() . 'vendor/composer/autoload_classmap.php');

        // if classmap has no entries return empty array
        if(count($classmap) == 0) {
            return array();
        }

        $classmapKeys = array_keys($classmap);
        return array_map(function($index) use ($classmapKeys){
            return new ClassmapEntry($classmapKeys[$index]);
        }, range(0, count($classmap) - 1));
    }
}
