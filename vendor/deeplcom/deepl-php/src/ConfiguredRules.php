<?php

// Copyright 2025 DeepL SE (https://www.deepl.com)
// Use of this source code is governed by an MIT
// license that can be found in the LICENSE file.

namespace DeepL;

/**
 * Configuration rules for a style rule list.
 */
class ConfiguredRules
{
    /** @var array<string, string>|null Date and time formatting rules. */
    public $datesAndTimes;

    /** @var array<string, string>|null Text formatting rules. */
    public $formatting;

    /** @var array<string, string>|null Number formatting rules. */
    public $numbers;

    /** @var array<string, string>|null Punctuation rules. */
    public $punctuation;

    /** @var array<string, string>|null Spelling and grammar rules. */
    public $spellingAndGrammar;

    /** @var array<string, string>|null Style and tone rules. */
    public $styleAndTone;

    /** @var array<string, string>|null Vocabulary rules. */
    public $vocabulary;

    public function __construct(
        ?array $datesAndTimes = null,
        ?array $formatting = null,
        ?array $numbers = null,
        ?array $punctuation = null,
        ?array $spellingAndGrammar = null,
        ?array $styleAndTone = null,
        ?array $vocabulary = null
    ) {
        $this->datesAndTimes = $datesAndTimes;
        $this->formatting = $formatting;
        $this->numbers = $numbers;
        $this->punctuation = $punctuation;
        $this->spellingAndGrammar = $spellingAndGrammar;
        $this->styleAndTone = $styleAndTone;
        $this->vocabulary = $vocabulary;
    }

    /**
     * @throws InvalidContentException
     */
    public static function fromJson(?array $json): ?ConfiguredRules
    {
        if ($json === null) {
            return null;
        }

        return new ConfiguredRules(
            $json['dates_and_times'] ?? null,
            $json['formatting'] ?? null,
            $json['numbers'] ?? null,
            $json['punctuation'] ?? null,
            $json['spelling_and_grammar'] ?? null,
            $json['style_and_tone'] ?? null,
            $json['vocabulary'] ?? null
        );
    }
}
