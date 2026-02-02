<?php

// Copyright 2022 DeepL SE (https://www.deepl.com)
// Use of this source code is governed by an MIT
// license that can be found in the LICENSE file.

namespace DeepL;

use JsonException;

/**
 * Status of a document translation request.
 */
class DocumentStatus
{
    /**
     * @var string The status code indicating the document translation status, one of:
     * * 'queued': Document translation has not yet started, but will begin soon.
     * * 'translating': Document translation is in progress.
     * * 'done': Document translation completed successfully, and the translated document may be downloaded.
     * * 'error': An error occurred during document translation.
     */
    public $status;

    /**
     * @var integer|null Estimated time until document translation completes in seconds, otherwise null if unknown.
     */
    public $secondsRemaining;

    /**
     * @var integer|null Number of characters billed for this document, or null if unknown or before translation is
     * complete.
     */
    public $billedCharacters;

    /**
     * @var string|null A short description of the error, or null if no error has occurred.
     */
    public $errorMessage;

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

        $this->status = $json['status'];
        $this->secondsRemaining = $json['seconds_remaining'] ?? null;
        $this->billedCharacters = $json['billed_characters'] ?? null;
        $this->errorMessage = $json['error_message'] ?? null;
    }

    /**
     * @return bool True if the document translation completed successfully, otherwise false.
     */
    public function done(): bool
    {
        return $this->status === 'done';
    }

    /**
     * @return bool True if no error has occurred, otherwise false.
     * Note that while the document translation is in progress, this returns true.
     */
    public function ok(): bool
    {
        return $this->status !== 'error';
    }
}
