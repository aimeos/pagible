<?php

namespace HaydenPierce\ClassFinder\Classmap;

use HaydenPierce\ClassFinder\ClassFinder;

class ClassmapEntry
{
    /** @var string */
    private $className;

    /**
     * @param string $fullyQualifiedClassName
     */
    public function __construct($fullyQualifiedClassName)
    {
        $this->className = $fullyQualifiedClassName;
    }

    /**
     * @param string $namespace
     * @return bool
     */
    public function knowsNamespace($namespace)
    {
        return strpos($this->className, $namespace) !== false;
    }

    /**
     * @param string $namespace
     * @return bool
     */
    public function matches($namespace, $options)
    {
        if ($options === ClassFinder::RECURSIVE_MODE) {
            return $this->doesMatchAnyNamespace($namespace);
        } else {
            return $this->doesMatchDirectNamespace($namespace);
        }
    }

    /**
     * @return string
     */
    public function getClassName()
    {
        return $this->className;
    }

    /**
     * Checks if the class is a child or subchild of the given namespace.
     *
     * @param $namespace
     * @return bool
     */
    private function doesMatchAnyNamespace($namespace)
    {
        return strpos($this->getClassName(),$namespace) === 0;
    }

    /**
     * Checks if the class is a DIRECT child of the given namespace.
     *
     * @param string $namespace
     * @return bool
     */
    private function doesMatchDirectNamespace($namespace)
    {
        $classNameFragments = explode('\\', $this->getClassName());
        array_pop($classNameFragments);
        $classNamespace = implode('\\', $classNameFragments);

        $namespace = trim($namespace, '\\');

        return $namespace === $classNamespace;
    }
}
