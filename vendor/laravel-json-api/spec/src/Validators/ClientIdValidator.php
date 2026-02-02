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

class ClientIdValidator
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
        if ($document->expectsId()) {
            return $next($document);
        }

        $data = $document->data;

        if (!property_exists($data, 'id')) {
            return $next($document);
        }

        if (false === $this->spec->clientIds($document->type())) {
            $document->errors()->push(
                $this->translator->resourceDoesNotSupportClientIds($document->type())
            );
        }

        if ($error = $this->accept($document->type(), $data->id)) {
            $document->errors()->push($error);
        }

        return $next($document);
    }

    /**
     * @param string $type
     * @param $value
     * @return Error|null
     */
    private function accept(string $type, $value): ?Error
    {
        if (!is_string($value)) {
            return $this->translator->memberNotString('/data', 'id');
        }

        if (Identifier::idIsEmpty($value)) {
            return $this->translator->memberEmpty('/data', 'id');
        }

        if ($this->spec->exists($type, $value)) {
            return $this->translator->resourceExists($type, $value, '/data/id');
        }

        return null;
    }
}
