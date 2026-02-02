<?php
/*
 * Copyright 2024 Cloud Creativity Limited
 *
 * Use of this source code is governed by an MIT-style
 * license that can be found in the LICENSE file or at
 * https://opensource.org/licenses/MIT.
 */

declare(strict_types=1);

namespace LaravelJsonApi\Encoder\Neomerx;

use LaravelJsonApi\Encoder\Neomerx\Encoder\Encoder as ExtendedEncoder;

class CompoundDocument extends Document
{

    /**
     * @var mixed
     */
    private $data;

    /**
     * CompoundDocument constructor.
     *
     * @param ExtendedEncoder $encoder
     * @param Mapper $mapper
     * @param mixed $data
     */
    public function __construct(ExtendedEncoder $encoder, Mapper $mapper, $data)
    {
        parent::__construct($encoder, $mapper);
        $this->data = $data;
    }

    /**
     * @inheritDoc
     */
    protected function serialize(): array
    {
        return $this->encoder()->serializeData(
            $this->data
        );
    }

    /**
     * @inheritDoc
     */
    protected function encode(): string
    {
        return $this->encoder()->encodeData(
            $this->data
        );
    }

}
