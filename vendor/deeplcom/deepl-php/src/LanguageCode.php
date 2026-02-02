<?php

// Copyright 2022 DeepL SE (https://www.deepl.com)
// Use of this source code is governed by an MIT
// license that can be found in the LICENSE file.

namespace DeepL;

/**
 * Language codes for the languages currently supported by DeepL translation. New languages may be added in the future;
 * to retrieve the currently supported languages use the getSourceLanguages() and getSourceLanguages() functions.
 * @see Translator::getSourceLanguages()
 * @see Translator::getTargetLanguages()
 */
class LanguageCode
{
    /** Arabic language code, may be used as source or target language. */
    public const ARABIC = 'ar';

    /** Bulgarian language code, may be used as source or target language. */
    public const BULGARIAN = 'bg';

    /** Czech language code, may be used as source or target language. */
    public const CZECH = 'cs';

    /** Danish language code, may be used as source or target language. */
    public const DANISH = 'da';

    /** German language code, may be used as source or target language. */
    public const GERMAN = 'de';

    /** Greek language code, may be used as source or target language. */
    public const GREEK = 'el';

    /** English language code, may only be used as a source language. */
    public const ENGLISH = 'en';

    /** British English language code, may only be used as a target language. */
    public const ENGLISH_BRITISH = 'en-GB';

    /** American English language code, may only be used as a target language. */
    public const ENGLISH_AMERICAN = 'en-US';

    /** Spanish language code, may be used as source or target language. */
    public const SPANISH = 'es';

    /** Estonian language code, may be used as source or target language. */
    public const ESTONIAN = 'et';

    /** Finnish language code, may be used as source or target language. */
    public const FINNISH = 'fi';

    /** French language code, may be used as source or target language. */
    public const FRENCH = 'fr';

    /** Hungarian language code, may be used as source or target language. */
    public const HUNGARIAN = 'hu';

    /** Indonesian language code, may be used as source or target language. */
    public const INDONESIAN = 'id';

    /** Italian language code, may be used as source or target language. */
    public const ITALIAN = 'it';

    /** Japanese language code, may be used as source or target language. */
    public const JAPANESE = 'ja';

    /** Korean language code, may be used as source or target language. */
    public const KOREAN = 'ko';

    /** Lithuanian language code, may be used as source or target language. */
    public const LITHUANIAN = 'lt';

    /** Latvian language code, may be used as source or target language. */
    public const LATVIAN = 'lv';

    /** Norwegian (bokmål) language code, may be used as source or target language. */
    public const NORWEGIAN = 'nb';

    /** Dutch language code, may be used as source or target language. */
    public const DUTCH = 'nl';

    /** Polish language code, may be used as source or target language. */
    public const POLISH = 'pl';

    /** Portuguese language code, may only be used as a source language. */
    public const PORTUGUESE = 'pt';

    /** Brazilian Portuguese language code, may only be used as a target language. */
    public const PORTUGUESE_BRAZILIAN = 'pt-BR';

    /** European Portuguese language code, may only be used as a target language. */
    public const PORTUGUESE_EUROPEAN = 'pt-PT';

    /** Romanian language code, may be used as source or target language. */
    public const ROMANIAN = 'ro';

    /** Russian language code, may be used as source or target language. */
    public const RUSSIAN = 'ru';

    /** Slovak language code, may be used as source or target language. */
    public const SLOVAK = 'sk';

    /** Slovenian language code, may be used as source or target language. */
    public const SLOVENIAN = 'sl';

    /** Swedish language code, may be used as source or target language. */
    public const SWEDISH = 'sv';

    /** Turkish language code, may be used as source or target language. */
    public const TURKISH = 'tr';

    /** Ukrainian language code, may be used as source or target language. */
    public const UKRAINIAN = 'uk';

    /** Chinese language code, may be used as source or target language. */
    public const CHINESE = 'zh';

    /**
     * Changes the upper- and lower-casing of the given language code to match ISO 639-1 with an optional regional code
     * from ISO 3166-1.
     * @param string $langCode String containing language code to standardize.
     * @return string String containing the standardized language code.
     * @throws DeepLException If language code is an empty string.
     */
    public static function standardizeLanguageCode(string $langCode): string
    {
        if (strlen($langCode) === 0) {
            throw new DeepLException('langCode must be a non-empty string');
        }

        $exploded = explode('-', $langCode, 2);

        if (isset($exploded[1])) {
            return strtolower($exploded[0]) . '-' . strtoupper($exploded[1]);
        } else {
            return strtolower($exploded[0]);
        }
    }

    /**
     * Removes the regional variant (if any) from a language code, for example inputs 'en' and 'en-US' both return 'en'.
     * @param string $langCode String containing language code to convert.
     * @return string String containing language code without a regional variant.
     * @throws DeepLException
     */
    public static function removeRegionalVariant(string $langCode): string
    {
        if (strlen($langCode) === 0) {
            throw new DeepLException('langCode must be a non-empty string');
        }

        $exploded = explode('-', $langCode, 2);
        return strtolower($exploded[0]);
    }
}
