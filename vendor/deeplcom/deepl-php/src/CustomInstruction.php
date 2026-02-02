<?php

// Copyright 2025 DeepL SE (https://www.deepl.com)
// Use of this source code is governed by an MIT
// license that can be found in the LICENSE file.

namespace DeepL;

/**
 * Custom instruction for a style rule.
 */
class CustomInstruction
{
    /** @var string Label for the custom instruction. */
    public $label;

    /** @var string Prompt text for the custom instruction. */
    public $prompt;

    /** @var string|null Optional source language code for the custom instruction. */
    public $sourceLanguage;

    public function __construct(
        string $label,
        string $prompt,
        ?string $sourceLanguage = null
    ) {
        $this->label = $label;
        $this->prompt = $prompt;
        $this->sourceLanguage = $sourceLanguage;
    }

    /**
     * @throws InvalidContentException
     */
    public static function fromJson(array $json): CustomInstruction
    {
        return new CustomInstruction(
            $json['label'],
            $json['prompt'],
            $json['source_language'] ?? null
        );
    }
}
