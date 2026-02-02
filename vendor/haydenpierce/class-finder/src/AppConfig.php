<?php

namespace HaydenPierce\ClassFinder;

use HaydenPierce\ClassFinder\Exception\ClassFinderException;

class AppConfig
{
    /** @var string */
    private $appRoot;
    /** @var bool */
    public $ignorePSR4Vendors = false;

    public function __construct()
    {
        $this->appRoot = $this->findAppRoot();
    }

    /**
     * @return string
     */
    private function findAppRoot()
    {
        if ($this->appRoot) {
            $appRoot = $this->appRoot;
        } else {
            $workingDirectory = str_replace('\\', '/', __DIR__);
            $workingDirectory = str_replace('/vendor/haydenpierce/class-finder/src', '', $workingDirectory);
            $directoryPathPieces = explode('/', $workingDirectory);

            $appRoot = null;
            do {
                $path = implode('/', $directoryPathPieces) . '/composer.json';
                if (file_exists($path)) {
                    $appRoot = implode('/', $directoryPathPieces) . '/';
                } else {
                    array_pop($directoryPathPieces);
                }
            } while (is_null($appRoot) && count($directoryPathPieces) > 0);
        }

        $this->throwIfInvalidAppRoot($appRoot);

        $this->appRoot= $appRoot;
        return $this->appRoot;
    }

    /**
     * @param string $appRoot
     * @return void
     * @throws ClassFinderException
     */
    private function throwIfInvalidAppRoot($appRoot)
    {
        if (!file_exists($appRoot . 'composer.json')) {
            throw new ClassFinderException(sprintf("Could not locate composer.json. You can get around this by setting ClassFinder::\$appRoot manually. See '%s' for details.",
                'https://gitlab.com/hpierce1102/ClassFinder/blob/master/docs/exceptions/missingComposerConfig.md'
            ));
        }
    }

    /**
     * @return string
     */
    public function getAppRoot()
    {
        if ($this->appRoot === null) {
            $this->appRoot = $this->findAppRoot();
        }

        $this->throwIfInvalidAppRoot($this->appRoot);

        return $this->appRoot;
    }

    /**
     * @param string $appRoot
     * @return void
     */
    public function setAppRoot($appRoot)
    {
        $this->appRoot = $appRoot;
    }
}
