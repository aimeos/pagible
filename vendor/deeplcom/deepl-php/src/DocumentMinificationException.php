<?php

// Copyright 2024 DeepL SE (https://www.deepl.com)
// Use of this source code is governed by an MIT
// license that can be found in the LICENSE file.

namespace DeepL;

/**
 * Exception thrown if an error occurs during the minification phase of document minification.
 * @see Translator::translateDocument()
 * @see DocumentMinifier::minifyDocument
 */
class DocumentMinificationException extends DocumentTranslationException
{
    public function __construct($message = "", $code = 0, $previous = null, ?DocumentHandle $handle = null)
    {
        parent::__construct($message, $code, $previous, $handle);
    }
}
