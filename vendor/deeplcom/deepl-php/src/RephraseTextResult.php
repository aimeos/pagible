<?php

// Copyright 2025 DeepL SE (https://www.deepl.com)
// Use of this source code is governed by an MIT
// license that can be found in the LICENSE file.

namespace DeepL;

/**
 * Holds the result of a text rephrasing request.
 */
class RephraseTextResult
{
    /**
     * @var string String containing the rephrased text.
     */
    public $text;

    /**
     * @var string Language code of the detected source language.
     * @see LanguageCode
     */
    public $detectedSourceLanguage;

    /**
     * @var string Language code of the target language.
     * @see LanguageCode
     */
    public $targetLanguage;

    /**
     * @throws DeepLException
     */
    public function __construct(
        string $text,
        string $detectedSourceLanguage,
        string $targetLanguage
    ) {
        $this->text = $text;
        $this->detectedSourceLanguage = LanguageCode::standardizeLanguageCode($detectedSourceLanguage);
        $this->targetLanguage = LanguageCode::standardizeLanguageCode($targetLanguage);
    }

    public function __toString(): string
    {
        return $this->text;
    }
}
