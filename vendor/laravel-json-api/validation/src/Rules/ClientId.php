<?php
/*
 * Copyright 2024 Cloud Creativity Limited
 *
 * Use of this source code is governed by an MIT-style
 * license that can be found in the LICENSE file or at
 * https://opensource.org/licenses/MIT.
 */

declare(strict_types=1);

namespace LaravelJsonApi\Validation\Rules;

use Illuminate\Contracts\Validation\Rule;
use LaravelJsonApi\Contracts\Schema\Schema;
use LaravelJsonApi\Validation\JsonApiValidation;
use function is_string;

class ClientId implements Rule
{

    /**
     * @var Schema
     */
    private Schema $schema;

    /**
     * ClientId constructor.
     *
     * @param Schema $schema
     */
    public function __construct(Schema $schema)
    {
        $this->schema = $schema;
    }

    /**
     * @inheritDoc
     */
    public function passes($attribute, $value)
    {
        if (is_string($value)) {
            return $this->schema->id()->match($value);
        }

        return false;
    }

    /**
     * @inheritDoc
     */
    public function message()
    {
        return trans(JsonApiValidation::translationKeyForRule($this));
    }

}
