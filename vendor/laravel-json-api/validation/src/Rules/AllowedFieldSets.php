<?php
/*
 * Copyright 2024 Cloud Creativity Limited
 *
 * Use of this source code is governed by an MIT-style
 * license that can be found in the LICENSE file or at
 * https://opensource.org/licenses/MIT.
 */

declare(strict_types=1);

namespace LaravelJsonApi\Validation\Rules;

use Illuminate\Contracts\Validation\Rule;
use Illuminate\Support\Collection;
use LaravelJsonApi\Contracts\Schema\Container as SchemaContainer;
use LaravelJsonApi\Core\Support\Arr;
use LaravelJsonApi\Validation\JsonApiValidation;

class AllowedFieldSets implements Rule
{
    /**
     * @var Collection
     */
    private Collection $allowed;

    /**
     * @var SchemaContainer|null
     */
    private ?SchemaContainer $schemas = null;

    /**
     * The last value that was validated.
     *
     * @var array|null
     */
    private ?array $value = null;

    /**
     * Create an allowed field set rule for the supplied schemas.
     *
     * @param SchemaContainer $schemas
     * @return AllowedFieldSets
     */
    public static function make(SchemaContainer $schemas): self
    {
        $rule = new self();
        $rule->schemas = $schemas;

        return $rule;
    }

    /**
     * AllowedFieldSets constructor.
     *
     * @param iterable|null $allowed
     */
    public function __construct(iterable $allowed = [])
    {
        $this->allowed = Collection::make($allowed);
    }

    /**
     * Allow fields for a resource type.
     *
     * @param string $resourceType
     * @param string[]|null $fields
     *      the allowed fields, empty array for none allowed, or null for all allowed.
     * @return $this
     */
    public function allow(string $resourceType, ?array $fields = null): self
    {
        $this->allowed[$resourceType] = $fields;

        return $this;
    }

    /**
     * Allow any fields for the specified resource type.
     *
     * @param string ...$resourceTypes
     * @return $this
     */
    public function any(string ...$resourceTypes): self
    {
        foreach ($resourceTypes as $resourceType) {
            $this->allow($resourceType, null);
        }

        return $this;
    }

    /**
     * Allow no fields for the specified resource type.
     *
     * @param string ...$resourceTypes
     * @return $this
     */
    public function none(string ...$resourceTypes): self
    {
        foreach ($resourceTypes as $resourceType) {
            $this->allow($resourceType, []);
        }

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function passes($attribute, $value): bool
    {
        $value = is_array($value) ? $value : [];
        $this->value = $value;

        return Collection::make($value)->every(function ($value, $key) {
            return $this->allowed($key, $value);
        });
    }

    /**
     * @inheritDoc
     */
    public function message()
    {
        if ($message = $this->messageForUnrecognised()) {
            return $message;
        }

        $invalid = $this->invalid();

        if ($invalid->isEmpty()) {
            $key = 'default';
        } else {
            $key = (1 === $invalid->count()) ? 'singular' : 'plural';
        }

        return trans(JsonApiValidation::translationKeyForRule($this, $key), [
            'values' => $invalid->sort()->implode(', '),
        ]);
    }

    /**
     * @return string|null
     */
    private function messageForUnrecognised(): ?string
    {
        $unrecognised = $this->unrecognised();

        if ($unrecognised->isEmpty()) {
            return null;
        }

        $key = (1 === $unrecognised->count()) ? 'singular' : 'plural';

        return trans(JsonApiValidation::translationKeyForRule($this, "unrecognised.{$key}"), [
            'types' => $unrecognised->sort()->implode(', '),
        ]);
    }

    /**
     * Are the fields allowed for the specified resource type?
     *
     * @param string $resourceType
     * @param string|null $fields
     * @return bool
     */
    protected function allowed(string $resourceType, ?string $fields): bool
    {
        return $this->notAllowed($resourceType, $fields)->isEmpty();
    }

    /**
     * Get the invalid fields for the resource type.
     *
     * @param string $resourceType
     * @param string|null $fields
     * @return Collection
     */
    protected function notAllowed(string $resourceType, ?string $fields): Collection
    {
        $fields = empty($fields) ? Collection::make() : Collection::make(explode(',', $fields));

        if (!$this->allowed->has($resourceType)) {
            $this->allowed[$resourceType] = $this->fieldsFor($resourceType);
        }

        $allowed = $this->allowed->get($resourceType);

        if ($allowed === null) {
            return Collection::make();
        }

        $allowed = Collection::make(Arr::wrap($allowed));

        return $fields->reject(fn($value) => $allowed->contains($value));
    }

    /**
     * Get the resource types that are not recognised.
     *
     * @return Collection
     */
    protected function unrecognised(): Collection
    {
        return Collection::make($this->value ?? [])
            ->keys()
            ->reject(fn(string $resourceType) => $this->isResourceType($resourceType))
            ->values();
    }

    /**
     * Get the fields that are invalid.
     *
     * @return Collection
     */
    protected function invalid(): Collection
    {
        return Collection::make($this->value ?? [])->map(function ($value, $key) {
            return $this->notAllowed($key, $value);
        })->flatMap(function (Collection $fields, $type) {
            return $fields->map(function ($field) use ($type) {
                return "{$type}.{$field}";
            });
        });
    }

    /**
     * @param string $resourceType
     * @return array
     */
    private function fieldsFor(string $resourceType): array
    {
        if ($this->isResourceType($resourceType)) {
            return Collection::make($this->schemas
                ->schemaFor($resourceType)
                ->sparseFields()
            )->all();
        }

        return [];
    }

    /**
     * @param string $resourceType
     * @return bool
     */
    private function isResourceType(string $resourceType): bool
    {
        return $this->schemas && $this->schemas->exists($resourceType);
    }
}
