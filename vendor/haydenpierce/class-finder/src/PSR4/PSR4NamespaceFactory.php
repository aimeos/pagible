<?php

namespace HaydenPierce\ClassFinder\PSR4;

use HaydenPierce\ClassFinder\AppConfig;
use HaydenPierce\ClassFinder\Exception\ClassFinderException;

class PSR4NamespaceFactory
{
    /** @var AppConfig */
    private $appConfig;

    public function __construct(AppConfig $appConfig)
    {
        $this->appConfig = $appConfig;
    }

    /**
     * @return PSR4Namespace[]
     */
    public function getPSR4Namespaces()
    {
        $namespaces = $this->getUserDefinedPSR4Namespaces();
        if (!$this->appConfig->ignorePSR4Vendors) {
            $vendorNamespaces = require($this->appConfig->getAppRoot() . 'vendor/composer/autoload_psr4.php');
            $namespaces = array_merge_recursive($vendorNamespaces, $namespaces);
        }
        // There's some wackiness going on here for PHP 5.3 compatibility.
        $names = array_keys($namespaces);
        $directories = array_values($namespaces);
        $self = $this;
        $namespaces = array_map(function($index) use ($self, $names, $directories) {
            return $self->createNamespace($names[$index], $directories[$index]);
        },range(0, count($namespaces) - 1));

        return $namespaces;
    }

    /**
     * @return string[]
     */
    private function getUserDefinedPSR4Namespaces()
    {
        $appRoot = $this->appConfig->getAppRoot();

        $composerJsonPath = $appRoot . 'composer.json';
        $composerConfig = json_decode(file_get_contents($composerJsonPath), true);
        return isset($composerConfig['autoload']['psr-4']) ? (array)$composerConfig['autoload']['psr-4'] : array();
    }

    /**
     * Creates a namespace from composer_psr4.php and composer.json autoload.psr4 items.
     *
     * @param string $namespace
     * @param string[] $directories
     * @return PSR4Namespace
     * @throws ClassFinderException
     */
    public function createNamespace($namespace, $directories)
    {
        if (is_string($directories)) {
            // This is an acceptable format according to composer.json
            $directories = array($directories);
        } elseif (is_array($directories)) {
            // composer_psr4.php seems to put everything in this format
        } else {
            throw new ClassFinderException('Unknown PSR4 definition.');
        }

        $self = $this;
        $appConfig = $this->appConfig;
        $directories = array_map(function($directory) use ($self, $appConfig) {
            if ($self->isAbsolutePath($directory)) {
                return $directory;
            } else {
                return $appConfig->getAppRoot() . $directory;
            }
        }, $directories);

        $directories = array_filter(array_map(function($directory) {
            return realpath($directory);
        }, $directories));

        $psr4Namespace = new PSR4Namespace($namespace, $directories);

        $subNamespaces = $this->getSubnamespaces($psr4Namespace);
        $psr4Namespace->setDirectSubnamespaces($subNamespaces);

        return $psr4Namespace;
    }

    /**
     * @param PSR4Namespace $psr4Namespace
     * @return PSR4Namespace[]
     */
    private function getSubnamespaces(PSR4Namespace $psr4Namespace)
    {
        // Scan it's own directories.
        $directories = $psr4Namespace->findDirectories();

        $self = $this;
        $subnamespaces = array_map(function($directory) use ($self, $psr4Namespace){
            $segments = explode('/', $directory);
            $subnamespaceSegment = array_pop($segments);

            $namespace = $psr4Namespace->getNamespace() . "\\" . $subnamespaceSegment . "\\";
            return $self->createNamespace($namespace, $directory);
        }, $directories);

        return $subnamespaces;
    }

    /**
     * Check if a path is absolute.
     *
     * Mostly this answer https://stackoverflow.com/a/38022806/3000068
     * A few changes: Changed exceptions to be ClassFinderExceptions, removed some ctype dependencies,
     * updated the root prefix regex to handle Window paths better.
     *
     * @param string $path
     * @return bool
     * @throws ClassFinderException
     */
    public function isAbsolutePath($path) {
        if (!is_string($path)) {
            $mess = sprintf('String expected but was given %s', gettype($path));
            throw new ClassFinderException($mess);
        }

        // Optional wrapper(s).
        $regExp = '%^(?<wrappers>(?:[[:print:]]{2,}://)*)';
        // Optional root prefix.
        $regExp .= '(?<root>(?:[[:alpha:]]:[/\\\\]|/)?)';
        // Actual path.
        $regExp .= '(?<path>(?:[[:print:]]*))$%';
        $parts = array();
        if (!preg_match($regExp, $path, $parts)) {
            $mess = sprintf('Path is NOT valid, was given %s', $path);
            throw new ClassFinderException($mess);
        }
        if ('' !== $parts['root']) {
            return true;
        }
        return false;
    }
}
