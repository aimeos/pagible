<?php

namespace HaydenPierce\ClassFinder;

use HaydenPierce\ClassFinder\Classmap\ClassmapEntryFactory;
use HaydenPierce\ClassFinder\Classmap\ClassmapFinder;
use HaydenPierce\ClassFinder\Files\FilesEntryFactory;
use HaydenPierce\ClassFinder\Files\FilesFinder;
use HaydenPierce\ClassFinder\PSR4\PSR4Finder;
use HaydenPierce\ClassFinder\PSR4\PSR4NamespaceFactory;

class ClassFinder
{
    const STANDARD_MODE = 1;
    const RECURSIVE_MODE = 2;

    /** @var AppConfig */
    private static $config;

    /** @var PSR4Finder */
    private static $psr4;

    /** @var ClassmapFinder */
    private static $classmap;

    /** @var FilesFinder */
    private static $files;

    /** @var boolean */
    private static $useFilesSupport = false;

    /** @var boolean */
    private static $usePSR4Support = true;

    /** @var boolean */
    private static $useClassmapSupport = true;

    /**
     * @return void
     */
    private static function initialize()
    {
        if (!(self::$config instanceof AppConfig)) {
            self::$config = new AppConfig();
        }

        if (!(self::$psr4 instanceof PSR4Finder)) {
            $PSR4Factory = new PSR4NamespaceFactory(self::$config);
            self::$psr4 = new PSR4Finder($PSR4Factory);
        }

        if (!(self::$classmap instanceof ClassmapFinder)) {
            $classmapFactory = new ClassmapEntryFactory(self::$config);
            self::$classmap = new ClassmapFinder($classmapFactory);
        }

        if (!(self::$files instanceof FilesFinder) && self::$useFilesSupport) {
            $filesFactory = new FilesEntryFactory(self::$config);
            self::$files = new FilesFinder($filesFactory);
        }
    }

    /**
     * Identify classes in a given namespace.
     *
     * @param string $namespace
     * @param int $options
     * @return string[]
     *
     * @throws \Exception
     */
    public static function getClassesInNamespace($namespace, $options = self::STANDARD_MODE)
    {
        self::initialize();

        $findersWithNamespace = self::findersWithNamespace($namespace);

        $classes = array_reduce($findersWithNamespace, function($carry, FinderInterface $finder) use ($namespace, $options){
            return array_merge($carry, $finder->findClasses($namespace, $options));
        }, array());

        return array_values(array_unique($classes));
    }

    /**
     * Check if a given namespace contains any classes.
     *
     * @param string $namespace
     * @return bool
     */
    public static function namespaceHasClasses($namespace)
    {
        self::initialize();

        return count(self::findersWithNamespace($namespace)) > 0;
    }

    /**
     * @param string $appRoot
     * @return void
     */
    public static function setAppRoot($appRoot)
    {
        self::initialize();
        self::$config->setAppRoot($appRoot);
    }

    /**
     * @return void
     */
    public static function enableExperimentalFilesSupport()
    {
        self::$useFilesSupport = true;
    }

    /**
     * @return void
     */
    public static function disableExperimentalFilesSupport()
    {
        self::$useFilesSupport = false;
    }

    /**
     * @return void
     */
    public static function enablePSR4Support()
    {
        self::$usePSR4Support = true;
    }

    /**
     * @return void
     */
    public static function disablePSR4Support()
    {
        self::$usePSR4Support = false;
    }

    /**
     * @return void
     */
    public static function disablePSR4Vendors()
    {
      self::initialize();
      self::$config->ignorePSR4Vendors = true;
    }

    /**
     * @return void
     */
    public static function enablePSR4Vendors()
    {
      self::initialize();
      self::$config->ignorePSR4Vendors = false;
    }

    /**
     * @return void
     */
    public static function enableClassmapSupport()
    {
        self::$useClassmapSupport = true;
    }

    /**
     * @return void
     */
    public static function disableClassmapSupport()
    {
        self::$useClassmapSupport = false;
    }

    /**
     * @return FinderInterface[]
     */
    private static function getSupportedFinders()
    {
        $supportedFinders = array();

        /*
         * This is done for testing. For some tests, allowing PSR4 classes contaminates the test results. This could also be
         * disabled for performance reasons (less finders in use means less work), but most people probably won't do that.
         */
        if (self::$usePSR4Support) {
            $supportedFinders[] = self::$psr4;
        }

        /*
         * This is done for testing. For some tests, allowing classmap classes contaminates the test results. This could also be
         * disabled for performance reasons (less finders in use means less work), but most people probably won't do that.
         */
        if (self::$useClassmapSupport) {
            $supportedFinders[] = self::$classmap;
        }

        /*
         * Files support is tucked away behind a flag because it will need to use some kind of shell access via exec, or
         * system.
         *
         * #1 Many environments (such as shared space hosts) may not allow these functions, and attempting to call
         * these functions will blow up.
         * #2 I've heard of performance issues with calling these functions.
         * #3 Files support probably doesn't benefit most projects.
         * #4 Using exec() or system() is against many PHP developers' religions.
         */
        if (self::$useFilesSupport) {
            $supportedFinders[] = self::$files;
        }

        return $supportedFinders;
    }

    /**
     * @param string $namespace
     * @return FinderInterface[]
     */
    private static function findersWithNamespace($namespace)
    {
        $findersWithNamespace = array_filter(self::getSupportedFinders(), function (FinderInterface $finder) use ($namespace) {
            return $finder->isNamespaceKnown($namespace);
        });

        return $findersWithNamespace;
    }
}
