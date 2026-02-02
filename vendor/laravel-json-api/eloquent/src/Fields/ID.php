<?php
/*
 * Copyright 2024 Cloud Creativity Limited
 *
 * Use of this source code is governed by an MIT-style
 * license that can be found in the LICENSE file or at
 * https://opensource.org/licenses/MIT.
 */

declare(strict_types=1);

namespace LaravelJsonApi\Eloquent\Fields;

use Illuminate\Database\Eloquent\Model;
use LaravelJsonApi\Contracts\Schema\ID as IDContract;
use LaravelJsonApi\Core\Schema\Concerns\ClientIds;
use LaravelJsonApi\Core\Schema\Concerns\MatchesIds;
use LaravelJsonApi\Core\Schema\Concerns\Sortable;
use LaravelJsonApi\Eloquent\Contracts\Fillable;

class ID implements IDContract, Fillable
{

    use ClientIds;
    use MatchesIds;
    use Sortable;

    /**
     * @var string|null
     */
    private ?string $column;

    /**
     * Create an id field.
     *
     * @param string|null $column
     * @return static
     */
    public static function make(?string $column = null): self
    {
        return new static($column);
    }

    /**
     * IdField constructor.
     *
     * @param string|null $column
     */
    public function __construct(?string $column = null)
    {
        $this->column = $column ?: null;
        $this->sortable();
    }

    /**
     * @inheritDoc
     */
    public function name(): string
    {
        return 'id';
    }

    /**
     * @return string|null
     */
    public function column(): ?string
    {
        return $this->column;
    }

    /**
     * @inheritDoc
     */
    public function key(): ?string
    {
        return $this->column();
    }

    /**
     * @inheritDoc
     */
    public function isSparseField(): bool
    {
        return false;
    }

    /**
     * @inheritDoc
     */
    public function isReadOnly($request): bool
    {
        if ($this->acceptsClientIds()) {
            return !$request->isMethod('POST');
        }

        return true;
    }

    /**
     * @inheritDoc
     */
    public function isNotReadOnly($request): bool
    {
        return !$this->isReadOnly($request);
    }

    /**
     * @inheritDoc
     */
    public function mustExist(): bool
    {
        return false;
    }

    /**
     * @inheritDoc
     */
    public function fill(Model $model, $value, array $validatedData): void
    {
        $column = $this->column() ?: $model->getRouteKeyName();

        $model->{$column} = $value;
    }

}
