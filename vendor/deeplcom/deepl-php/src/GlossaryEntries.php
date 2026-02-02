<?php

// Copyright 2022 DeepL SE (https://www.deepl.com)
// Use of this source code is governed by an MIT
// license that can be found in the LICENSE file.

namespace DeepL;

/**
 * Stores the entries of a glossary.
 */
class GlossaryEntries
{
    /** @var array Associative array storing the glossary entries as source-target entry pairs. */
    private $implEntries;

    public function getEntries(): array
    {
        return $this->implEntries;
    }

    /**
     * @throws DeepLException
     */
    public static function fromTsv(string $tsv): GlossaryEntries
    {
        return new GlossaryEntries(GlossaryUtils::fromTsv($tsv));
    }

    /**
     * @throws DeepLException
     */
    public static function fromEntries(array $entries): GlossaryEntries
    {
        GlossaryUtils::validateEntries($entries);
        return new GlossaryEntries($entries);
    }

    public function convertToTsv(): string
    {
        return GlossaryUtils::convertToTsv($this->implEntries);
    }

    /**
     * @param array $entries
     */
    private function __construct(array $entries)
    {
        $this->implEntries = $entries;
    }
}
