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

use LaravelJsonApi\Spec\ResourceDocument;
use LaravelJsonApi\Spec\Translator;

class FieldsValidator
{

    private Translator $translator;

    /**
     * FieldsValidator constructor.
     *
     * @param Translator $translator
     */
    public function __construct(Translator $translator)
    {
        $this->translator = $translator;
    }

    /**
     * Validate a resource's fields (attributes + relationships).
     *
     * @param ResourceDocument $document
     * @param \Closure $next
     * @return ResourceDocument
     */
    public function validate(ResourceDocument $document, \Closure $next): ResourceDocument
    {
        $duplicates = collect((array) $document->get('data.attributes', []))
            ->intersectByKeys((array) $document->get('data.relationships', []))
            ->keys()
            ->map(fn($field) => $this->translator->resourceFieldExistsInAttributesAndRelationships($field));

        $document->errors()->push(...$duplicates);

        return $next($document);
    }
}
