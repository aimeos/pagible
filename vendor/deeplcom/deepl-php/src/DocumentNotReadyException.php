<?php

// Copyright 2022 DeepL SE (https://www.deepl.com)
// Use of this source code is governed by an MIT
// license that can be found in the LICENSE file.

namespace DeepL;

/**
 * Exception thrown when attempting to download a document that is not ready for download.
 * @see Translator::downloadDocument()
 */
class DocumentNotReadyException extends DeepLException
{
}
