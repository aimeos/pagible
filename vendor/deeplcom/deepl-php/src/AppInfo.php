<?php

// Copyright 2023 DeepL SE (https://www.deepl.com)
// Use of this source code is governed by an MIT
// license that can be found in the LICENSE file.

namespace DeepL;

/**
 * Information that identifies the application that uses this library.
 */
class AppInfo
{
    /** Name of the application using this library */
    public $appName;

    /** Version of the app using this library */
    public $appVersion;
    public function __construct(string $appName, string $appVersion)
    {
        $this->appName = $appName;
        $this->appVersion = $appVersion;
    }
}
