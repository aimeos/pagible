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

use LaravelJsonApi\Core\Document\Error;

class QueryErrorIterator extends ErrorIterator
{

    /**
     * @inheritDoc
     */
    protected function createError(string $key, string $message, array $failed): Error
    {
        return $this->translator->invalidQueryParameter(
            $key,
            $message,
            $failed
        );
    }

}
