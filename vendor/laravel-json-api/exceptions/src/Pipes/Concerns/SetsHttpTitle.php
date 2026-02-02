<?php
/*
 * Copyright 2024 Cloud Creativity Limited
 *
 * Use of this source code is governed by an MIT-style
 * license that can be found in the LICENSE file or at
 * https://opensource.org/licenses/MIT.
 */

declare(strict_types=1);

namespace LaravelJsonApi\Exceptions\Pipes\Concerns;

use Illuminate\Contracts\Translation\Translator;
use Illuminate\Http\Response;

trait SetsHttpTitle
{
    /**
     * @var Translator|null
     */
    private ?Translator $translator = null;

    /**
     * @param int|null $status
     * @return string|null
     */
    private function getTitle(?int $status): ?string
    {
        if ($status && isset(Response::$statusTexts[$status])) {
            $title = Response::$statusTexts[$status];
            return $this->translator?->get($title) ?? $title;
        }

        return null;
    }
}
