<?php
/*
 * Copyright 2025 Cloud Creativity Limited
 *
 * Use of this source code is governed by an MIT-style
 * license that can be found in the LICENSE file or at
 * https://opensource.org/licenses/MIT.
 */

declare(strict_types=1);

namespace CloudCreativity\JsonApi\Testing\Concerns;

use CloudCreativity\JsonApi\Testing\Document;
use CloudCreativity\JsonApi\Testing\HttpAssert;
use CloudCreativity\JsonApi\Testing\Utils\JsonObject;
use CloudCreativity\JsonApi\Testing\Utils\JsonStack;
use Illuminate\Contracts\Routing\UrlRoutable;
use JsonSerializable;

/**
 * Trait HasHttpAssertions
 *
 * @package CloudCreativity\JsonApi\Testing
 */
trait HasHttpAssertions
{
    /**
     * @var Document|null
     */
    protected ?Document $document = null;

    /**
     * @var string
     */
    private string $expectedType = '';

    /**
     * Get the JSON API document.
     *
     * @return Document
     */
    public function getDocument(): Document
    {
        if ($this->document) {
            return $this->document;
        }

        return $this->document = HttpAssert::assertContent(
            $this->getContentType(),
            $this->getContent()
        );
    }

    /**
     * @return string
     */
    public function getExpectedType(): string
    {
        return $this->expectedType;
    }

    /**
     * @param string $type
     * @return $this
     */
    public function willSeeResourceType(string $type): self
    {
        return $this->willSeeType($type);
    }

    /**
     * Set the expected resource type for the data member of the JSON document.
     *
     * @param string $type
     * @return $this
     */
    public function willSeeType(string $type): self
    {
        if (empty($type)) {
            throw new \InvalidArgumentException('Expected type must be a non-empty string.');
        }

        $this->expectedType = $type;

        return $this;
    }

    /**
     * @param int $status
     * @return $this
     */
    public function assertStatusCode(int $status): self
    {
        HttpAssert::assertStatusCode($this->getStatusCode(), $status, $this->getContent());

        return $this;
    }

    /**
     * Assert that a resource was fetched.
     *
     * @param array|JsonSerializable|UrlRoutable|string|int $expected
     *      the expected resource, or a subset of the expected resource.
     * @param bool $strict
     * @return $this
     */
    public function assertFetchedOne($expected, bool $strict = true): self
    {
        $this->document = HttpAssert::assertFetchedOne(
            $this->getStatusCode(),
            $this->getContentType(),
            $this->getContent(),
            JsonObject::cast($expected, $this->getExpectedType()),
            $strict
        );

        return $this;
    }

    /**
     * Assert that an exact resource was fetched.
     *
     * @param array|JsonSerializable|UrlRoutable|string|int $expected
     *      the expected resource.
     * @param bool $strict
     * @return $this
     */
    public function assertFetchedOneExact($expected, bool $strict = true): self
    {
        $this->document = HttpAssert::assertFetchedExact(
            $this->getStatusCode(),
            $this->getContentType(),
            $this->getContent(),
            JsonObject::cast($expected, $this->getExpectedType()),
            $strict
        );

        return $this;
    }

    /**
     * Assert that the fetched data is null.
     *
     * @return $this
     */
    public function assertFetchedNull(): self
    {
        $this->document = HttpAssert::assertFetchedNull(
            $this->getStatusCode(),
            $this->getContentType(),
            $this->getContent()
        );

        return $this;
    }

    /**
     * Assert that a resource collection was fetched.
     *
     * @param iterable $expected
     * @param bool $strict
     * @return $this
     */
    public function assertFetchedMany(iterable $expected, bool $strict = true): self
    {
        $this->document = HttpAssert::assertFetchedMany(
            $this->getStatusCode(),
            $this->getContentType(),
            $this->getContent(),
            new JsonStack($expected, $this->getExpectedType()),
            $strict
        );

        return $this;
    }

    /**
     * Assert that an exact resource collection was fetched.
     *
     * @param iterable $expected
     * @param bool $strict
     * @return $this
     */
    public function assertFetchedManyExact(iterable $expected, bool $strict = true): self
    {
        $this->document = HttpAssert::assertFetchedExact(
            $this->getStatusCode(),
            $this->getContentType(),
            $this->getContent(),
            new JsonStack($expected, $this->getExpectedType()),
            $strict
        );

        return $this;
    }

    /**
     * Assert that a resource collection was fetched in the expected order.
     *
     * @param iterable $expected
     * @param bool $strict
     * @return $this
     */
    public function assertFetchedManyInOrder(iterable $expected, bool $strict = true): self
    {
        $this->document = HttpAssert::assertFetchedManyInOrder(
            $this->getStatusCode(),
            $this->getContentType(),
            $this->getContent(),
            new JsonStack($expected, $this->getExpectedType()),
            $strict
        );

        return $this;
    }

    /**
     * Assert that an empty resource collection was fetched.
     *
     * @return $this
     */
    public function assertFetchedNone(): self
    {
        $this->document = HttpAssert::assertFetchedNone(
            $this->getStatusCode(),
            $this->getContentType(),
            $this->getContent()
        );

        return $this;
    }

    /**
     * Assert that a to-one relationship was fetched.
     *
     * @param array|JsonSerializable|UrlRoutable|string|int $expected
     * @return $this
     */
    public function assertFetchedToOne($expected): self
    {
        $identifier = JsonObject::cast(
            $expected,
            $this->getExpectedType()
        )->toArray();

        $this->document = HttpAssert::assertFetchedToOne(
            $this->getStatusCode(),
            $this->getContentType(),
            $this->getContent(),
            $identifier['type'],
            $identifier['id']
        );

        return $this;
    }

    /**
     * Assert that a to-many relationship was fetched.
     *
     * @param iterable $expected
     * @param bool $strict
     * @return $this
     */
    public function assertFetchedToMany(iterable $expected, bool $strict = true): self
    {
        $this->document = HttpAssert::assertFetchedToMany(
            $this->getStatusCode(),
            $this->getContentType(),
            $this->getContent(),
            new JsonStack($expected, $this->getExpectedType()),
            $strict
        );

        return $this;
    }

    /**
     * Assert that a to-many relationship was fetched in the expected order.
     *
     * @param iterable $expected
     * @param bool $strict
     * @return $this
     */
    public function assertFetchedToManyInOrder(iterable $expected, bool $strict = true): self
    {
        $this->document = HttpAssert::assertFetchedToManyInOrder(
            $this->getStatusCode(),
            $this->getContentType(),
            $this->getContent(),
            new JsonStack($expected, $this->getExpectedType()),
            $strict
        );

        return $this;
    }

    /**
     * Assert that a resource was created with a server generated id.
     *
     * @param string|null $expectedLocation
     *      the expected location without the id, or null if no location header is expected.
     * @param array|JsonSerializable|UrlRoutable|string|int $expected
     * @param bool $strict
     * @return $this
     */
    public function assertCreatedWithServerId(?string $expectedLocation, $expected, bool $strict = true): self
    {
        $this->document = HttpAssert::assertCreatedWithServerId(
            $this->getStatusCode(),
            $this->getContentType(),
            $this->getContent(),
            $this->getLocation(),
            $expectedLocation,
            JsonObject::cast($expected, $this->getExpectedType()),
            $strict
        );

        return $this;
    }

    /**
     * Assert that a resource was created with a client generated id.
     *
     * @param string|null $expectedLocation
     *      the expected location without the id, or null if no location header is expected.
     * @param array|JsonSerializable|UrlRoutable|string|int $expected
     * @param bool $strict
     * @return $this
     */
    public function assertCreatedWithClientId(?string $expectedLocation, $expected, bool $strict = true): self
    {
        $this->document = HttpAssert::assertCreatedWithClientId(
            $this->getStatusCode(),
            $this->getContentType(),
            $this->getContent(),
            $this->getLocation(),
            $expectedLocation,
            JsonObject::cast($expected, $this->getExpectedType()),
            $strict
        );

        return $this;
    }

    /**
     * Assert that a resource was created with a no content response.
     *
     * @param string $expectedLocation
     * @return $this
     */
    public function assertCreatedNoContent(string $expectedLocation): self
    {
        HttpAssert::assertCreatedNoContent(
            $this->getStatusCode(),
            $this->getLocation(),
            $expectedLocation
        );

        return $this;
    }

    /**
     * Assert that an asynchronous process was accepted with a server id.
     *
     * @param string $expectedLocation
     * @param array|JsonSerializable|UrlRoutable|string|int $expected
     * @param bool $strict
     * @return $this
     */
    public function assertAcceptedWithId(string $expectedLocation, $expected, bool $strict = true): self
    {
        $this->document = HttpAssert::assertAcceptedWithId(
            $this->getStatusCode(),
            $this->getContentType(),
            $this->getContent(),
            $this->getContentLocation(),
            $expectedLocation,
            JsonObject::cast($expected, $this->getExpectedType()),
            $strict
        );

        return $this;
    }

    /**
     * Assert that the expected resource is included in the document.
     *
     * @param string $type
     * @param UrlRoutable|string|int $id
     * @return $this
     */
    public function assertIsIncluded(string $type, $id): self
    {
        $this->getDocument()->assertIncludedContainsResource(
            $type,
            $id,
        );

        return $this;
    }

    /**
     * Assert that the included member contains the supplied resource.
     *
     * @param array|JsonSerializable|UrlRoutable|string|int $expected
     * @param bool $strict
     * @return $this
     */
    public function assertIncludes($expected, bool $strict = true): self
    {
        $this->getDocument()->assertIncludedContainsHash(
            JsonObject::cast($expected, $this->getExpectedType()),
            $strict
        );

        return $this;
    }

    /**
     * Assert that the document's included member matches the expected array.
     *
     * This does not assert the order of the included member because there is no significance to
     * the order of resources in the included member.
     *
     * @param iterable $expected
     * @param bool $strict
     * @return $this
     */
    public function assertIncluded(iterable $expected, bool $strict = true): self
    {
        $this->getDocument()->assertIncluded(
            new JsonStack($expected, $this->getExpectedType()),
            $strict
        );

        return $this;
    }

    /**
     * Assert that the document does not have the top-level included member.
     *
     * @return $this
     */
    public function assertDoesntHaveIncluded(): self
    {
        $this->getDocument()->assertNotExists('included', 'Document has included resources.');

        return $this;
    }

    /**
     * Assert a top-level meta response without data.
     *
     * @param array|JsonSerializable $expected
     * @param bool $strict
     * @return $this
     */
    public function assertMetaWithoutData($expected, bool $strict = true): self
    {
        $this->document = HttpAssert::assertMetaWithoutData(
            $this->getStatusCode(),
            $this->getContentType(),
            $this->getContent(),
            $expected,
            $strict
        );

        return $this;
    }

    /**
     * Assert an exact top-level meta response without data.
     *
     * @param array|JsonSerializable $expected
     * @param bool $strict
     * @return $this
     */
    public function assertExactMetaWithoutData($expected, bool $strict = true): self
    {
        $this->document = HttpAssert::assertExactMetaWithoutData(
            $this->getStatusCode(),
            $this->getContentType(),
            $this->getContent(),
            $expected,
            $strict
        );

        return $this;
    }

    /**
     * Assert that the top-level meta matches the expected values.
     *
     * @param array|JsonSerializable $expected
     * @param bool $strict
     * @return $this
     */
    public function assertMeta($expected, bool $strict = true): self
    {
        $this->getDocument()->assertMeta($expected, $strict);

        return $this;
    }

    /**
     * Assert that the top-level meta is exactly the expected meta.
     *
     * @param array|JsonSerializable $expected
     * @param bool $strict
     * @return $this
     */
    public function assertExactMeta($expected, bool $strict = true): self
    {
        $this->getDocument()->assertExactMeta($expected, $strict);

        return $this;
    }

    /**
     * Assert that the document does not have the top-level meta member.
     *
     * @return $this
     */
    public function assertDoesntHaveMeta(): self
    {
        $this->getDocument()->assertNotExists('meta', 'Document has top-level meta.');

        return $this;
    }

    /**
     * Assert that the top-level links match the expected values.
     *
     * @param array|JsonSerializable $expected
     * @param bool $strict
     * @return $this
     */
    public function assertLinks($expected, bool $strict = true): self
    {
        $this->getDocument()->assertLinks($expected, $strict);

        return $this;
    }

    /**
     * Assert that the top-level links are exactly the expected links.
     *
     * @param array|JsonSerializable $expected
     * @param bool $strict
     * @return $this
     */
    public function assertExactLinks($expected, bool $strict = true): self
    {
        $this->getDocument()->assertExactLinks($expected, $strict);

        return $this;
    }

    /**
     * Assert that the document does not have the top-level links member.
     *
     * @return $this
     */
    public function assertDoesntHaveLinks(): self
    {
        $this->getDocument()->assertNotExists('links', 'Document has top-level links.');

        return $this;
    }

    /**
     * Assert the document contains a single error that matches the supplied error.
     *
     * @param int $status
     * @param array|JsonSerializable $error
     * @param bool $strict
     * @return $this
     */
    public function assertError(int $status, $error = [], bool $strict = true): self
    {
        $this->document = HttpAssert::assertError(
            $this->getStatusCode(),
            $this->getContentType(),
            $this->getContent(),
            $status,
            $error,
            $strict
        );

        return $this;
    }

    /**
     * Assert the document contains a single  error that exactly matches the supplied error.
     *
     * @param int $status
     * @param array|JsonSerializable $error
     * @param bool $strict
     * @return $this
     */
    public function assertExactError(int $status, $error, bool $strict = true): self
    {
        $this->document = HttpAssert::assertExactError(
            $this->getStatusCode(),
            $this->getContentType(),
            $this->getContent(),
            $status,
            $error,
            $strict
        );

        return $this;
    }

    /**
     * Assert the document contains a single error that matches the supplied error and has a status member.
     *
     * @param array|JsonSerializable $error
     * @param bool $strict
     * @return $this
     */
    public function assertErrorStatus($error, bool $strict = true): self
    {
        $this->document = HttpAssert::assertErrorStatus(
            $this->getStatusCode(),
            $this->getContentType(),
            $this->getContent(),
            $error,
            $strict
        );

        return $this;
    }

    /**
     * Assert the document contains a single error that exactly matches the supplied error and has a status member.
     *
     * @param array|JsonSerializable $error
     * @param bool $strict
     * @return $this
     */
    public function assertExactErrorStatus($error, bool $strict = true): self
    {
        $this->document = HttpAssert::assertExactErrorStatus(
            $this->getStatusCode(),
            $this->getContentType(),
            $this->getContent(),
            $error,
            $strict
        );

        return $this;
    }

    /**
     * Assert the HTTP message contains the supplied error within its errors member.
     *
     * @param int $status
     * @param array|JsonSerializable $error
     * @param bool $strict
     * @return $this
     */
    public function assertHasError(int $status, $error = [], bool $strict = true): self
    {
        $this->document = HttpAssert::assertHasError(
            $this->getStatusCode(),
            $this->getContentType(),
            $this->getContent(),
            $status,
            $error,
            $strict
        );

        return $this;
    }

    /**
     * Assert the HTTP message contains the exact supplied error within its errors member.
     *
     * @param int $status
     * @param array|JsonSerializable $error
     * @param bool $strict
     * @return $this
     */
    public function assertHasExactError(int $status, $error, bool $strict = true): self
    {
        $this->document = HttpAssert::assertHasExactError(
            $this->getStatusCode(),
            $this->getContentType(),
            $this->getContent(),
            $status,
            $error,
            $strict
        );

        return $this;
    }

    /**
     * Assert the HTTP status contains the supplied errors.
     *
     * @param int $status
     * @param iterable $errors
     * @param bool $strict
     * @return $this
     */
    public function assertErrors(int $status, iterable $errors, bool $strict = true): self
    {
        $this->document = HttpAssert::assertErrors(
            $this->getStatusCode(),
            $this->getContentType(),
            $this->getContent(),
            $status,
            $errors,
            $strict
        );

        return $this;
    }

    /**
     * Assert the HTTP status contains the supplied exact errors.
     *
     * @param int $status
     * @param iterable $errors
     * @param bool $strict
     * @return $this
     */
    public function assertExactErrors(int $status, iterable $errors, bool $strict = true): self
    {
        $this->document = HttpAssert::assertExactErrors(
            $this->getStatusCode(),
            $this->getContentType(),
            $this->getContent(),
            $status,
            $errors,
            $strict
        );

        return $this;
    }
}
