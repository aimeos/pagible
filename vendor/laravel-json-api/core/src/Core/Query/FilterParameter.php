<?php
/*
 * Copyright 2024 Cloud Creativity Limited
 *
 * Use of this source code is governed by an MIT-style
 * license that can be found in the LICENSE file or at
 * https://opensource.org/licenses/MIT.
 */

declare(strict_types=1);

namespace LaravelJsonApi\Core\Query;

use InvalidArgumentException;
use LaravelJsonApi\Contracts\Schema\Schema;

class FilterParameter
{

    /**
     * @var string
     */
    private string $key;

    /**
     * @var mixed
     */
    private $value;

    /**
     * FilterParameter constructor.
     *
     * @param string $key
     * @param $value
     */
    public function __construct(string $key, $value)
    {
        if (empty($key)) {
            throw new InvalidArgumentException('Expecting a non-empty string for the filter key.');
        }

        $this->key = $key;
        $this->value = $value;
    }

    /**
     * @return string
     */
    public function key(): string
    {
        return $this->key;
    }

    /**
     * @return mixed
     */
    public function value()
    {
        return $this->value;
    }

    /**
     * @param Schema $schema
     * @return bool
     */
    public function existsOnSchema(Schema $schema): bool
    {
        return $schema->isFilter($this->key());
    }
}
