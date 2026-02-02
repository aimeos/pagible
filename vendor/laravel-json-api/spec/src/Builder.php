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

use Illuminate\Pipeline\Pipeline;
use Illuminate\Support\Arr;
use LaravelJsonApi\Core\Exceptions\JsonApiException;
use function array_merge;

abstract class Builder
{

    /**
     * @var JsonDecoder
     */
    private JsonDecoder $decoder;

    /**
     * @var Pipeline
     */
    private Pipeline $pipeline;

    /**
     * @var array
     */
    private array $pipes = [];

    /**
     * @param object $json
     * @return Document
     */
    abstract protected function create(object $json): Document;

    /**
     * @return array
     */
    abstract protected function pipes(): array;

    /**
     * Builder constructor.
     *
     * @param JsonDecoder $decoder
     * @param Pipeline $pipeline
     */
    public function __construct(JsonDecoder $decoder, Pipeline $pipeline)
    {
        $this->decoder = $decoder;
        $this->pipeline = $pipeline;
    }

    /**
     * @param $pipes
     * @return $this
     */
    public function using($pipes): self
    {
        $this->pipes = array_merge($this->pipes, Arr::wrap($pipes));

        return $this;
    }

    /**
     * @param string $json
     * @return Document
     * @throws JsonApiException
     */
    public function build(string $json): Document
    {
        $document = $this->create(
            $this->decoder->decode($json)
        );

        $pipes = array_merge($this->pipes(), $this->pipes);

        return $this->pipeline
            ->send($document)
            ->through($pipes)
            ->via('validate')
            ->thenReturn();
    }
}
