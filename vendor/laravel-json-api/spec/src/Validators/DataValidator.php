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

class DataValidator
{

    /**
     * @var Translator
     */
    private Translator $translator;

    /**
     * DataMember constructor.
     *
     * @param Translator $translator
     */
    public function __construct(Translator $translator)
    {
        $this->translator = $translator;
    }

    /**
     * Validate the `/data` member of the document.
     *
     * @param ResourceDocument $document
     * @param \Closure $next
     * @return ResourceDocument
     */
    public function validate(ResourceDocument $document, \Closure $next): ResourceDocument
    {
        if (!isset($document->data)) {
            $document->errors()->push(
                $this->translator->memberRequired('/', 'data')
            );
            return $document;
        }

        $data = $document->data;

        if (is_object($data)) {
            return $next($document);
        }

        $document->errors()->push(
            $this->translator->memberNotObject('/', 'data')
        );

        return $document;
    }
}
