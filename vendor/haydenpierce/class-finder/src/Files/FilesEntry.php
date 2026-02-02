<?php

namespace HaydenPierce\ClassFinder\Files;

class FilesEntry
{
    /** @var string */
    private $file;

    /** @var string */
    private $php;

    /**
     * @param string $fileToInclude
     * @param string $php
     */
    public function __construct($fileToInclude, $php)
    {
        $this->file = $this->normalizePath($fileToInclude);
        $this->php = $php;
    }

    /**
     * @param string $namespace
     * @return bool
     */
    public function knowsNamespace($namespace)
    {
        $classes = $this->getClassesInFile();

        foreach($classes as $class) {
            if (strpos($class, $namespace) !== false) {
                return true;
            };
        }

        return false;
    }

    /**
     * Gets a list of classes that belong to the given namespace.
     *
     * @param string $namespace
     * @return string[]
     */
    public function getClasses($namespace)
    {
        $classes = $this->getClassesInFile();

        return array_values(array_filter($classes, function($class) use ($namespace) {
            $classNameFragments = explode('\\', $class);
            array_pop($classNameFragments);
            $classNamespace = implode('\\', $classNameFragments);

            $namespace = trim($namespace, '\\');

            return $namespace === $classNamespace;
        }));
    }

    /**
     * Dynamically execute files and check for defined classes.
     *
     * This is where the real magic happens. Since classes in a randomly included file could contain classes in any namespace,
     * (or even multiple namespaces!) we must execute the file and check for newly defined classes. This has a potential
     * downside that files being executed will execute their side effects - which may be undesirable. However, Composer
     * will require these files anyway - so hopefully causing those side effects isn't that big of a deal.
     *
     * @return array
     */
    private function getClassesInFile()
    {
        // get_declared_classes() returns a bunch of classes that are built into PHP. So we need a control here.
        $script = "var_export(get_declared_classes());";
        exec($this->php . " -r \"$script\"", $output);
        $classes = 'return ' . implode('', $output) . ';';
        $initialClasses = eval($classes);

        // clear the exec() buffer.
        unset($output);

        // This brings in the new classes. so $classes here will include the PHP defaults and the newly defined classes
        $script = "require_once '{$this->file}'; var_export(get_declared_classes());";
        exec($this->php . ' -r "' . $script . '"', $output);
        $classes = 'return ' . implode('', $output) . ';';
        $allClasses = eval($classes);

        return array_diff($allClasses, $initialClasses);
    }

    /**
     * TODO: Similar to PSR4Namespace::normalizePath. Maybe we refactor?
     * @param string $path
     * @return string
     */
    private function normalizePath($path)
    {
        $path = str_replace('\\', '/', $path);
        return $path;
    }
}
