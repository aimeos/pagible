<?php

// Copyright 2022 DeepL SE (https://www.deepl.com)
// Use of this source code is governed by an MIT
// license that can be found in the LICENSE file.

namespace DeepL;

/**
 * Information about a language supported by DeepL translator.
 */
class Language
{
    /**
     * @var string Name of the language in English.
     */
    public $name;

    /**
     * @var string Language code according to ISO 639-1, for example 'en'. Some target languages also include the
     * regional variant according to ISO 3166-1, for example 'en-US'.
     * @see LanguageCode
     */
    public $code;

    /**
     * @var boolean|null For target languages only, specifies whether the formality option is available for the target
     * language. This parameter is null for source languages.
     */
    public $supportsFormality;

    public function __construct(string $name, string $code, ?bool $supportsFormality)
    {
        $this->name = $name;
        $this->code = $code;
        $this->supportsFormality = $supportsFormality;
    }

    public function __toString(): string
    {
        return "$this->name ($this->code)";
    }
}
