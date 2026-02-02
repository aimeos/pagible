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

use LaravelJsonApi\Core\Document\Error;
use LaravelJsonApi\Spec\ResourceDocument;
use LaravelJsonApi\Spec\Specification;
use LaravelJsonApi\Spec\Translator;
use LaravelJsonApi\Spec\Values\Identifier;

class IdValidator
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
     * ClientIdValidator constructor.
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
     * Validate the `/data/id` member of the document.
     *
     * @param ResourceDocument $document
     * @param \Closure $next
     * @return ResourceDocument
     */
    public function validate(ResourceDocument $document, \Closure $next): ResourceDocument
    {
        if (!$expected = $document->id()) {
            return $next($document);
        }

        $data = $document->data;

        if (!property_exists($data, 'id')) {
            $document->errors()->push(
                $this->translator->memberRequired('/data', 'id')
            );
        } else if ($error = $this->accept($expected, $data->id)) {
            $document->errors()->push($error);
        }

        return $next($document);
    }

    /**
     * @param string $expected
     * @param $value
     * @return Error|null
     */
    private function accept(string $expected, $value): ?Error
    {
        if (!is_string($value)) {
            return $this->translator->memberNotString('/data', 'id');
        }

        if (Identifier::idIsEmpty($value)) {
            return $this->translator->memberEmpty('/data', 'id');
        }

        if ($expected !== $value) {
            return $this->translator->resourceIdNotSupported($value);
        }

        return null;
    }
}
