<?php
/*
 * Copyright 2024 Cloud Creativity Limited
 *
 * Use of this source code is governed by an MIT-style
 * license that can be found in the LICENSE file or at
 * https://opensource.org/licenses/MIT.
 */

declare(strict_types=1);

namespace LaravelJsonApi\Spec\Values;

use LaravelJsonApi\Contracts\Schema\Relation;
use LaravelJsonApi\Core\Document\ErrorList;
use LaravelJsonApi\Spec\Factory;
use LaravelJsonApi\Spec\Translator;
use LogicException;

class ToOne extends Value
{

    /**
     * @var Translator
     */
    private Translator $translator;

    /**
     * @var Factory
     */
    private Factory $factory;

    /**
     * @var Relation
     */
    private Relation $relation;

    /**
     * @var mixed
     */
    private $value;

    /**
     * @var Identifier|null
     */
    private ?Identifier $data = null;

    /**
     * ToOne constructor.
     *
     * @param Translator $translator
     * @param Factory $factory
     * @param Relation $relation
     * @param string $path
     * @param mixed $value
     */
    public function __construct(
        Translator $translator,
        Factory $factory,
        Relation $relation,
        string $path,
        $value
    ) {
        $this->translator = $translator;
        $this->factory = $factory;
        $this->relation = $relation;
        $this->path = rtrim($path, '/');
        $this->value = $value;
    }

    /**
     * @return Identifier|null
     */
    public function data(): ?Identifier
    {
        if ($this->data) {
            return $this->data;
        }

        if ($this->valid()) {
            return $this->data = !is_null($this->value->data) ? $this->factory->createIdentifierValue(
                "{$this->path}/data",
                $this->value->data,
                $this->relation,
            ) : null;
        }

        throw new LogicException('Invalid to-one relationship object.');
    }

    /**
     * @return ErrorList
     */
    public function allErrors(): ErrorList
    {
        $errors = $this->errors();
        $identifier = $this->valid() ? $this->data() : null;

        if ($identifier) {
            return $errors->merge($identifier->errors());
        }

        return $errors;
    }

    /**
     * @inheritDoc
     */
    protected function validate(): ErrorList
    {
        $errors = new ErrorList();

        if (!is_object($this->value)) {
            return $errors->push($this->translator->memberNotObject(
                $this->parent(),
                $this->member()
            ));
        }

        if (!property_exists($this->value, 'data')) {
            return $errors->push($this->translator->memberRequired(
                $this->path ?: '/',
                'data'
            ));
        }

        if (is_array($this->value->data)) {
            return $errors->push($this->translator->fieldExpectsToOne(
                $this->parent(),
                $this->member() ?: 'data',
                $this->relation->name()
            ));
        }

        return $errors;
    }

}
