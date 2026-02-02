<?php
/*
 * Copyright 2024 Cloud Creativity Limited
 *
 * Use of this source code is governed by an MIT-style
 * license that can be found in the LICENSE file or at
 * https://opensource.org/licenses/MIT.
 */

declare(strict_types=1);

namespace LaravelJsonApi\Spec\Validators;

use LaravelJsonApi\Contracts\Schema\Relation;
use LaravelJsonApi\Core\Document\ErrorList;
use LaravelJsonApi\Spec\Factory;
use LaravelJsonApi\Spec\ResourceDocument;
use LaravelJsonApi\Spec\Specification;
use LaravelJsonApi\Spec\Translator;

class RelationshipsValidator
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
     * @var Factory
     */
    private Factory $factory;

    /**
     * RelationshipsValidator constructor.
     *
     * @param Specification $spec
     * @param Translator $translator
     * @param Factory $factory
     */
    public function __construct(Specification $spec, Translator $translator, Factory $factory)
    {
        $this->spec = $spec;
        $this->translator = $translator;
        $this->factory = $factory;
    }

    /**
     * Validate the `/data/relationships` member.
     *
     * @param ResourceDocument $document
     * @param \Closure $next
     * @return ResourceDocument
     */
    public function validate(ResourceDocument $document, \Closure $next): ResourceDocument
    {
        $data = $document->data ?? null;

        if ($data && property_exists($data, 'relationships')) {
            $document->errors()->merge(
                $this->accept($document->type(), $data->relationships)
            );
        }

        return $next($document);
    }

    /**
     * @param string $resourceType
     * @param $relationships
     * @return ErrorList
     */
    private function accept(string $resourceType, $relationships): ErrorList
    {
        $errors = new ErrorList();

        if (!is_object($relationships)) {
            return $errors->push(
                $this->translator->memberNotObject('/data', 'relationships')
            );
        }

        $fields = collect($this->spec->fields($resourceType))
            ->whereInstanceOf(Relation::class)
            ->keyBy(fn($relation) => $relation->name());

        foreach ($relationships as $name => $value) {
            if ('type' === $name || 'id' === $name) {
                $errors->push($this->translator->memberFieldNotAllowed('/data', 'relationships', $name));
                continue;
            }

            if ($field = $fields->get($name)) {
                $errors->merge($this->acceptRelation($field, $value));
                continue;
            }

            $errors->push($this->translator->memberFieldNotSupported('/data', 'relationships', $name));
        }

        return $errors;
    }

    /**
     * @param Relation $relation
     * @param $value
     * @return ErrorList
     */
    private function acceptRelation(Relation $relation, $value): ErrorList
    {
        if ($relation->toMany()) {
            return $this->factory->createToManyValue(
                $relation,
                "/data/relationships/{$relation->name()}",
                $value
            )->allErrors();
        }

        return $this->factory->createToOneValue(
            $relation,
            "/data/relationships/{$relation->name()}",
            $value
        )->allErrors();
    }
}
