<?php
/*
 * Copyright 2024 Cloud Creativity Limited
 *
 * Use of this source code is governed by an MIT-style
 * license that can be found in the LICENSE file or at
 * https://opensource.org/licenses/MIT.
 */

namespace LaravelJsonApi\Testing;

use Illuminate\Contracts\Routing\UrlRoutable;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use JsonSerializable;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\TestCase;
use function array_walk_recursive;
use function implode;
use function is_bool;
use function is_scalar;

final class TestBuilder
{
    /**
     * @var TestCase|mixed
     */
    private TestCase $test;

    /**
     * @var string|null
     */
    private ?string $accept;

    /**
     * @var string|null
     */
    private ?string $contentType;

    /**
     * @var string|null
     */
    private ?string $expectedResourceType = null;

    /**
     * @var Collection
     */
    private Collection $query;

    /**
     * @var Collection
     */
    private Collection $headers;

    /**
     * @var Collection|null
     */
    private ?Collection $json = null;

    /**
     * @var Collection|null
     */
    private ?Collection $payload = null;

    /**
     * TestBuilder constructor.
     *
     * @param mixed $test
     */
    public function __construct($test)
    {
        $this->test = $test;
        $this->accept = $this->contentType = 'application/vnd.api+json';
        $this->query = new Collection();
        $this->headers = new Collection();
    }

    /**
     * Set the resource type that is expected in the response body.
     *
     * @param string $resourceType
     * @return $this
     */
    public function expects(string $resourceType): self
    {
        $this->expectedResourceType = $resourceType;

        return $this;
    }

    /**
     * Set the accept media type for the request.
     *
     * @param string|null $mediaType
     * @return $this
     */
    public function accept(?string $mediaType): self
    {
        $this->accept = $mediaType;

        return $this;
    }

    /**
     * Set the content media type for the request.
     *
     * @param string|null $mediaType
     * @return $this
     */
    public function contentType(?string $mediaType): self
    {
        $this->contentType = $mediaType;

        return $this;
    }

    /**
     * Set the request content type to 'application/x-www-form-urlencoded'.
     *
     * @return $this
     */
    public function asFormUrlEncoded(): self
    {
        return $this->contentType('application/x-www-form-urlencoded');
    }

    /**
     * Set the request content type to multipart form data.
     *
     * @return $this
     */
    public function asMultiPartFormData(): self
    {
        return $this->contentType(
            'multipart/form-data; boundary=----WebKitFormBoundary' . Str::random(15),
        );
    }

    /**
     * Add query parameters to the request.
     *
     * @param iterable $query
     * @return $this
     */
    public function query(iterable $query): self
    {
        $query = Collection::make($query);

        foreach (['filter', 'page'] as $key) {
            if ($value = $query->get($key)) {
                $query->put($key, $this->convertIds($value));
            }
        }

        $this->query = $this->query->merge($query);

        return $this;
    }

    /**
     * Set the include paths.
     *
     * @param string ...$paths
     * @return $this
     */
    public function includePaths(string ...$paths): self
    {
        $this->query['include'] = implode(',', $paths);

        return $this;
    }

    /**
     * Set the sparse field sets for a resource type.
     *
     * @param string $resourceType
     * @param string|string[] $fieldNames
     * @return $this
     */
    public function sparseFields(string $resourceType, $fieldNames): self
    {
        $this->query['fields'] = Collection::make($this->query->get('fields'))
            ->put($resourceType, implode(',', Arr::wrap($fieldNames)));

        return $this;
    }

    /**
     * Set the filter parameters.
     *
     * @param iterable $filter
     * @return $this
     */
    public function filter(iterable $filter): self
    {
        $this->query['filter'] = $this->convertIds($filter);

        return $this;
    }

    /**
     * Set the sort parameters.
     *
     * @param string ...$sort
     * @return $this
     */
    public function sort(string ...$sort): self
    {
        $this->query['sort'] = implode(',', $sort);

        return $this;
    }

    /**
     * Set the pagination parameters.
     *
     * @param iterable $page
     * @return $this
     */
    public function page(iterable $page): self
    {
        $this->query['page'] = $this->convertIds($page);

        return $this;
    }

    /**
     * Set the data member of the request JSON API document.
     *
     * @param JsonSerializable|iterable|null $data
     * @return $this
     */
    public function withData($data): self
    {
        if ($data === null) {
            return $this->withJson(['data' => null]);
        }

        if (is_object($data) && !$data instanceof JsonSerializable) {
            $data = Collection::make($data);
        }

        return $this->withJson(['data' => $data]);
    }

    /**
     * Set the JSON request document.
     *
     * @param $json
     * @return $this
     */
    public function withJson($json): self
    {
        $this->json = Collection::make($json);

        return $this;
    }

    /**
     * Set the request payload for a non-JSON API request.
     *
     * @param mixed $payload
     * @return $this
     */
    public function withPayload($payload): self
    {
        $this->payload = Collection::make($payload);

        return $this;
    }

    /**
     * @param iterable $headers
     * @return $this
     */
    public function withHeaders(iterable $headers): self
    {
        $this->headers = $this->headers->merge($headers);

        return $this;
    }

    /**
     * @param string $name
     * @param string $value
     * @return $this
     */
    public function withHeader(string $name, string $value): self
    {
        $this->headers->put($name, $value);

        return $this;
    }

    /**
     * Visit the given URI with a GET request, expecting JSON API content.
     *
     * @param string $uri
     * @param iterable $headers
     * @return TestResponse
     */
    public function get(string $uri, iterable $headers = []): TestResponse
    {
        return $this->call('GET', $uri, $headers);
    }

    /**
     * Visit the given URI with a POST request, expecting JSON API content.
     *
     * @param string $uri
     * @param iterable $headers
     * @return TestResponse
     */
    public function post(string $uri, iterable $headers = []): TestResponse
    {
        return $this->call('POST', $uri, $headers);
    }

    /**
     * Visit the given URI with a PATCH request, expecting JSON API content.
     *
     * @param string $uri
     * @param iterable $headers
     * @return TestResponse
     */
    public function patch(string $uri, iterable $headers = []): TestResponse
    {
        return $this->call('PATCH', $uri, $headers);
    }

    /**
     * @param string $uri
     * @param array|iterable $headers
     * @return TestResponse
     */
    public function put(string $uri, iterable $headers = []): TestResponse
    {
        return $this->call('PUT', $uri, $headers);
    }

    /**
     * Visit the given URI with a DELETE request, expecting JSON API content.
     *
     * @param string $uri
     * @param iterable $headers
     * @return TestResponse
     */
    public function delete(string $uri, iterable $headers = []): TestResponse
    {
        return $this->call('DELETE', $uri, $headers);
    }

    /**
     * @param string $method
     * @param string $uri
     * @param iterable $headers
     * @return TestResponse
     */
    public function call(string $method, string $uri, iterable $headers = []): TestResponse
    {
        if ($this->query->isNotEmpty()) {
            $uri .= '?' . $this->buildQuery();
        }

        $headers = $this->buildHeaders($headers);

        if ($this->payload) {
            $payload = $this->payload->toArray();
            // we need a content length as it is used by the JSON API implementation to determine if there is body.
            $headers['CONTENT_LENGTH'] = $headers['CONTENT_LENGTH'] ?? mb_strlen(Arr::query($payload), '8bit');

            $response = $this->test->{strtolower($method)}(
                $uri,
                $payload,
                $headers
            );
        } else {
            $response = $this->test->json(
                $method,
                $uri,
                $this->json ? $this->json->toArray() : [],
                $headers
            );
        }

        $response = TestResponse::cast($response);

        if ($this->expectedResourceType) {
            $response->willSeeResourceType($this->expectedResourceType);
        }

        return $response;
    }

    /**
     * Convert query params to a string.
     *
     * We check all values are strings, integers or floats as these are the only
     * valid values that can be sent in the query params. E.g. if the developer
     * uses a `boolean`, they actually need to test where the strings `'true'`
     * or `'false'` (or the string/integer equivalents) work.
     *
     * @return string
     * @see https://github.com/cloudcreativity/laravel-json-api/issues/427
     */
    private function buildQuery(): string
    {
        $query = $this->query->toArray();

        array_walk_recursive($query, function ($value, $key) {
            if (!is_scalar($value) || is_bool($value)) {
                Assert::fail("Test query parameter at {$key} is not a string, integer or float.");
            }
        });

        return Arr::query($query);
    }

    /**
     * @param iterable $headers
     * @return array
     */
    private function buildHeaders(iterable $headers): array
    {
        return Collection::make(['Accept' => $this->accept, 'CONTENT_TYPE' => $this->contentType])
            ->filter()
            ->merge($this->headers)
            ->merge($headers)
            ->toArray();
    }

    /**
     * @param iterable $values
     * @return Collection
     */
    private function convertIds(iterable $values): Collection
    {
        return Collection::make($values)->map(
            fn($value) => $this->convertId($value)
        );
    }

    /**
     * @param mixed $value
     * @return mixed
     */
    private function convertId($value)
    {
        if ($value instanceof UrlRoutable) {
            return $value->getRouteKey();
        }

        if (is_iterable($value)) {
            return $this->convertIds($value);
        }

        return $value;
    }
}
