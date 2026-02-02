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

use LaravelJsonApi\Contracts\Schema\Attribute;
use LaravelJsonApi\Core\Document\ErrorList;
use LaravelJsonApi\Spec\ResourceDocument;
use LaravelJsonApi\Spec\Specification;
use LaravelJsonApi\Spec\Translator;

class AttributesValidator
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
     * AttributesValidator constructor.
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
     * Validate the `/data/attributes` member.
     *
     * @param ResourceDocument $document
     * @param \Closure $next
     * @return ResourceDocument
     */
    public function validate(ResourceDocument $document, \Closure $next): ResourceDocument
    {
        $data = $document->data ?? null;

        if ($data && property_exists($data, 'attributes')) {
            $document->errors()->merge(
                $this->accept($document->type(), $data->attributes)
            );
        }

        return $next($document);
    }

    /**
     * @param string $resourceType
     * @param $attributes
     * @return ErrorList
     */
    private function accept(string $resourceType, $attributes): ErrorList
    {
        $errors = new ErrorList();

        if (!is_object($attributes)) {
            return $errors->push(
                $this->translator->memberNotObject('/data', 'attributes')
            );
        }

        /** Type and id are not allowed in attributes */
        $errors->push(...collect(['type', 'id'])->filter(fn($name) => property_exists($attributes, $name))->map(
            fn($name) => $this->translator->memberFieldNotAllowed('/data', 'attributes', $name)
        ));

        $fields = collect($this->spec->fields($resourceType))
            ->whereInstanceOf(Attribute::class)
            ->map(fn($field) => $field->name())
            ->values();

        $actual = collect(get_object_vars($attributes))
            ->forget(['type', 'id'])
            ->keys();

        return $errors->push(...$actual->diff($fields)->map(
            fn($name) => $this->translator->memberFieldNotSupported('/data', 'attributes', $name)
        ));
    }
}
