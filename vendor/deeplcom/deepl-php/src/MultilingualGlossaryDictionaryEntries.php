<?php

// Copyright 2025 DeepL SE (https://www.deepl.com)
// Use of this source code is governed by an MIT
// license that can be found in the LICENSE file.

namespace DeepL;

use JsonException;

/**
 * Stores the entries of a dictionary, for a single source-target language pair.
 */
class MultilingualGlossaryDictionaryEntries
{
    /** @var string Language code of the source terms in the dictionary. */
    public $sourceLang;

    /** @var string Language code of the target terms in the dictionary. */
    public $targetLang;

    /** @var array Associative array storing the dictionary entries as source-target entry pairs. */
    public $entries;

    /**
     * @param string $sourceLang
     * @param string $targetLang
     * @param array $entries
     * @throws DeepLException
     */
    public function __construct(string $sourceLang, string $targetLang, array $entries)
    {
        GlossaryUtils::validateEntries($entries);
        $this->sourceLang = $sourceLang;
        $this->targetLang = $targetLang;
        $this->entries = $entries;
    }

    /**
     * @throws DeepLException
     */
    public static function parseJsonList(string $content): array
    {
        try {
            $object = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new InvalidContentException($exception);
        }

        return self::parseList($object['dictionaries']);
    }

    /**
     * @throws DeepLException
     */
    public static function parseList(array $list): array
    {
        $result = [];
        foreach ($list as $object) {
            $result[] = self::parseObject($object);
        }
        return $result;
    }

    /**
     * @throws DeepLException
     */
    public static function parseObject($object): MultilingualGlossaryDictionaryEntries
    {
        $entries_format = $object['entries_format'];
        if ($entries_format === "tsv") {
            $entries = GlossaryUtils::fromTsv($object['entries']);
        } else {
            throw new DeepLException("Unsupported entries_format: $entries_format");
        }

        return new MultilingualGlossaryDictionaryEntries($object['source_lang'], $object['target_lang'], $entries);
    }

    public function toObject(): array
    {
        return [
            "source_lang" => $this->sourceLang,
            "target_lang" => $this->targetLang,
            "entries" => $this->convertToTsv(),
            "entries_format" => "tsv",
        ];
    }

    public function convertToTsv(): string
    {
        return GlossaryUtils::convertToTsv($this->entries);
    }
}
