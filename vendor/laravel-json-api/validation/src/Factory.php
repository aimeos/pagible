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

use Illuminate\Contracts\Validation\Validator;
use LaravelJsonApi\Contracts\Schema\Schema;

class Factory
{

    /**
     * @var Translator
     */
    private Translator $translator;

    /**
     * Factory constructor.
     *
     * @param Translator $translator
     */
    public function __construct(Translator $translator)
    {
        $this->translator = $translator;
    }

    /**
     * Create JSON API errors for a Laravel validator.
     *
     * @param Validator $validator
     * @return ValidatorErrorIterator
     */
    public function createErrors(Validator $validator): ValidatorErrorIterator
    {
        return new ValidatorErrorIterator(
            $this->translator,
            $validator
        );
    }

    /**
     * Create JSON API errors for a resource validator.
     *
     * @param Schema $schema
     * @param Validator $validator
     * @return ErrorIterator
     */
    public function createErrorsForResource(Schema $schema, Validator $validator): ErrorIterator
    {
        return new SchemaErrorIterator(
            $this->translator,
            $schema,
            $validator
        );
    }

    /**
     * Create JSON API errors for a query parameter validator.
     *
     * @param Validator $validator
     * @return ErrorIterator
     */
    public function createErrorsForQuery(Validator $validator): ErrorIterator
    {
        return new QueryErrorIterator($this->translator, $validator);
    }

    /**
     * Create JSON API errors for a delete resource validator.
     *
     * @param Validator $validator
     * @return ErrorIterator
     */
    public function createErrorsForDeleteResource(Validator $validator): ErrorIterator
    {
        return new DeleteErrorIterator($this->translator, $validator);
    }
}
