<?php

// Copyright 2022 DeepL SE (https://www.deepl.com)
// Use of this source code is governed by an MIT
// license that can be found in the LICENSE file.

namespace DeepL;

/**
 * Exception thrown if an error occurs during document translation.
 * @see Translator::translateDocument()
 */
class DocumentTranslationException extends DeepLException
{
    /**
     * If not null, contains the handle associated with the in-progress document translation.
     * @var DocumentHandle|null
     */
    public $handle;

    public function __construct($message = "", $code = 0, $previous = null, ?DocumentHandle $handle = null)
    {
        parent::__construct($message, $code, $previous);
        $this->handle = $handle;
    }
}
