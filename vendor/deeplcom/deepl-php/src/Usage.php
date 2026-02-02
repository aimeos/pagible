<?php

// Copyright 2022 DeepL SE (https://www.deepl.com)
// Use of this source code is governed by an MIT
// license that can be found in the LICENSE file.

namespace DeepL;

use JsonException;

/**
 * Information about the API usage: how much has been translated in this billing period, and the
 * maximum allowable amount.
 *
 * Depending on the account type, different usage types are included: the character, document and
 * teamDocument fields provide details about each corresponding usage type, allowing each usage type
 * to be checked individually. The anyLimitReached() function checks if any usage type is exceeded.
 */
class Usage
{
    /**
     * @var UsageDetail|null Usage details for characters, for example due to the translateText() function.
     */
    public $character;

    /**
     * @var UsageDetail|null Usage details for documents.
     */
    public $document;

    /**
     * @var UsageDetail|null Usage details for documents shared among your team.
     */
    public $teamDocument;

    /**
     * @return bool True if any usage type limit has been reached or passed, otherwise false.
     */
    public function anyLimitReached(): bool
    {
        return ($this->character !== null && $this->character->limitReached()) ||
            ($this->document !== null && $this->document->limitReached()) ||
            ($this->teamDocument !== null && $this->teamDocument->limitReached());
    }

    public function __toString(): string
    {
        $list = [
            'Characters' => $this->character,
            'Documents' => $this->document,
            'Team documents' => $this->teamDocument,
        ];
        $result = 'Usage this billing period:';
        foreach ($list as $label => $detail) {
            if ($detail !== null) {
                $result .= "\n$label: $detail->count of $detail->limit";
            }
        }
        return $result;
    }

    /**
     * @throws InvalidContentException
     */
    public function __construct(string $content)
    {
        try {
            $json = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new InvalidContentException($exception);
        }

        $this->character = $this->buildUsageDetail('character', $json);
        $this->document = $this->buildUsageDetail('document', $json);
        $this->teamDocument = $this->buildUsageDetail('team_document', $json);
    }

    private function buildUsageDetail(string $prefix, array $json): ?UsageDetail
    {
        $count = "{$prefix}_count";
        $limit = "{$prefix}_limit";
        if (array_key_exists($count, $json) && array_key_exists($limit, $json)) {
            return new UsageDetail($json[$count], $json[$limit]);
        }
        return null;
    }
}
