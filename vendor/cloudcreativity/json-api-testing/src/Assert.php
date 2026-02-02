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

use CloudCreativity\JsonApi\Testing\Constraints\EmptyOrMissingList;
use CloudCreativity\JsonApi\Testing\Constraints\ExactInDocument;
use CloudCreativity\JsonApi\Testing\Constraints\ExactInList;
use CloudCreativity\JsonApi\Testing\Constraints\IdentifierInDocument;
use CloudCreativity\JsonApi\Testing\Constraints\IdentifierInList;
use CloudCreativity\JsonApi\Testing\Constraints\IdentifiersInOrder;
use CloudCreativity\JsonApi\Testing\Constraints\OnlyExactInList;
use CloudCreativity\JsonApi\Testing\Constraints\OnlyIdentifiersInList;
use CloudCreativity\JsonApi\Testing\Constraints\OnlySubsetsInList;
use CloudCreativity\JsonApi\Testing\Constraints\SubsetInDocument;
use CloudCreativity\JsonApi\Testing\Constraints\SubsetInList;
use CloudCreativity\JsonApi\Testing\Constraints\SubsetsInOrder;
use CloudCreativity\JsonApi\Testing\Utils\JsonObject;
use Illuminate\Contracts\Routing\UrlRoutable;
use JsonSerializable;
use PHPUnit\Framework\Assert as PHPUnitAssert;
use PHPUnit\Framework\Constraint\LogicalNot;

/**
 * Class Assert
 *
 * @package CloudCreativity\JsonApi\Testing
 */
class Assert
{

    /**
     * Assert that the value at the pointer has the expected JSON API resource.
     *
     * @param Document|iterable|string $document
     *      the JSON:API document.
     * @param string $type
     *      the expected resource object type.
     * @param string $id
     *      the expected resource object id.
     * @param string $pointer
     *      the JSON pointer to where the resource object is expected in the document.
     * @param string $message
     */
    public static function assertResource(
        $document,
        string $type,
        string $id,
        string $pointer = '/data',
        string $message = ''
    ): void
    {
        self::assertHash($document, compact('type', 'id'), $pointer, true, $message);
    }

    /**
     * Assert that the value at the pointer has the expected JSON API resource identifier.
     *
     * @param Document|iterable|string $document
     *      the JSON:API document.
     * @param string $type
     *      the expected resource object type.
     * @param string $id
     *      the expected resource object id.
     * @param string $pointer
     *      the JSON pointer to where the resource object is expected in the document.
     * @param string $message
     */
    public static function assertIdentifier(
        $document,
        string $type,
        string $id,
        string $pointer = '/data',
        string $message = ''
    ): void
    {
        $expected = compact('type', 'id');

        PHPUnitAssert::assertThat(
            $document,
            new IdentifierInDocument($expected, $pointer, true),
            $message
        );
    }

    /**
     * Assert that the expected value is in the document at the specified path.
     *
     * @param Document|iterable|string $document
     *      the JSON:API document.
     * @param array|JsonSerializable|null $expected
     *      the expected value.
     * @param string $pointer
     *      the JSON pointer to where the object is expected to exist within the document.
     * @param bool $strict
     *      whether strict comparison should be used.
     * @param string $message
     * @return void
     */
    public static function assertExact(
        $document,
        $expected,
        string $pointer = '/data',
        bool $strict = true,
        string $message = ''
    ): void
    {
        PHPUnitAssert::assertThat(
            $document,
            new ExactInDocument($expected, $pointer, $strict),
            $message
        );
    }

    /**
     * Assert that the value at the specified path is not the expected value.
     *
     * @param Document|iterable|string $document
     *      the JSON:API document.
     * @param array|JsonSerializable|null $expected
     * @param string $pointer
     * @param bool $strict
     * @param string $message
     * @return void
     */
    public static function assertNotExact(
        $document,
        $expected,
        string $pointer = '/data',
        bool $strict = true,
        string $message = ''
    ): void
    {
        $constraint = new LogicalNot(
            new ExactInDocument($expected, $pointer, $strict)
        );

        PHPUnitAssert::assertThat($document, $constraint, $message);
    }

    /**
     * Assert that the expected hash is in the document at the specified path.
     *
     * @param Document|iterable|string $document
     *      the JSON:API document.
     * @param array|JsonSerializable $expected
     *      the expected resource object.
     * @param string $pointer
     *      the JSON pointer to where the object is expected to exist within the document.
     * @param bool $strict
     *      whether strict comparison should be used.
     * @param string $message
     * @return void
     */
    public static function assertHash(
        $document,
        $expected,
        string $pointer = '/data',
        bool $strict = true,
        string $message = ''
    ): void
    {
        PHPUnitAssert::assertThat(
            $document,
            new SubsetInDocument($expected, $pointer, $strict),
            $message
        );
    }

    /**
     * Assert that the member contains a null value.
     *
     * @param Document|iterable|string $document
     *      the JSON:API document.
     * @param string $pointer,
     * @param string $message
     * @return void
     */
    public static function assertNull($document, string $pointer = '/data', string $message = ''): void
    {
        self::assertExact($document, null, $pointer, true, $message);
    }

    /**
     * Assert that the member contains an empty list.
     *
     * @param Document|iterable|string $document
     *      the JSON:API document.
     * @param string $pointer
     * @param string $message
     * @return void
     */
    public static function assertListEmpty($document, string $pointer = '/data', string $message = ''): void
    {
        PHPUnitAssert::assertThat(
            $document,
            new EmptyOrMissingList($pointer, false),
            $message
        );
    }

    /**
     * Assert that the member does not contain an empty list.
     *
     * @param Document|iterable|string $document
     *      the JSON:API document.
     * @param string $pointer
     * @param string $message
     * @return void
     */
    public static function assertListNotEmpty($document, string $pointer = '/data', string $message = ''): void
    {
        $constraint = new LogicalNot(
            new EmptyOrMissingList($pointer, false)
        );

        PHPUnitAssert::assertThat($document, $constraint, $message);
    }

    /**
     * Assert that the member contains an empty list, or the member does not exist.
     *
     * @param Document|iterable|string $document
     *      the JSON:API document.
     * @param string $pointer
     * @param string $message
     */
    public static function assertListEmptyOrMissing(
        $document,
        string $pointer = '/data',
        string $message = ''
    ): void
    {
        PHPUnitAssert::assertThat(
            $document,
            new EmptyOrMissingList($pointer, true),
            $message
        );
    }

    /**
     * Assert that the member contains an empty list, or the member does not exist.
     *
     * @param Document|iterable|string $document
     *      the JSON:API document.
     * @param string $pointer
     * @param string $message
     */
    public static function assertListNotEmptyOrMissing(
        $document,
        string $pointer = '/data',
        string $message = ''
    ): void
    {
        $constraint = new LogicalNot(
            new EmptyOrMissingList($pointer, true)
        );

        PHPUnitAssert::assertThat($document, $constraint, $message);
    }

    /**
     * Assert that a list in the document only contains the specified hashes.
     *
     * This assertion does not check that the expected and actual lists are in the same order.
     * To assert the order, use `assertListInOrder`.
     *
     * @param Document|iterable|string $document
     *      the JSON:API document.
     * @param iterable $expected
     * @param string $pointer
     * @param bool $strict
     * @param string $message
     * @return void
     */
    public static function assertList(
        $document,
        iterable $expected,
        string $pointer = '/data',
        bool $strict = true,
        string $message = ''
    ): void
    {
        PHPUnitAssert::assertThat(
            $document,
            new OnlySubsetsInList($expected, $pointer, $strict),
            $message
        );
    }

    /**
     * Assert that a list in the document only contains the specified values.
     *
     * This assertion does not check that the expected and actual lists are in the same order.
     * To assert the order, use `assertExact`.
     *
     * @param Document|iterable|string $document
     *      the JSON:API document.
     * @param iterable $expected
     * @param string $pointer
     * @param bool $strict
     * @param string $message
     * @return void
     */
    public static function assertExactList(
        $document,
        iterable $expected,
        string $pointer = '/data',
        bool $strict = true,
        string $message = ''
    ): void
    {
        PHPUnitAssert::assertThat(
            $document,
            new OnlyExactInList($expected, $pointer, $strict),
            $message
        );
    }

    /**
     * Assert that a list in the document contains the hashes in the specified order.
     *
     * @param Document|iterable|string $document
     *      the JSON:API document.
     * @param iterable $expected
     * @param string $pointer
     * @param bool $strict
     * @param string $message
     * @return void
     */
    public static function assertListInOrder(
        $document,
        iterable $expected,
        string $pointer = '/data',
        bool $strict = true,
        string $message = ''
    ): void
    {
        PHPUnitAssert::assertThat(
            $document,
            new SubsetsInOrder($expected, $pointer, $strict),
            $message
        );
    }

    /**
     * Assert that a list in the document only contains the specified identifiers.
     *
     * Asserting that a list contains only identifiers will fail if any of the items in the
     * list is a resource object. I.e. to pass as an identifier, it must not contain
     * `attributes` and/or `relationships` members.
     *
     * This assertion does not check that the expected and actual lists are in the same order.
     * To assert the order, use `assertIdentifiersListInOrder`.
     *
     * @param Document|iterable|string $document
     *      the JSON:API document.
     * @param iterable $expected
     * @param string $pointer
     * @param bool $strict
     * @param string $message
     * @return void
     */
    public static function assertIdentifiersList(
        $document,
        iterable $expected,
        string $pointer = '/data',
        bool $strict = true,
        string $message = ''
    ): void
    {
        PHPUnitAssert::assertThat(
            $document,
            new OnlyIdentifiersInList($expected, $pointer, $strict),
            $message
        );
    }

    /**
     * Assert that a list in the document contains the identifiers in the specified order.
     *
     * Asserting that a list contains only identifiers will fail if any of the items in the
     * list is a resource object. I.e. to pass as an identifier, it must not contain
     * `attributes` and/or `relationships` members.
     *
     * @param Document|iterable|string $document
     *      the JSON:API document.
     * @param iterable $expected
     * @param string $pointer
     * @param bool $strict
     * @param string $message
     * @return void
     */
    public static function assertIdentifiersListInOrder(
        $document,
        iterable $expected,
        string $pointer = '/data',
        bool $strict = true,
        string $message = ''
    ): void
    {
        PHPUnitAssert::assertThat(
            $document,
            new IdentifiersInOrder($expected, $pointer, $strict),
            $message
        );
    }

    /**
     * Assert that the document has a list containing the expected resource.
     *
     * @param Document|iterable|string $document
     *      the JSON:API document.
     * @param string $type
     *      the expected resource object type.
     * @param UrlRoutable|string|int $id
     *      the expected resource object id.
     * @param string $pointer
     *      the JSON pointer to where the array is expected in the document.
     * @param string $message
     * @return void
     */
    public static function assertListContainsResource(
        $document,
        string $type,
        $id,
        string $pointer = '/data',
        string $message = ''
    ): void
    {
        $expected = JsonObject::fromId($type, $id);

        self::assertListContainsHash($document, $expected, $pointer, true, $message);
    }

    /**
     * Assert that the document has a list containing the expected resource identifier.
     *
     * @param Document|iterable|string $document
     *      the JSON:API document.
     * @param string $type
     *      the expected resource object type.
     * @param string $id
     *      the expected resource object id.
     * @param string $pointer
     *      the JSON pointer to where the array is expected in the document.
     * @param string $message
     * @return void
     */
    public static function assertListContainsIdentifier(
        $document,
        string $type,
        string $id,
        string $pointer = '/data',
        string $message = ''
    ): void
    {
        $expected = compact('type', 'id');

        PHPUnitAssert::assertThat(
            $document,
            new IdentifierInList($expected, $pointer, true),
            $message
        );
    }

    /**
     * Assert that a list in the document at the specified path contains the expected hash.
     *
     * @param Document|iterable|string $document
     *      the JSON:API document.
     * @param array|JsonSerializable $expected
     * @param string $pointer
     * @param bool $strict
     * @param string $message
     * @return void
     */
    public static function assertListContainsHash(
        $document,
        $expected,
        string $pointer = '/data',
        bool $strict = true,
        string $message = ''
    ): void
    {
        PHPUnitAssert::assertThat(
            $document,
            new SubsetInList($expected, $pointer, $strict),
            $message
        );
    }

    /**
     * Assert that a list in the document at the specified path contains the expected value.
     *
     * @param Document|iterable|string $document
     *      the JSON:API document.
     * @param array|JsonSerializable $expected
     * @param string $pointer
     * @param bool $strict
     * @param string $message
     * @return void
     */
    public static function assertListContainsExact(
        $document,
        $expected,
        string $pointer = '/data',
        bool $strict = true,
        string $message = ''
    ): void
    {
        PHPUnitAssert::assertThat(
            $document,
            new ExactInList($expected, $pointer, $strict),
            $message
        );
    }

    /**
     * Assert that the document's included member matches the expected array.
     *
     * This does not assert the order of the included member because there is no significance to
     * the order of resources in the included member.
     *
     * @param Document|iterable|string $document
     *      the JSON:API document.
     * @param iterable $expected
     * @param bool $strict
     * @param string $message
     * @return void
     */
    public static function assertIncluded(
        $document,
        iterable $expected,
        bool $strict = true,
        string $message = ''
    ): void
    {
        self::assertList($document, $expected, '/included', $strict, $message);
    }

    /**
     * Assert that the expected identifier is included in the document.
     *
     * @param Document|iterable|string $document
     *      the JSON:API document.
     * @param string $type
     * @param UrlRoutable|string|int $id
     * @param string $message
     * @return void
     */
    public static function assertIncludedContainsResource(
        $document,
        string $type,
        $id,
        string $message = ''
    ): void
    {
        self::assertListContainsResource($document, $type, $id, '/included', $message);
    }

    /**
     * Assert that the included member contains the supplied hash.
     *
     * @param Document|iterable|string $document
     *      the JSON:API document.
     * @param array|JsonSerializable $expected
     * @param bool $strict
     * @param string $message
     * @return void
     */
    public static function assertIncludedContainsHash(
        $document,
        $expected,
        bool $strict = true,
        string $message = ''
    ): void
    {
        self::assertListContainsHash($document, $expected, '/included', $strict, $message);
    }

    /**
     * Assert that the included member does not exist or is empty.
     *
     * @param Document|iterable|string $document
     *      the JSON:API document.
     * @param string $message
     * @return void
     */
    public static function assertNoneIncluded($document, string $message = ''): void
    {
        self::assertListEmptyOrMissing($document, '/included', $message);
    }

    /**
     * Assert the document contains a single error that matches the supplied error.
     *
     * @param Document|iterable|string $document
     *      the JSON:API document.
     * @param array|JsonSerializable $error
     * @param bool $strict
     * @param string $message
     * @return void
     */
    public static function assertError($document, $error, bool $strict = true, string $message = ''): void
    {
        self::assertList($document, [$error], '/errors', $strict, $message);
    }

    /**
     * Assert the document contains a single error that exactly matches the supplied error.
     *
     * @param Document|iterable|string $document
     *      the JSON:API document.
     * @param array|JsonSerializable $error
     * @param bool $strict
     * @param string $message
     * @return void
     */
    public static function assertExactError($document, $error, bool $strict = true, string $message = ''): void
    {
        self::assertExactList($document, [$error], '/errors', $strict, $message);
    }

    /**
     * Assert the document contains the supplied error within its errors member.
     *
     * @param Document|iterable|string $document
     *      the JSON:API document.
     * @param array|JsonSerializable $error
     * @param bool $strict
     * @param string $message
     * @return void
     */
    public static function assertHasError($document, $error, bool $strict = true, string $message = ''): void
    {
        self::assertListContainsHash($document, $error, '/errors', $strict, $message);
    }

    /**
     * Assert the document contains the exact supplied error within its errors member.
     *
     * @param Document|iterable|string $document
     *      the JSON:API document.
     * @param array|JsonSerializable $error
     * @param bool $strict
     * @param string $message
     * @return void
     */
    public static function assertHasExactError(
        $document,
        $error,
        bool $strict = true,
        string $message = ''
    ): void
    {
        self::assertListContainsExact($document, $error, '/errors', $strict, $message);
    }

    /**
     * Assert the document contains the supplied errors.
     *
     * This does not assert the order of the errors, as the error order does not have any significance.
     *
     * @param Document|iterable|string $document
     *      the JSON:API document.
     * @param iterable $errors
     * @param bool $strict
     * @param string $message
     * @return void
     */
    public static function assertErrors($document, iterable $errors, bool $strict = true, string $message = ''): void
    {
        self::assertList($document, $errors, '/errors', $strict, $message);
    }

    /**
     * Assert the document contains the supplied errors.
     *
     * This does not assert the order of the errors, as the error order does not have any significance.
     *
     * @param Document|iterable|string $document
     *      the JSON:API document.
     * @param iterable $errors
     * @param bool $strict
     * @param string $message
     * @return void
     */
    public static function assertExactErrors($document, iterable $errors, bool $strict = true, string $message = ''): void
    {
        self::assertExactList($document, $errors, '/errors', $strict, $message);
    }
}
