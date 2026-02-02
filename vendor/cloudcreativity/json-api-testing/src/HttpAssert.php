<?php
/*
 * Copyright 2025 Cloud Creativity Limited
 *
 * Use of this source code is governed by an MIT-style
 * license that can be found in the LICENSE file or at
 * https://opensource.org/licenses/MIT.
 */

declare(strict_types=1);

namespace CloudCreativity\JsonApi\Testing;

use CloudCreativity\JsonApi\Testing\Constraints\HttpStatusIs;
use CloudCreativity\JsonApi\Testing\Constraints\HttpStatusIsSuccessful;
use CloudCreativity\JsonApi\Testing\Utils\JsonObject;
use CloudCreativity\JsonApi\Testing\Utils\JsonStack;
use Illuminate\Support\Str;
use JsonSerializable;
use PHPUnit\Framework\Assert as PHPUnitAssert;

/**
 * Class HttpAssert
 *
 * @package CloudCreativity\JsonApi\Testing
 */
class HttpAssert
{

    private const JSON_MEDIA_TYPE = 'application/json';
    private const JSON_API_MEDIA_TYPE = 'application/vnd.api+json';
    private const STATUS_OK = 200;
    private const STATUS_CREATED = 201;
    private const STATUS_ACCEPTED = 202;
    private const STATUS_NO_CONTENT = 204;

    /**
     * Assert that the HTTP status matches the expected status.
     *
     * This assertion will print any JSON API errors in the supplied content if
     * the HTTP status does not match. This is useful for understanding why the status
     * is not the expected status when an error has occurred.
     *
     * @param string|int $status
     * @param int $expected
     * @param string|null $content
     * @param string $message
     * @return void
     */
    public static function assertStatusCode(
        $status,
        int $expected,
        ?string $content = null,
        string $message = ''
    ): void
    {
        PHPUnitAssert::assertThat(
            $status,
            new HttpStatusIs($expected, $content),
            $message
        );
    }

    /**
     * Assert that the provided HTTP status is successful.
     *
     * The HTTP content must be provided for this assertion, because a 204 No Content status
     * would not be valid if there is content.
     *
     * @param string|int $status
     * @param string|null $content
     * @param string $message
     */
    public static function assertStatusIsSuccessful($status, ?string $content, string $message = ''): void
    {
        PHPUnitAssert::assertThat(
            $status,
            new HttpStatusIsSuccessful($content),
            $message
        );
    }

    /**
     * Assert that there is content with the expected media type.
     *
     * @param string|null $contentType
     * @param string|null $content
     * @param string $expected
     * @param string $message
     * @return Document
     */
    public static function assertContent(
        ?string $contentType,
        ?string $content,
        string $expected = self::JSON_API_MEDIA_TYPE,
        string $message = ''
    ): Document
    {
        PHPUnitAssert::assertSame($expected, $contentType, $message ?: "Expecting content with media type {$expected}.");
        PHPUnitAssert::assertNotEmpty($content, $message ?: 'Expecting HTTP body to have content.');

        return Document::cast($content);
    }

    /**
     * Assert a JSON HTTP message with an expected status.
     *
     * @param string|int $status
     * @param string|null $contentType
     * @param string|null $content
     * @param int $expected
     *      the expected HTTP status.
     * @param string $message
     * @return Document
     */
    public static function assertJson(
        $status,
        ?string $contentType,
        ?string $content,
        int $expected = self::STATUS_OK,
        string $message = ''
    ): Document
    {
        self::assertStatusCode($status, $expected, $content, $message);

        return self::assertContent($contentType, $content, self::JSON_MEDIA_TYPE, $message);
    }

    /**
     * Assert a JSON API HTTP message with an expected status.
     *
     * @param string|int $status
     * @param string|null $contentType
     * @param string|null $content
     * @param int $expected
     *      the expected HTTP status.
     * @param string $message
     * @return Document
     */
    public static function assertJsonApi(
        $status,
        ?string $contentType,
        ?string $content,
        int $expected = self::STATUS_OK,
        string $message = ''
    ): Document
    {
        self::assertStatusCode($status, $expected, $content, $message);

        return self::assertContent($contentType, $content, self::JSON_API_MEDIA_TYPE, $message);
    }

    /**
     * Assert a JSON API HTTP message with a successful status.
     *
     * @param int $status
     * @param string|null $contentType
     * @param string|null $content
     * @param string $message
     * @return Document
     */
    public static function assertJsonApiIsSuccessful(
        int $status,
        ?string $contentType,
        ?string $content,
        string $message = ''
    ): Document
    {
        self::assertStatusIsSuccessful($status, $content, $message);

        return self::assertContent($contentType, $content, self::JSON_API_MEDIA_TYPE, $message);
    }

    /**
     * Assert that a resource was fetched.
     *
     * @param string|int $status
     * @param string|null $contentType
     * @param string|null $content
     * @param array|JsonSerializable $expected
     *      the expected resource, or a subset of the expected resource.
     * @param bool $strict
     * @param string $message
     * @return Document
     */
    public static function assertFetchedOne(
        $status,
        ?string $contentType,
        ?string $content,
        $expected,
        bool $strict = true,
        string $message = ''
    ): Document
    {
        return self::assertJsonApi($status, $contentType, $content, self::STATUS_OK, $message)
            ->assertHash($expected, '/data', $strict, $message);
    }

    /**
     * Assert that an exact resource or resource collection was fetched.
     *
     * @param string|int $status
     * @param string|null $contentType
     * @param string|null $content
     * @param array|JsonSerializable $expected
     *      the expected content of the document's data member.
     * @param bool $strict
     * @param string $message
     * @return Document
     */
    public static function assertFetchedExact(
        $status,
        ?string $contentType,
        ?string $content,
        $expected,
        bool $strict = true,
        string $message = ''
    ): Document
    {
        return self::assertJsonApi($status, $contentType, $content, self::STATUS_OK, $message)
            ->assertExact($expected, '/data', $strict, $message);
    }

    /**
     * Assert that the fetched data is null.
     *
     * @param string|int $status
     * @param string|null $contentType
     * @param string|null $content
     * @param string $message
     * @return Document
     */
    public static function assertFetchedNull(
        $status,
        ?string $contentType,
        ?string $content,
        string $message = ''
    ): Document
    {
        return self::assertJsonApi($status, $contentType, $content, self::STATUS_OK, $message)
            ->assertNull('/data', $message);
    }

    /**
     * Assert that a resource collection was fetched.
     *
     * @param string|int $status
     * @param string|null $contentType
     * @param string|null $content
     * @param iterable $expected
     * @param bool $strict
     * @param string $message
     * @return Document
     */
    public static function assertFetchedMany(
        $status,
        ?string $contentType,
        ?string $content,
        iterable $expected,
        bool $strict = true,
        string $message = ''
    ): Document
    {
        return self::assertJsonApi($status, $contentType, $content, self::STATUS_OK, $message)
            ->assertList($expected, '/data', $strict, $message);
    }

    /**
     * Assert that a resource collection was fetched in the expected order.
     *
     * @param string|int $status
     * @param string|null $contentType
     * @param string|null $content
     * @param iterable $expected
     * @param bool $strict
     * @param string $message
     * @return Document
     */
    public static function assertFetchedManyInOrder(
        $status,
        ?string $contentType,
        ?string $content,
        iterable $expected,
        bool $strict = true,
        string $message = ''
    ): Document
    {
        $expected = JsonStack::cast($expected);

        if ($expected->isEmpty()) {
            return self::assertFetchedNone($status, $contentType, $content, $message);
        }

        return self::assertJsonApi($status, $contentType, $content, self::STATUS_OK, $message)
            ->assertListInOrder($expected, '/data', $strict, $message);
    }

    /**
     * Assert that an empty resource collection was fetched.
     *
     * @param string|int $status
     * @param string|null $contentType
     * @param string|null $content
     * @param string $message
     * @return Document
     */
    public static function assertFetchedNone(
        $status,
        ?string $contentType,
        ?string $content,
        string $message = ''
    ): Document
    {
        return self::assertJsonApi($status, $contentType, $content, self::STATUS_OK, $message)
            ->assertListEmpty('/data', $message);
    }

    /**
     * Assert that a to-one relationship was fetched.
     *
     * @param string|int $status
     * @param string|null $contentType
     * @param string|null $content
     * @param string $type
     * @param string $id
     * @param string $message
     * @return Document
     */
    public static function assertFetchedToOne(
        $status,
        ?string $contentType,
        ?string $content,
        string $type,
        string $id,
        string $message = ''
    ): Document
    {
        return self::assertJsonApi($status, $contentType, $content, self::STATUS_OK, $message)
            ->assertIdentifier($type, $id, '/data', $message);
    }

    /**
     * Assert that a to-many relationship was fetched.
     *
     * @param string|int $status
     * @param string|null $contentType
     * @param string|null $content
     * @param iterable $expected
     * @param bool $strict
     * @param string $message
     * @return Document
     */
    public static function assertFetchedToMany(
        $status,
        ?string $contentType,
        ?string $content,
        iterable $expected,
        bool $strict = true,
        string $message = ''
    ): Document
    {
        $expected = JsonStack::cast($expected);

        if ($expected->isEmpty()) {
            return self::assertFetchedNone($status, $contentType, $content, $message);
        }

        return self::assertJsonApi($status, $contentType, $content, self::STATUS_OK, $message)
            ->assertIdentifiersList($expected, '/data', $strict, $message);
    }

    /**
     * Assert that a to-many relationship was fetched in the expected order.
     *
     * @param string|int $status
     * @param string|null $contentType
     * @param string|null $content
     * @param array $expected
     * @param bool $strict
     * @param string $message
     * @return Document
     */
    public static function assertFetchedToManyInOrder(
        $status,
        ?string $contentType,
        ?string $content,
        iterable $expected,
        bool $strict = true,
        string $message = ''
    ): Document
    {
        $expected = JsonStack::cast($expected);

        if ($expected->isEmpty()) {
            return self::assertFetchedNone($status, $contentType, $content, $message);
        }

        return self::assertJsonApi($status, $contentType, $content, self::STATUS_OK, $message)
            ->assertIdentifiersListInOrder($expected, '/data', $strict, $message);
    }

    /**
     * Assert that a resource was created with a server generated id.
     *
     * @param string|int $status
     * @param string|null $contentType
     * @param string|null $content
     * @param string|null $location
     * @param string|null $expectedLocation
     *      the expected location without the id, or null if none is expected.
     * @param array|JsonSerializable $expected
     * @param bool $strict
     * @param string $message
     * @return Document
     */
    public static function assertCreatedWithServerId(
        $status,
        ?string $contentType,
        ?string $content,
        ?string $location,
        ?string $expectedLocation,
        $expected,
        bool $strict = true,
        string $message = ''
    ): Document
    {
        self::assertStatusCode($status, self::STATUS_CREATED, $content, $message);
        $document = self::assertServerGeneratedId($contentType, $content, $expected, $strict, $message);
        $id = $document->get('/data/id');

        PHPUnitAssert::assertNotEmpty($id, $message ?: 'Expecting content to contain a resource id.');

        if (null === $expectedLocation) {
            PHPUnitAssert::assertNull($location, 'Expecting no Location header.');
        } else {
            PHPUnitAssert::assertNotNull($location, 'Missing Location header.');
            $expectedLocation = rtrim($expectedLocation, '/') . '/' . $id;
            PHPUnitAssert::assertSame($expectedLocation, $location, $message ?: 'Unexpected Location header.');
        }

        return $document;
    }

    /**
     * Assert that a resource was created with a client generated id.
     *
     * @param string|int $status
     * @param string|null $contentType
     * @param string|null $content
     * @param string|null $location
     * @param string|null $expectedLocation
     *      the expected location without the id, or null if none is expected.
     * @param array|JsonSerializable $expected
     * @param bool $strict
     * @param string $message
     * @return Document
     */
    public static function assertCreatedWithClientId(
        $status,
        ?string $contentType,
        ?string $content,
        ?string $location,
        ?string $expectedLocation,
        $expected,
        bool $strict = true,
        string $message = ''
    ): Document
    {
        $expected = JsonObject::cast($expected);
        $expectedId = $expected['id'] ?? null;

        if (!$expectedId) {
            throw new \InvalidArgumentException('Expected resource hash must have an id.');
        }

        self::assertStatusCode($status, self::STATUS_CREATED, $content, $message);

        if (null === $expectedLocation) {
            PHPUnitAssert::assertNull($location, 'Expecting no Location header.');
        } else {
            PHPUnitAssert::assertNotNull($location, 'Missing Location header.');
            $expectedLocationWithId = Str::endsWith($expectedLocation, '/' . $expectedId) ?
                $expectedLocation :
                "$expectedLocation/{$expectedId}";
            PHPUnitAssert::assertSame(
                $expectedLocationWithId,
                $location,
                $message ?: 'Unexpected Location header.'
            );
        }

        return self::assertContent($contentType, $content, self::JSON_API_MEDIA_TYPE, $message)
            ->assertHash($expected, '/data', $strict, $message);
    }

    /**
     * Assert that a resource was created with a no content response.
     *
     * @param string|int $status
     * @param string|null $location
     * @param string|null $expectedLocation
     * @param string $message = ''
     * @return void
     */
    public static function assertCreatedNoContent(
        $status,
        ?string $location,
        ?string $expectedLocation,
        string $message = ''
    ): void
    {
        self::assertStatusCode($status, self::STATUS_NO_CONTENT, null, $message);

        if (null === $expectedLocation) {
            PHPUnitAssert::assertNull($location, 'Expecting no Location header.');
        } else {
            PHPUnitAssert::assertNotNull($location, 'Missing Location header.');
            PHPUnitAssert::assertSame($expectedLocation, $location, $message ?: 'Unexpected Location header.');
        }
    }

    /**
     * Assert that an asynchronous process was accepted with a server id.
     *
     * @param string|int $status
     * @param string|null $contentType
     * @param string|null $content
     * @param string|null $contentLocation
     * @param string $expectedLocation
     * @param array|JsonSerializable $expected
     * @param bool $strict
     * @param string $message
     * @return Document
     */
    public static function assertAcceptedWithId(
        $status,
        ?string $contentType,
        ?string $content,
        ?string $contentLocation,
        string $expectedLocation,
        $expected,
        bool $strict = true,
        string $message = ''
    ): Document
    {
        self::assertStatusCode($status, self::STATUS_ACCEPTED, $content, $message);
        $document = self::assertServerGeneratedId($contentType, $content, $expected, $strict, $message);
        $id = $document->get('/data/id');

        PHPUnitAssert::assertNotEmpty($id, $message ?: 'Expecting content to contain a resource id.');
        $expectedLocation = rtrim($expectedLocation, '/') . '/' . $id;
        PHPUnitAssert::assertSame($expectedLocation, $contentLocation, $message ?: 'Unexpected Location header.');

        return $document;
    }

    /**
     * Assert a no content response.
     *
     * @param string|int $status
     * @param string|null $content
     * @param string $message
     * @return void
     */
    public static function assertNoContent($status, ?string $content = null, string $message = ''): void
    {
        self::assertStatusCode($status, self::STATUS_NO_CONTENT, $content, $message);
        PHPUnitAssert::assertEmpty($content, $message ?: 'Expecting HTTP body content to be empty.');
    }

    /**
     * Assert a top-level meta response without data.
     *
     * @param string|int $status
     * @param string|null $contentType
     * @param string|null $content
     * @param array|JsonSerializable $expected
     * @param bool $strict
     * @param string $message
     * @return Document
     */
    public static function assertMetaWithoutData(
        $status,
        ?string $contentType,
        ?string $content,
        $expected,
        bool $strict = true,
        string $message = ''
    ): Document
    {
        return self::assertJsonApiIsSuccessful($status, $contentType, $content, $message)
            ->assertNotExists('/data', $message ?: 'Data member exists.')
            ->assertMeta($expected, $strict, $message);
    }

    /**
     * Assert an exact top-level meta response without data.
     *
     * @param string|int $status
     * @param string|null $contentType
     * @param string|null $content
     * @param array|JsonSerializable $expected
     * @param bool $strict
     * @param string $message
     * @return Document
     */
    public static function assertExactMetaWithoutData(
        $status,
        ?string $contentType,
        ?string $content,
        $expected,
        bool $strict = true,
        string $message = ''
    ): Document
    {
        return self::assertJsonApiIsSuccessful($status, $contentType, $content, $message)
            ->assertNotExists('/data', $message ?: 'Data member exists.')
            ->assertExactMeta($expected, $strict, $message);
    }

    /**
     * Assert the document contains a single error that matches the supplied error.
     *
     * @param string|int $status
     * @param string|null $contentType
     * @param string|null $content
     * @param int $expectedStatus
     * @param array|JsonSerializable $error
     * @param bool $strict
     * @param string $message
     * @return Document
     */
    public static function assertError(
        $status,
        ?string $contentType,
        ?string $content,
        int $expectedStatus,
        $error = [],
        bool $strict = true,
        string $message = ''
    ): Document
    {
        $document = self::assertJsonApi($status, $contentType, $content, $expectedStatus, $message)
            ->assertNotExists('/data', $message);

        $error = JsonObject::cast($error);

        if ($error->isNotEmpty()) {
            $document->assertError($error, $strict, $message);
        } else {
            $document->assertExists('/error', $message);
        }

        return $document;
    }

    /**
     * Assert the document contains an exact single error that matches the supplied error.
     *
     * @param string|int $status
     * @param string|null $contentType
     * @param string|null $content
     * @param int $expectedStatus
     * @param array|JsonSerializable $error
     * @param bool $strict
     * @param string $message
     * @return Document
     */
    public static function assertExactError(
        $status,
        ?string $contentType,
        ?string $content,
        int $expectedStatus,
        $error,
        bool $strict = true,
        string $message = ''
    ): Document
    {
        return self::assertJsonApi($status, $contentType, $content, $expectedStatus, $message)
            ->assertNotExists('/data', $message)
            ->assertExactError($error, $strict, $message);
    }

    /**
     * Assert the document contains a single error that matches the supplied error and has a status member.
     *
     * @param string|int $status
     * @param string|null $contentType
     * @param string|null $content
     * @param array|JsonSerializable $error
     * @param bool $strict
     * @param string $message
     * @return Document
     */
    public static function assertErrorStatus(
        $status,
        ?string $contentType,
        ?string $content,
        $error = [],
        bool $strict = true,
        string $message = ''
    ): Document
    {
        $error = JsonObject::cast($error);
        $expectedStatus = $error['status'] ?? null;

        if (!$expectedStatus) {
            throw new \InvalidArgumentException('Expecting error to have a status member.');
        }

        return self::assertError(
            $status,
            $contentType,
            $content,
            (int) $expectedStatus,
            $error,
            $strict,
            $message
        );
    }

    /**
     * Assert the document contains an exact single error that matches the supplied error and has a status member.
     *
     * @param string|int $status
     * @param string|null $contentType
     * @param string|null $content
     * @param array|JsonSerializable $error
     * @param bool $strict
     * @param string $message
     * @return Document
     */
    public static function assertExactErrorStatus(
        $status,
        ?string $contentType,
        ?string $content,
        $error,
        bool $strict = true,
        string $message = ''
    ): Document
    {
        $error = JsonObject::cast($error);
        $expectedStatus = $error['status'] ?? null;

        if (!$expectedStatus) {
            throw new \InvalidArgumentException('Expecting error to have a status member.');
        }

        return self::assertExactError(
            $status,
            $contentType,
            $content,
            (int) $expectedStatus,
            $error,
            $strict,
            $message
        );
    }

    /**
     * Assert the HTTP message contains the supplied error within its errors member.
     *
     * @param string|int  $status
     * @param string|null $contentType
     * @param string|null $content
     * @param int $expectedStatus
     * @param array|JsonSerializable $error
     * @param bool $strict
     * @param string $message
     * @return Document
     */
    public static function assertHasError(
        $status,
        ?string $contentType,
        ?string $content,
        int $expectedStatus,
        $error = [],
        bool $strict = true,
        string $message = ''
    ): Document
    {
        $error = JsonObject::cast($error);

        if ($error->isEmpty()) {
            $error = ['status' => (string) $status];
        }

        return self::assertJsonApi($status, $contentType, $content, $expectedStatus, $message)
            ->assertHasError($error, $strict, $message);
    }

    /**
     * Assert the HTTP message contains the supplied error within its errors member.
     *
     * @param string|int $status
     * @param string|null $contentType
     * @param string|null $content
     * @param int $expectedStatus
     * @param array|JsonSerializable $error
     * @param bool $strict
     * @param string $message
     * @return Document
     */
    public static function assertHasExactError(
        $status,
        ?string $contentType,
        ?string $content,
        int $expectedStatus,
        $error,
        bool $strict = true,
        string $message = ''
    ): Document
    {
        $error = JsonObject::cast($error);

        if ($error->isEmpty()) {
            $error = ['status' => (string) $status];
        }

        return self::assertJsonApi($status, $contentType, $content, $expectedStatus, $message)
            ->assertHasExactError($error, $strict, $message);
    }

    /**
     * Assert the HTTP status contains the supplied errors.
     *
     * @param string|int $status
     * @param string|null $contentType
     * @param string|null $content
     * @param int $expectedStatus
     * @param iterable $errors
     * @param bool $strict
     * @param string $message
     * @return Document
     */
    public static function assertErrors(
        $status,
        ?string $contentType,
        ?string $content,
        int $expectedStatus,
        iterable $errors,
        bool $strict = true,
        string $message = ''
    ): Document
    {
        return self::assertJsonApi($status, $contentType, $content, $expectedStatus, $message)
            ->assertErrors($errors, $strict, $message);
    }

    /**
     * Assert the HTTP status contains the exact supplied errors.
     *
     * @param string|int $status
     * @param string|null $contentType
     * @param string|null $content
     * @param int $expectedStatus
     * @param iterable $errors
     * @param bool $strict
     * @param string $message
     * @return Document
     */
    public static function assertExactErrors(
        $status,
        ?string $contentType,
        ?string $content,
        int $expectedStatus,
        iterable $errors,
        bool $strict = true,
        string $message = ''
    ): Document
    {
        return self::assertJsonApi($status, $contentType, $content, $expectedStatus, $message)
            ->assertExactErrors($errors, $strict, $message);
    }

    /**
     * @param string|null $contentType
     * @param string|null $content
     * @param array|JsonSerializable $expected
     * @param bool $strict
     * @param string $message
     * @return Document
     */
    private static function assertServerGeneratedId(
        ?string $contentType,
        ?string $content,
        $expected,
        bool $strict,
        string $message = ''
    ): Document
    {
        $id = $expected['id'] ?? null;

        $document = self::assertContent($contentType, $content, self::JSON_API_MEDIA_TYPE, $message)
            ->assertHash($expected, '/data', $strict, $message);

        if ($id) {
            PHPUnitAssert::assertSame($id, $document->get('/data/id'), $message);
        } else {
            $document->assertExists('/data/id', $message);
        }

        return $document;
    }
}
