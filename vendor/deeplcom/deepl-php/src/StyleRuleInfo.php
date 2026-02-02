<?php

// Copyright 2025 DeepL SE (https://www.deepl.com)
// Use of this source code is governed by an MIT
// license that can be found in the LICENSE file.

namespace DeepL;

use DateTime;
use JsonException;

/**
 * Information about a style rule list.
 */
class StyleRuleInfo
{
    /** @var string Unique ID assigned to the style rule list. */
    public $styleId;

    /** @var string User-defined name assigned to the style rule list. */
    public $name;

    /** @var DateTime Timestamp when the style rule list was created. */
    public $creationTime;

    /** @var DateTime Timestamp when the style rule list was last updated. */
    public $updatedTime;

    /** @var string Language code for the style rule list. */
    public $language;

    /** @var int Version number of the style rule list. */
    public $version;

    /** @var ConfiguredRules|null The predefined rules that have been enabled. */
    public $configuredRules;

    /** @var CustomInstruction[]|null Optional list of custom instructions. */
    public $customInstructions;

    public function __construct(
        string $styleId,
        string $name,
        DateTime $creationTime,
        DateTime $updatedTime,
        string $language,
        int $version,
        ?ConfiguredRules $configuredRules = null,
        ?array $customInstructions = null
    ) {
        $this->styleId = $styleId;
        $this->name = $name;
        $this->creationTime = $creationTime;
        $this->updatedTime = $updatedTime;
        $this->language = $language;
        $this->version = $version;
        $this->configuredRules = $configuredRules;
        $this->customInstructions = $customInstructions;
    }

    /**
     * @param string|StyleRuleInfo $styleRule Style rule ID or StyleRuleInfo of style rule.
     */
    public static function getStyleId($styleRule): string
    {
        return is_string($styleRule) ? $styleRule : $styleRule->styleId;
    }

    /**
     * @throws InvalidContentException
     */
    public static function fromJson(array $json): StyleRuleInfo
    {
        $configuredRules = null;
        if (isset($json['configured_rules'])) {
            $configuredRules = ConfiguredRules::fromJson($json['configured_rules']);
        }

        $customInstructions = null;
        if (isset($json['custom_instructions']) && is_array($json['custom_instructions'])) {
            $customInstructions = [];
            foreach ($json['custom_instructions'] as $instruction) {
                $customInstructions[] = CustomInstruction::fromJson($instruction);
            }
        }

        return new StyleRuleInfo(
            $json['style_id'],
            $json['name'],
            new DateTime($json['creation_time']),
            new DateTime($json['updated_time']),
            $json['language'],
            $json['version'],
            $configuredRules,
            $customInstructions
        );
    }

    /**
     * @throws InvalidContentException
     */
    public static function parseList(string $content): array
    {
        try {
            $decoded = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new InvalidContentException($exception);
        }

        $result = [];
        $styleRules = $decoded['style_rules'] ?? [];
        foreach ($styleRules as $object) {
            $result[] = self::fromJson($object);
        }
        return $result;
    }

    public function __toString(): string
    {
        return "StyleRule \"{$this->name}\" ({$this->styleId})";
    }
}
