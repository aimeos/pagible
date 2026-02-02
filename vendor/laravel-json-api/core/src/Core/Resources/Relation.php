<?php
/*
 * Copyright 2024 Cloud Creativity Limited
 *
 * Use of this source code is governed by an MIT-style
 * license that can be found in the LICENSE file or at
 * https://opensource.org/licenses/MIT.
 */

declare(strict_types=1);

namespace LaravelJsonApi\Core\Resources;

use Closure;
use InvalidArgumentException;
use LaravelJsonApi\Contracts\Resources\JsonApiRelation;
use LaravelJsonApi\Core\Document\Link;
use LaravelJsonApi\Core\Document\Links;
use LaravelJsonApi\Core\Support\Str;
use LogicException;
use function sprintf;

class Relation implements JsonApiRelation
{

    /**
     * @var object
     */
    protected object $resource;

    /**
     * @var string|null
     */
    private ?string $baseUri;

    /**
     * @var string
     */
    private string $fieldName;

    /**
     * @var string|null
     */
    private ?string $keyName;

    /**
     * @var string|null
     */
    private ?string $uriName;

    /**
     * @var mixed|null
     */
    private $data;

    /**
     * @var bool
     */
    private bool $hasData = false;

    /**
     * @var bool
     */
    private bool $showData = false;

    /**
     * @var bool
     */
    private bool $showSelf = true;

    /**
     * @var bool
     */
    private bool $showRelated = true;

    /**
     * @var array|Closure|null
     */
    private $meta = null;

    /**
     * Relation constructor.
     *
     * @param object $resource
     * @param string|null $baseUri
     * @param string $fieldName
     * @param string|null $keyName
     * @param string|null $uriName
     */
    public function __construct(
        object $resource,
        ?string $baseUri,
        string $fieldName,
        ?string $keyName = null,
        ?string $uriName = null
    ) {
        $this->resource = $resource;
        $this->baseUri = $baseUri;
        $this->fieldName = $fieldName;
        $this->keyName = $keyName ?: Str::camel($fieldName);
        $this->uriName = $uriName;
    }

    /**
     * @inheritDoc
     */
    public function fieldName(): string
    {
        return $this->fieldName;
    }

    /**
     * @inheritDoc
     */
    public function links(): Links
    {
        $links = new Links();

        if ($this->showSelf && $self = $this->selfUrl()) {
            $links->push(new Link('self', $self));
        }

        if ($this->showRelated && $related = $this->relatedUrl()) {
            $links->push(new Link('related', $related));
        }

        return $links;
    }

    /**
     * @inheritDoc
     */
    public function meta(): ?array
    {
        if ($this->meta instanceof Closure) {
            $this->meta = ($this->meta)($this->resource);
        }

        return $this->meta;
    }

    /**
     * @inheritDoc
     */
    public function data()
    {
        if (false === $this->hasData) {
            return $this->value();
        }

        if ($this->data instanceof Closure) {
            return ($this->data)($this->resource);
        }

        return $this->data;
    }

    /**
     * @inheritDoc
     */
    public function showData(): bool
    {
        return $this->showData;
    }

    /**
     * @return string|null
     */
    public function selfUrl(): ?string
    {
        if ($this->baseUri) {
            return sprintf(
                '%s/relationships/%s',
                $this->baseUri,
                $this->uriFieldName()
            );
        }

        return null;
    }

    /**
     * @return string|null
     */
    public function relatedUrl(): ?string
    {
        if ($this->baseUri) {
            return sprintf(
                '%s/%s',
                $this->baseUri,
                $this->uriFieldName()
            );
        }

        return null;
    }

    /**
     * Use the field-name as-is for relationship URLs.
     *
     * @return $this
     */
    public function retainFieldName(): self
    {
        $this->uriName = $this->fieldName();

        return $this;
    }

    /**
     * Use the provided string as the URI fragment for the field name.
     *
     * @param string $uri
     * @return $this
     */
    public function withUriFieldName(string $uri): self
    {
        if (empty($uri)) {
            throw new InvalidArgumentException('Expecting a non-empty string URI fragment.');
        }

        $this->uriName = $uri;

        return $this;
    }

    /**
     * @return $this
     */
    public function withoutSelfLink(): self
    {
        $this->showSelf = false;

        return $this;
    }

    /**
     * @return $this
     */
    public function onlySelfLink(): self
    {
        return $this->withoutRelatedLink();
    }

    /**
     * @return $this
     */
    public function withoutRelatedLink(): self
    {
        $this->showRelated = false;

        return $this;
    }

    /**
     * @return $this
     */
    public function onlyRelatedLink(): self
    {
        return $this->withoutSelfLink();
    }

    /**
     * @return $this
     */
    public function withoutLinks(): self
    {
        $this->withoutSelfLink();
        $this->withoutRelatedLink();

        return $this;
    }

    /**
     * @param mixed $data
     * @return $this
     */
    public function withData($data): self
    {
        $this->data = $data;
        $this->hasData = true;

        return $this;
    }

    /**
     * Always show the data member of the relation.
     *
     * @return $this
     */
    public function alwaysShowData(): self
    {
        $this->showData = true;

        return $this;
    }

    /**
     * Always show the data member of the relation if it is loaded on the model.
     *
     * @return $this
     */
    public function showDataIfLoaded(): self
    {
        if (method_exists($this->resource, 'relationLoaded')) {
            $this->showData = $this->resource->relationLoaded($this->keyName);
            return $this;
        }

        throw new LogicException('Expecting resource to have a relationLoaded method.');
    }

    /**
     * @param $meta
     * @return $this
     */
    public function withMeta($meta): self
    {
        if (!is_array($meta) && !$meta instanceof Closure) {
            throw new InvalidArgumentException('Expecting meta to be an array or a closure.');
        }

        $this->meta = $meta;

        return $this;
    }

    /**
     * Get the value of the relationship.
     *
     * @return mixed
     */
    protected function value()
    {
        return $this->resource->{$this->keyName};
    }

    /**
     * Get the field name for URIs.
     *
     * @return string
     */
    private function uriFieldName(): string
    {
        if ($this->uriName) {
            return $this->uriName;
        }

        return $this->uriName = Str::dasherize($this->fieldName);
    }

}
