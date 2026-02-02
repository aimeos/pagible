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

use LaravelJsonApi\Contracts\Schema\Relation;
use LaravelJsonApi\Spec\Values\Identifier;
use LaravelJsonApi\Spec\Values\ToMany;
use LaravelJsonApi\Spec\Values\ToOne;

class Factory
{

    /**
     * @var Specification
     */
    private Specification $spec;

    /**
     * @var Translator
     */
    private Translator $translator;

    /**
     * Factory constructor.
     *
     * @param Specification $spec
     * @param Translator $translator
     */
    public function __construct(Specification $spec, Translator $translator)
    {
        $this->spec = $spec;
        $this->translator = $translator;
    }


    /**
     * Create a resource identifier object.
     *
     * @param string $path
     * @param $value
     * @param Relation|null $relation
     *      the relation the identifier exists in.
     * @return Identifier
     */
    public function createIdentifierValue(string $path, $value, ?Relation $relation = null): Identifier
    {
        return new Identifier($this->spec, $this->translator, $path, $value, $relation);
    }

    /**
     * Create a to-one relationship object.
     *
     * @param Relation $relation
     * @param string $path
     * @param $value
     * @return ToOne
     */
    public function createToOneValue(Relation $relation, string $path, $value): ToOne
    {
        return new ToOne($this->translator, $this, $relation, $path, $value);
    }

    /**
     * Create a to-many relationship object.
     *
     * @param Relation $relation
     * @param string $path
     * @param $value
     * @return ToMany
     */
    public function createToManyValue(Relation $relation, string $path, $value): ToMany
    {
        return new ToMany($this->translator, $this, $relation, $path, $value);
    }
}
