<?php

// Copyright 2025 DeepL SE (https://www.deepl.com)
// Use of this source code is governed by an MIT
// license that can be found in the LICENSE file.

namespace DeepL;

use DateTime;
use JsonException;

/**
 * Information about a multilingual glossary, excluding the entry list.
 */
class MultilingualGlossaryInfo
{
    /** @var string ID of the associated glossary. */
    public $glossaryId;

    /** @var string Name of the glossary chosen during creation. */
    public $name;

    /** @var DateTime DateTime when the glossary was created. */
    public $creationTime;

    /** @var MultilingualGlossaryDictionaryInfo[] Dictionaries contained in this glossary. Each
     * dictionary contains its language pair and the number of entries. */
    public $dictionaries;

    public function __construct(
        string $glossaryId,
        string $name,
        DateTime $creationTime,
        array $dictionaries
    ) {
        $this->glossaryId = $glossaryId;
        $this->name = $name;
        $this->creationTime = $creationTime;
        $this->dictionaries = $dictionaries;
    }

    /**
     * @param string|MultilingualGlossaryInfo $glossary Glossary ID or MultilingualGlossaryInfo of glossary.
     */
    public static function getGlossaryId($glossary): string
    {
        return is_string($glossary) ? $glossary : $glossary->glossaryId;
    }

    /**
     * @throws InvalidContentException
     */
    public static function parseJson(string $content): MultilingualGlossaryInfo
    {
        try {
            $object = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new InvalidContentException($exception);
        }

        return self::parseObject($object);
    }

    /**
     * @throws InvalidContentException
     */
    public static function parseListJson(string $content): array
    {
        try {
            $decoded = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new InvalidContentException($exception);
        }

        $result = [];
        foreach ($decoded['glossaries'] as $object) {
            $result[] = self::parseObject($object);
        }
        return $result;
    }

    /**
     * @throws \Exception
     */
    private static function parseObject($object): MultilingualGlossaryInfo
    {
        return new MultilingualGlossaryInfo(
            $object['glossary_id'],
            $object['name'] ?? null,
            new DateTime($object['creation_time']) ?? null,
            MultilingualGlossaryDictionaryInfo::parseList($object['dictionaries'])
        );
    }
}
