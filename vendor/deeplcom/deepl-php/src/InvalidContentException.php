<?php

// Copyright 2022 DeepL SE (https://www.deepl.com)
// Use of this source code is governed by an MIT
// license that can be found in the LICENSE file.

namespace DeepL;

use JsonException;

class InvalidContentException extends DeepLException
{
    public function __construct(JsonException $exception)
    {
        parent::__construct($exception->getMessage(), $exception->getCode(), $exception);
    }
}
