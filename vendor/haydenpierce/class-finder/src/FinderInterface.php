<?php

namespace HaydenPierce\ClassFinder;

interface FinderInterface
{
    /**
     * Find classes in a given namespace.
     *
     * @param string $namespace
     * @param int $options
     * @return string[]
     */
    public function findClasses($namespace, $options);

    /**
     * Check if a given namespace is known.
     *
     * A namespace is "known" if a Finder can determine that the autoloader can create classes from that namespace.
     *
     * For instance:
     * If given a classmap for "TestApp1\Foo\Bar\Baz", the namespace "TestApp1\Foo" is known, even if nothing loads
     * from that namespace directly. It is known because classes that include that namespace are known.
     *
     * @param string $namespace
     * @return bool
     */
    public function isNamespaceKnown($namespace);
}
