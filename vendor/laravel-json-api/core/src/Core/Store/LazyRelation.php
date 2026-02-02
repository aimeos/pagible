<?php
/*
 * Copyright 2024 Cloud Creativity Limited
 *
 * Use of this source code is governed by an MIT-style
 * license that can be found in the LICENSE file or at
 * https://opensource.org/licenses/MIT.
 */

declare(strict_types=1);

namespace LaravelJsonApi\Core\Store;

use Illuminate\Support\Collection;
use IteratorAggregate;
use LaravelJsonApi\Contracts\Schema\Relation;
use LaravelJsonApi\Contracts\Server\Server;
use LaravelJsonApi\Core\Support\Arr;
use LogicException;
use Traversable;

class LazyRelation implements IteratorAggregate
{
    /**
     * @var Server
     */
    private Server $server;

    /**
     * @var Relation
     */
    protected Relation $relation;

    /**
     * @var array
     */
    private array $json;

    /**
     * The cached to-one resource.
     *
     * @var object|null
     */
    private ?object $toOne = null;

    /**
     * Whether the to-one resource has been loaded.
     *
     * @var bool
     */
    private bool $loadedToOne = false;

    /**
     * The cached to-many resources.
     *
     * @var Collection|null
     */
    private ?Collection $toMany = null;

    /**
     * RelatedResource constructor.
     *
     * @param Server $server
     * @param Relation $relation
     * @param array $json
     */
    public function __construct(Server $server, Relation $relation, array $json)
    {
        $this->server = $server;
        $this->relation = $relation;
        $this->json = $json;
    }

    /**
     * Retrieve the related resource for a to-one relation.
     *
     * @return object|null
     */
    public function get(): ?object
    {
        if ($this->relation->toOne()) {
            return $this->toOne();
        }

        throw new LogicException('Can only retrieve the result of a to-one relation.');
    }

    /**
     * @inheritDoc
     */
    public function getIterator(): Traversable
    {
        if ($this->relation->toMany()) {
            yield from $this->toMany();
            return;
        }

        throw new LogicException('Can only iterate over a to-many relation.');
    }

    /**
     * Retrieve the related resources for a to-many relation.
     *
     * @return Collection
     */
    public function collect(): Collection
    {
        if ($this->relation->toMany()) {
            return $this->toMany();
        }

        throw new LogicException('Can only convert a to-many relation to a collection.');
    }

    /**
     * Retrieve the related resources for a to-many relation.
     *
     * @return array
     */
    public function all(): array
    {
        return $this->toMany()->all();
    }

    /**
     * Retrieve the related resource for a to-one relation.
     *
     * @return object|null
     */
    private function toOne(): ?object
    {
        if (true === $this->loadedToOne) {
            return $this->toOne;
        }

        $data = $this->json['data'] ?? [];
        $value = null;

        if ($this->isValid($data)) {
            $value = $this->server->store()->find(
                $data['type'],
                $data['id']
            );
        }

        $this->loadedToOne = true;

        return $this->toOne = $value;
    }

    /**
     * @return Collection
     */
    private function toMany(): Collection
    {
        if ($this->toMany) {
            return $this->toMany;
        }

        $data = $this->json['data'] ?? [];
        $identifiers = [];

        if (is_array($data) && !Arr::isAssoc($data)) {
            $identifiers = Collection::make($data)
                ->filter(fn($value): bool => $this->isValid($value))
                ->values()
                ->all();
        }

        if (empty($identifiers)) {
            return $this->toMany = new Collection();
        }

        return $this->toMany = Collection::make(
            $this->server->store()->findMany($identifiers)
        );
    }

    /**
     * @param mixed $identifier
     * @return bool
     */
    private function isValid($identifier): bool
    {
        if (is_array($identifier) && isset($identifier['type']) && isset($identifier['id'])) {
            return $this->isType($identifier['type']) && $this->isId($identifier['id']);
        }

        return false;
    }

    /**
     * @param mixed $type
     * @return bool
     */
    private function isType($type): bool
    {
        return in_array($type, $this->relation->allInverse(), true);
    }

    /**
     * @param mixed $id
     * @return bool
     */
    private function isId($id): bool
    {
        if (is_string($id)) {
            return !empty($id) || '0' === $id;
        }

        return false;
    }
}
