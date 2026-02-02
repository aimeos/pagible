<?php
/*
 * Copyright 2024 Cloud Creativity Limited
 *
 * Use of this source code is governed by an MIT-style
 * license that can be found in the LICENSE file or at
 * https://opensource.org/licenses/MIT.
 */

declare(strict_types=1);

namespace LaravelJsonApi\Core\Document\Concerns;

use function json_encode;

trait Serializable
{

    /**
     * @inheritDoc
     */
    public function toJson($options = 0)
    {
        return json_encode($this, $options | JSON_THROW_ON_ERROR);
    }

    /**
     * @return string
     */
    public function toString(): string
    {
        return $this->toJson(JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }
}
