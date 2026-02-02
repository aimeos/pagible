<?php

// Copyright 2025 DeepL SE (https://www.deepl.com)
// Use of this source code is governed by an MIT
// license that can be found in the LICENSE file.

namespace DeepL;

use JsonException;

class DeepLClient extends Translator
{

    public function __construct(string $authKey, array $options = [])
    {
        parent::__construct($authKey, $options);
    }

    /**
     * Rephrases the given text using DeepL's API.
     * @param string|string[] $texts A single string or array of strings to rephrase
     * @param string|null $targetLang Optional target language code
     * @param array $options Rephrase options to apply. See \DeepL\DeepLClientOptions.
     * @return RephraseTextResult|RephraseTextResult[] A RephraseTextResult or array of
     * RephraseTextResult objects containing rephrased texts
     * @throws DeepLException
     * @see \DeepL\DeepLClientOptions
     */
    public function rephraseText($texts, ?string $targetLang = null, array $options = [])
    {
        $params = $this->buildRephraseBodyParams(
            $targetLang,
            $options[RephraseTextOptions::WRITING_STYLE] ?? null,
            $options[RephraseTextOptions::TONE] ?? null
        );
        $this->validateAndAppendTexts($params, $texts);

        $response = $this->client->sendRequestWithBackoff(
            'POST',
            '/v2/write/rephrase',
            [HttpClientWrapper::OPTION_PARAMS => $params]
        );
        $this->checkStatusCode($response);
        list(, $content) = $response;

        try {
            $json = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new InvalidContentException($exception);
        }

        $improvements = isset($json['improvements']) && is_array($json['improvements']) ? $json['improvements'] : [];
        $output = [];
        foreach ($improvements as $improvement) {
            $text = $improvement['text'] ?? '';
            $detectedSourceLanguage = $improvement['detected_source_language'] ?? '';
            $targetLanguage = $improvement['target_language'] ?? '';
            $output[] = new RephraseTextResult($text, $detectedSourceLanguage, $targetLanguage);
        }

        return is_array($texts) ? $output : $output[0];
    }

    /**
     * Creates a new glossary on DeepL server with given name with all the specified
     * dictionaries, each with their own language pair and entries.
     * @param string $name User-defined name to assign to the glossary.
     * @param MultilingualGlossaryDictionaryEntries[] $dictionaries A list of MultilingualGlossaryDictionaryEntries
     *      which each contains entries for a particular language pair
     * @return MultilingualGlossaryInfo Details about the created glossary.
     * @throws DeepLException
     */
    public function createMultilingualGlossary(
        string $name,
        array $dictionaries
    ): MultilingualGlossaryInfo {

        if (strlen($name) === 0) {
            throw new DeepLException('glossary name must be a non-empty string');
        }

        $params = [
            'name' => $name,
            'dictionaries' => array_map(function (MultilingualGlossaryDictionaryEntries $entries) {
                return $entries->toObject();
            }, $dictionaries),
        ];

        $response = $this->client->sendRequestWithBackoff(
            'POST',
            '/v3/glossaries',
            [HttpClientWrapper::OPTION_JSON => json_encode($params)]
        );
        $this->checkStatusCode($response, false, true);
        list(, $content) = $response;
        return MultilingualGlossaryInfo::parseJson($content);
    }

    /**
     * Creates a new glossary on DeepL server with given name, languages, and entries.
     * @param string $name User-defined name to assign to the glossary.
     * @param string $sourceLang Language code of the glossary source terms.
     * @param string $targetLang Language code of the glossary target terms.
     * @param string $csvContent String containing CSV content.
     * @return MultilingualGlossaryInfo Details about the created glossary.
     * @throws DeepLException
     */
    public function createMultilingualGlossaryFromCsv(
        string $name,
        string $sourceLang,
        string $targetLang,
        string $csvContent
    ): MultilingualGlossaryInfo {

        if (strlen($name) === 0) {
            throw new DeepLException('glossary name must be a non-empty string');
        }

        $params = [
            'name' => $name,
            'dictionaries' => [
                [
                    'source_lang' => $sourceLang,
                    'target_lang' => $targetLang,
                    'entries_format' => 'csv',
                    'entries' => $csvContent,
                ]
            ]
        ];

        $response = $this->client->sendRequestWithBackoff(
            'POST',
            '/v3/glossaries',
            [HttpClientWrapper::OPTION_JSON => json_encode($params)]
        );
        $this->checkStatusCode($response, false, true);
        list(, $content) = $response;
        return MultilingualGlossaryInfo::parseJson($content);
    }

    /**
     * Updates or creates a glossary dictionary with given entries for the
     * source and target languages. Either updates entries if they exist for
     * the given language pair, or adds new ones to the dictionary if not.
     * @param string|MultilingualGlossaryInfo $glossary Glossary ID or MultilingualGlossaryInfo of glossary to update.
     * @param string|null $name Optional, new name for glossary.
     * @param array|null $dictionaries Optional, array of MultilingualGlossaryDictionaryEntries to update or add to
     *  glossary.
     * @return MultilingualGlossaryInfo Info about the updated glossary.
     * @throws DeepLException
     */
    public function updateMultilingualGlossary(
        $glossary,
        ?string $name,
        ?array $dictionaries
    ): MultilingualGlossaryInfo {
        $glossaryId = MultilingualGlossaryInfo::getGlossaryId($glossary);
        $params = [];
        if (isset($name)) {
            $params['name'] = $name;
        }
        if (isset($dictionaries) and count($dictionaries) > 0) {
            $params['dictionaries'] = array_map(function (MultilingualGlossaryDictionaryEntries $entries) {
                return $entries->toObject();
            }, $dictionaries);
        }

        $response = $this->client->sendRequestWithBackoff(
            'PATCH',
            "/v3/glossaries/$glossaryId",
            [HttpClientWrapper::OPTION_JSON => json_encode($params)]
        );
        $this->checkStatusCode($response, false, true);
        list(, $content) = $response;
        return MultilingualGlossaryInfo::parseJson($content);
    }

    /**
     * Replaces a glossary dictionary with given entries for the
     * source and target languages. Either replaces dictionary if one exists for
     * the given language pair, or adds new dictionary if not.
     * @param string|MultilingualGlossaryInfo $glossary Glossary ID or MultilingualGlossaryInfo of glossary to
     *  update.
     * @param MultilingualGlossaryDictionaryEntries $dictionaryEntries Replacement dictionary with entries.
     * @return MultilingualGlossaryDictionaryInfo Info about the dictionary.
     * @throws DeepLException
     */
    public function replaceMultilingualGlossaryDictionary(
        $glossary,
        MultilingualGlossaryDictionaryEntries $dictionaryEntries
    ): MultilingualGlossaryDictionaryInfo {
        $glossaryId = MultilingualGlossaryInfo::getGlossaryId($glossary);
        $params = $dictionaryEntries->toObject();

        $response = $this->client->sendRequestWithBackoff(
            'PUT',
            "/v3/glossaries/$glossaryId/dictionaries",
            [HttpClientWrapper::OPTION_JSON => json_encode($params)]
        );
        $this->checkStatusCode($response, false, true);
        list(, $content) = $response;
        return MultilingualGlossaryDictionaryInfo::parseJson($content);
    }

    /**
     * Gets information about an existing glossary.
     * @param string|MultilingualGlossaryInfo $glossary Glossary ID or MultilingualGlossaryInfo of glossary to get
     *  info.
     * @return MultilingualGlossaryInfo MultilingualGlossaryInfo containing details about the glossary.
     * @throws DeepLException
     */
    public function getMultilingualGlossary($glossary): MultilingualGlossaryInfo
    {
        $glossaryId = MultilingualGlossaryInfo::getGlossaryId($glossary);
        $response = $this->client->sendRequestWithBackoff('GET', "/v3/glossaries/$glossaryId");
        $this->checkStatusCode($response, false, true);
        list(, $content) = $response;
        return MultilingualGlossaryInfo::parseJson($content);
    }

    /**
     * Gets information about all existing glossaries.
     * @return MultilingualGlossaryInfo[] Array of MultilingualGlossaryInfos containing details about all existing
     *  glossaries.
     * @throws DeepLException
     */
    public function listMultilingualGlossaries(): array
    {
        $response = $this->client->sendRequestWithBackoff('GET', '/v3/glossaries');
        $this->checkStatusCode($response, false, true);
        list(, $content) = $response;
        return MultilingualGlossaryInfo::parseListJson($content);
    }

    /**
     * Retrieves the dictionary entries for a given source and target language in the given glossary.
     * @param string|MultilingualGlossaryInfo $glossary Glossary ID or MultilingualGlossaryInfo of glossary to
     *  retrieve entries of.
     * @param string $sourceLang Language code of the glossary source terms.
     * @param string $targetLang Language code of the glossary target terms.
     * @return MultilingualGlossaryDictionaryEntries[] The entries stored in the dictionary.
     * @throws DeepLException
     */
    public function getMultilingualGlossaryEntries($glossary, string $sourceLang, string $targetLang): array
    {
        $glossaryId = MultilingualGlossaryInfo::getGlossaryId($glossary);
        $url = "/v3/glossaries/$glossaryId/entries?source_lang=$sourceLang&target_lang=$targetLang";
        $response = $this->client->sendRequestWithBackoff('GET', $url);
        $this->checkStatusCode($response, false, true);
        list(, $content) = $response;
        return MultilingualGlossaryDictionaryEntries::parseJsonList($content);
    }

    /**
     * Deletes the glossary with the given glossary ID or MultilingualGlossaryInfo.
     * @param string|MultilingualGlossaryInfo $glossary Glossary ID or MultilingualGlossaryInfo of glossary to
     *  be deleted.
     * @throws DeepLException
     */
    public function deleteMultilingualGlossary($glossary): void
    {
        $glossaryId = MultilingualGlossaryInfo::getGlossaryId($glossary);
        $response = $this->client->sendRequestWithBackoff('DELETE', "/v3/glossaries/$glossaryId");
        $this->checkStatusCode($response, false, true);
    }

    /**
     * Deletes specified glossary dictionary.
     * @param string|MultilingualGlossaryInfo $glossary Glossary ID or MultilingualGlossaryInfo of glossary to
     *  be deleted.
     * @param MultilingualGlossaryDictionaryInfo|null $dictionary The dictionary to delete. Either the
     * MultilingualGlossaryDictionaryInfo or both the source_lang and target_lang
     * can be provided to identify the dictionary.
     * @param string|null $sourceLang Optional parameter representing the source language of the dictionary
     * @param string|null $targetLang Optional parameter representing the target language of the dictionary.
     * @throws DeepLException
     */
    public function deleteMultilingualGlossaryDictionary(
        $glossary,
        ?MultilingualGlossaryDictionaryInfo $dictionary,
        ?string $sourceLang = null,
        ?string $targetLang = null
    ): void {
        $glossaryId = MultilingualGlossaryInfo::getGlossaryId($glossary);

        if (is_null($dictionary)) {
            if (is_null($sourceLang) or is_null($targetLang)) {
                throw new DeepLException('must provide dictionary or both source_lang and target_lang');
            }
        } else {
            $sourceLang = $dictionary->sourceLang;
            $targetLang = $dictionary->targetLang;
        }

        $url = "/v3/glossaries/$glossaryId/dictionaries?source_lang=$sourceLang&target_lang=$targetLang";
        $response = $this->client->sendRequestWithBackoff('DELETE', $url);
        $this->checkStatusCode($response, false, true);
    }

    /**
     * Validates and prepares HTTP parameters for rephrase requests.
     * @param string|string[] $texts Text(s) to rephrase
     * @param string|null $targetLang Target language code, or null to use default
     * @param string|null $style Writing style option, or null if not specified
     * @param string|null $tone Tone option, or null if not specified
     * @return array Associative array of HTTP parameters
     * @throws DeepLException
     */
    public function buildRephraseBodyParams(
        ?string $targetLang = null,
        ?string $style = null,
        ?string $tone = null
    ): array {
        if ($targetLang !== null) {
            $targetLang = LanguageCode::standardizeLanguageCode($targetLang);
            if ($targetLang === 'en') {
                throw new DeepLException('targetLang="en" is deprecated, please use "en-GB" or "en-US" instead.');
            } elseif ($targetLang === 'pt') {
                throw new DeepLException('targetLang="pt" is deprecated, please use "pt-PT" or "pt-BR" instead.');
            }
            $params['target_lang'] = $targetLang;
        }

        if ($style !== null) {
            $params['writing_style'] = $style;
        }

        if ($tone !== null) {
            $params['tone'] = $tone;
        }

        return $params;
    }

    /**
     * Retrieves a list of StyleRuleInfo for all available style rules.
     * @param int|null $page Page number for pagination, 0-indexed (optional).
     * @param int|null $pageSize Number of items per page (optional).
     * @param bool|null $detailed Whether to include detailed configuration rules (optional).
     * @return StyleRuleInfo[] List of StyleRuleInfo objects for all available style rules.
     * @throws DeepLException
     */
    public function getAllStyleRules(
        ?int $page = null,
        ?int $pageSize = null,
        ?bool $detailed = null
    ): array {
        $params = [];
        if ($page !== null) {
            $params['page'] = (string)$page;
        }
        if ($pageSize !== null) {
            $params['page_size'] = (string)$pageSize;
        }
        if ($detailed !== null) {
            $params['detailed'] = $detailed ? 'true' : 'false';
        }

        $queryString = '';
        if (!empty($params)) {
            $queryString = '?' . http_build_query($params);
        }

        $response = $this->client->sendRequestWithBackoff('GET', "/v3/style_rules$queryString");
        $this->checkStatusCode($response);
        list(, $content) = $response;

        return StyleRuleInfo::parseList($content);
    }
}
