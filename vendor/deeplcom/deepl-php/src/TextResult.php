<?php

// Copyright 2022 DeepL SE (https://www.deepl.com)
// Use of this source code is governed by an MIT
// license that can be found in the LICENSE file.

namespace DeepL;

/**
 * Holds the result of a text translation request.
 */
class TextResult
{
    /**
     * @var string String containing the translated text.
     */
    public $text;

    /**
     * @var string Language code of the detected source language.
     * @see LanguageCode
     */
    public $detectedSourceLang;

    /**
     * @var int Number of characters billed for this text.
     */
    public $billedCharacters;

    /**
     * @var string|null Model type used for the translation.
     * @see TranslateTextOptions::MODEL_TYPE
     */
    public $modelTypeUsed;

    /**
     * @throws DeepLException
     */
    public function __construct(
        string $text,
        string $detectedSourceLang,
        int $billedCharacters,
        ?string $modelTypeUsed = null
    ) {
        $this->text = $text;
        $this->detectedSourceLang = LanguageCode::standardizeLanguageCode($detectedSourceLang);
        $this->billedCharacters = $billedCharacters;
        $this->modelTypeUsed = $modelTypeUsed;
    }

    public function __toString(): string
    {
        return $this->text;
    }
}
