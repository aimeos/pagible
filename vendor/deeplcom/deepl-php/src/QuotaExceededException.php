<?php

// Copyright 2022 DeepL SE (https://www.deepl.com)
// Use of this source code is governed by an MIT
// license that can be found in the LICENSE file.

namespace DeepL;

/**
 * Exception thrown when the DeepL translation quota has been reached.
 * @see Translator::getUsage()
 */
class QuotaExceededException extends DeepLException
{
}
