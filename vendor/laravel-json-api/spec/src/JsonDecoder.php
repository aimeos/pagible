<?php
/*
 * Copyright 2024 Cloud Creativity Limited
 *
 * Use of this source code is governed by an MIT-style
 * license that can be found in the LICENSE file or at
 * https://opensource.org/licenses/MIT.
 */

declare(strict_types=1);

namespace LaravelJsonApi\Spec;

use JsonException;
use LaravelJsonApi\Core\Exceptions\JsonApiException;

class JsonDecoder
{

    /**
     * @var Translator
     */
    private Translator $translator;

    /**
     * JsonDecoder constructor.
     *
     * @param Translator $translator
     */
    public function __construct(Translator $translator)
    {
        $this->translator = $translator;
    }

    /**
     * Decode a JSON string to a PHP object.
     *
     * @param string $json
     * @return object
     * @throws JsonApiException
     */
    public function decode(string $json): object
    {
        if (empty(trim($json))) {
            throw JsonApiException::make($this->translator->jsonEmpty());
        }

        try {
            if (is_string($json)) {
                $json = json_decode($json, false, 512, JSON_THROW_ON_ERROR);
            }
        } catch (JsonException $ex) {
            throw JsonApiException::make(
                $this->translator->jsonError($ex),
                $ex,
            );
        }

        if (is_object($json)) {
            return $json;
        }

        throw JsonApiException::make($this->translator->jsonNotObject());
    }
}
