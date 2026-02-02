<?php

// Copyright 2022 DeepL SE (https://www.deepl.com)
// Use of this source code is governed by an MIT
// license that can be found in the LICENSE file.

namespace DeepL;

/**
 * Options that can be specified when translating documents.
 * @see Translator::translateDocument()
 * @see Translator::uploadDocument()
 */
final class TranslateDocumentOptions
{
    /** Controls whether translations should lean toward formal or informal language.
     * - 'less': use informal language.
     * - 'more': use formal, more polite language.
     * - 'default': use default formality.
     * - 'prefer_less': use informal language if available, otherwise default.
     * - 'prefer_more': use formal, more polite language if available, otherwise default.
     */
    public const FORMALITY = 'formality';

    /** Set to string containing a glossary ID to use the glossary for translation. */
    public const GLOSSARY = 'glossary';

    /** Set to `true` in order to use Document Minification for translation, if available. */
    public const ENABLE_DOCUMENT_MINIFICATION = 'enable_document_minification';

    /** Dictionary of extra parameters to pass in the body of the HTTP request.
     * Can be used to access beta features, override built-in parameters, or for testing purposes.
     * Keys in this array will be added to the request body and can override existing keys.
     * Values must be of string type.
     */
    public const EXTRA_BODY_PARAMETERS = 'extra_body_parameters';
}
