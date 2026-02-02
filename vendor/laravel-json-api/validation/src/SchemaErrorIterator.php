<?php
/*
 * Copyright 2024 Cloud Creativity Limited
 *
 * Use of this source code is governed by an MIT-style
 * license that can be found in the LICENSE file or at
 * https://opensource.org/licenses/MIT.
 */

declare(strict_types=1);

namespace LaravelJsonApi\Validation;

use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use LaravelJsonApi\Contracts\Schema\Schema;
use LaravelJsonApi\Core\Document\Error;

class SchemaErrorIterator extends ErrorIterator
{

    /**
     * @var Schema
     */
    private Schema $schema;

    /**
     * SchemaErrorIterator constructor.
     *
     * @param Translator $translator
     * @param Schema $schema
     * @param ValidatorContract $validator
     */
    public function __construct(Translator $translator, Schema $schema, ValidatorContract $validator)
    {
        parent::__construct($translator, $validator);
        $this->schema = $schema;
    }

    /**
     * @param string $key
     * @param string $message
     * @param array $failed
     * @return Error
     */
    protected function createError(string $key, string $message, array $failed): Error
    {
        return $this->translator->invalidResource(
            SchemaSourcePointer::make($this->schema, $key)->withPrefix('/data')->toString(),
            $message,
            $failed
        );
    }

}
