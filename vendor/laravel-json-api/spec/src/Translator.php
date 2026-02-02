<?php
/*
 * Copyright 2024 Cloud Creativity Limited
 *
 * Use of this source code is governed by an MIT-style
 * license that can be found in the LICENSE file or at
 * https://opensource.org/licenses/MIT.
 */

declare(strict_types=1);

namespace LaravelJsonApi\Spec;

use Illuminate\Contracts\Translation\Translator as IlluminateTranslator;
use JsonException;
use LaravelJsonApi\Contracts\Schema\Relation;
use LaravelJsonApi\Core\Document\Error;
use LaravelJsonApi\Core\Support\Str;

class Translator
{

    /**
     * @var IlluminateTranslator
     */
    private IlluminateTranslator $translator;

    /**
     * Translator constructor.
     *
     * @param IlluminateTranslator $translator
     */
    public function __construct(IlluminateTranslator $translator)
    {
        $this->translator = $translator;
    }

    /**
     * Create an error for when the JSON string is empty.
     *
     * @return Error
     */
    public function jsonEmpty(): Error
    {
        return Error::make()
            ->setStatus(400)
            ->setCode($this->trans('json_empty', 'code'))
            ->setTitle($this->trans('json_empty', 'title'))
            ->setDetail($this->trans('json_empty', 'detail'));
    }

    /**
     * Create an error for a JSON decoding exception.
     *
     * @param JsonException $ex
     * @return Error
     */
    public function jsonError(JsonException $ex): Error
    {
        return Error::make()
            ->setStatus(400)
            ->setCode($ex->getCode())
            ->setTitle($this->trans('json_error', 'title'))
            ->setDetail($this->translator->get($ex->getMessage()));
    }

    /**
     * Create an error for when a JSON string does not decode to an object.
     *
     * @return Error
     */
    public function jsonNotObject(): Error
    {
        return Error::make()
            ->setStatus(400)
            ->setCode($this->trans('json_not_object', 'code'))
            ->setTitle($this->trans('json_not_object', 'title'))
            ->setDetail($this->trans('json_not_object', 'detail'));
    }

    /**
     * Create an error for a member that is required.
     *
     * @param string $path
     * @param string $member
     * @return Error
     */
    public function memberRequired(string $path, string $member): Error
    {
        return Error::make()
            ->setStatus(400)
            ->setCode($this->trans('member_required', 'code'))
            ->setTitle($this->trans('member_required', 'title'))
            ->setDetail($this->trans('member_required', 'detail', compact('member')))
            ->setSourcePointer($this->pointer($path));
    }

    /**
     * Create an error for a member that must be an object.
     *
     * @param string $path
     * @param string $member
     * @return Error
     */
    public function memberNotObject(string $path, string $member): Error
    {
        return Error::make()
            ->setStatus(400)
            ->setCode($this->trans('member_object_expected', 'code'))
            ->setTitle($this->trans('member_object_expected', 'title'))
            ->setDetail($this->trans('member_object_expected', 'detail', compact('member')))
            ->setSourcePointer($this->pointer($path, $member));
    }

    /**
     * Create an error for a member that must be an array.
     *
     * @param string $path
     * @param string $member
     * @return Error
     */
    public function memberNotArray(string $path, string $member): Error
    {
        return Error::make()
            ->setStatus(400)
            ->setCode($this->trans('member_array_expected', 'code'))
            ->setTitle($this->trans('member_array_expected', 'title'))
            ->setDetail($this->trans('member_array_expected', 'detail', compact('member')))
            ->setSourcePointer($this->pointer($path, $member));
    }


    /**
     * Create an error for a member that must be a resource identifier.
     *
     * @param string $path
     * @param string $member
     * @return Error
     */
    public function memberNotIdentifier(string $path, string $member): Error
    {
        return Error::make()
            ->setStatus(400)
            ->setCode($this->trans('member_identifier_expected', 'code'))
            ->setTitle($this->trans('member_identifier_expected', 'title'))
            ->setDetail($this->trans('member_identifier_expected', 'detail', compact('member')))
            ->setSourcePointer($this->pointer($path, $member));
    }

    /**
     * Create an error for when a member has a field that is not allowed.
     *
     * @param string $path
     * @param string $member
     * @param string $field
     * @return Error
     */
    public function memberFieldNotAllowed(string $path, string $member, string $field): Error
    {
        return Error::make()
            ->setStatus(400)
            ->setCode($this->trans('member_field_not_allowed', 'code'))
            ->setTitle($this->trans('member_field_not_allowed', 'title'))
            ->setDetail($this->trans('member_field_not_allowed', 'detail', compact('member', 'field')))
            ->setSourcePointer($this->pointer($path, $member));
    }

    /**
     * Create an error for when a member has a field that is not supported.
     *
     * @param string $path
     * @param string $member
     * @param string $field
     * @return Error
     */
    public function memberFieldNotSupported(string $path, string $member, string $field): Error
    {
        $type = $this->translator->get(Str::singular($member));

        return Error::make()
            ->setStatus(400)
            ->setCode($this->trans('member_field_not_supported', 'code'))
            ->setTitle($this->trans('member_field_not_supported', 'title'))
            ->setDetail($this->trans('member_field_not_supported', 'detail', compact('type', 'field')))
            ->setSourcePointer($this->pointer($path, $member));
    }

    /**
     * Create an error for when a to-one relation is expected, but a to-many has been sent.
     *
     * @param string $path
     * @param string $member
     * @param string $field
     * @return Error
     */
    public function fieldExpectsToOne(string $path, string $member, string $field): Error
    {
        return Error::make()
            ->setStatus(400)
            ->setCode($this->trans('field_expects_to_one', 'code'))
            ->setTitle($this->trans('field_expects_to_one', 'title'))
            ->setDetail($this->trans('field_expects_to_one', 'detail', compact('field')))
            ->setSourcePointer($this->pointer($path, $member));
    }

    /**
     * Create an error for when a to-many relation is expected, but a to-one has been sent.
     *
     * @param string $path
     * @param string $member
     * @param string $field
     * @return Error
     */
    public function fieldExpectsToMany(string $path, string $member, string $field): Error
    {
        return Error::make()
            ->setStatus(400)
            ->setCode($this->trans('field_expects_to_many', 'code'))
            ->setTitle($this->trans('field_expects_to_many', 'title'))
            ->setDetail($this->trans('field_expects_to_many', 'detail', compact('field')))
            ->setSourcePointer($this->pointer($path, $member));
    }

    /**
     * Create an error for a member that must be a string.
     *
     * @param string $path
     * @param string $member
     * @return Error
     */
    public function memberNotString(string $path, string $member): Error
    {
        return Error::make()
            ->setStatus(400)
            ->setCode($this->trans('member_string_expected', 'code'))
            ->setTitle($this->trans('member_string_expected', 'title'))
            ->setDetail($this->trans('member_string_expected', 'detail', compact('member')))
            ->setSourcePointer($this->pointer($path, $member));
    }


    /**
     * Create an error for a member that cannot be an empty value.
     *
     * @param string $path
     * @param string $member
     * @return Error
     */
    public function memberEmpty(string $path, string $member): Error
    {
        return Error::make()
            ->setStatus(400)
            ->setCode($this->trans('member_empty', 'code'))
            ->setTitle($this->trans('member_empty', 'title'))
            ->setDetail($this->trans('member_empty', 'detail', compact('member')))
            ->setSourcePointer($this->pointer($path, $member));
    }

    /**
     * Create an error for when the resource type is not supported by the endpoint.
     *
     * @param string $type
     * @param string $path
     * @return Error
     */
    public function resourceTypeNotSupported(string $type, string $path = '/data'): Error
    {
        return Error::make()
            ->setStatus(409)
            ->setCode($this->trans('resource_type_not_supported', 'code'))
            ->setTitle($this->trans('resource_type_not_supported', 'title'))
            ->setDetail($this->trans('resource_type_not_supported', 'detail', compact('type')))
            ->setSourcePointer($this->pointer($path, 'type'));
    }

    /**
     * Create an error for when the resource type is not supported by a relationship.
     *
     * @param Relation $relation
     * @param string $path
     * @return Error
     */
    public function resourceTypeNotSupportedByRelationship(Relation $relation, string $path = '/data'): Error
    {
        $key = sprintf(
            'resource_type_not_supported_by_%s_relationship',
            $relation->toOne() ? 'to_one' : 'to_many',
        );

        $data = [
            'field' => $relation->name(),
            'types' => implode(', ', $relation->allInverse()),
        ];

        return Error::make()
            ->setStatus(422)
            ->setCode($this->trans($key, 'code'))
            ->setTitle($this->trans($key, 'title'))
            ->setDetail($this->trans($key, 'detail', $data))
            ->setSourcePointer($this->pointer($path));
    }

    /**
     * Create an error for when a resource type is not recognised.
     *
     * @param string $type
     *      the resource type that is not recognised.
     * @param string $path
     * @return Error
     */
    public function resourceTypeNotRecognised(string $type, string $path = '/data'): Error
    {
        return Error::make()
            ->setStatus(400)
            ->setCode($this->trans('resource_type_not_recognised', 'code'))
            ->setTitle($this->trans('resource_type_not_recognised', 'title'))
            ->setDetail($this->trans('resource_type_not_recognised', 'detail', compact('type')))
            ->setSourcePointer($this->pointer($path, 'type'));
    }

    /**
     * Create an error for when the resource id is not supported by the endpoint.
     *
     * @param string $id
     *      the resource id that is not supported.
     * @param string $path
     * @return Error
     */
    public function resourceIdNotSupported(string $id, string $path = '/data'): Error
    {
        return Error::make()
            ->setStatus(409)
            ->setCode($this->trans('resource_id_not_supported', 'code'))
            ->setTitle($this->trans('resource_id_not_supported', 'title'))
            ->setDetail($this->trans('resource_id_not_supported', 'detail', compact('id')))
            ->setSourcePointer($this->pointer($path, 'id'));
    }

    /**
     * Create an error for when a resource does not support client-generated ids.
     *
     * @param string $type
     * @param string $path
     * @return Error
     */
    public function resourceDoesNotSupportClientIds(string $type, string $path = '/data'): Error
    {
        return Error::make()
            ->setStatus(403)
            ->setCode($this->trans('resource_client_ids_not_supported', 'code'))
            ->setTitle($this->trans('resource_client_ids_not_supported', 'title'))
            ->setDetail($this->trans('resource_client_ids_not_supported', 'detail', compact('type')))
            ->setSourcePointer($this->pointer($path, 'id'));
    }

    /**
     * Create an error for a resource already existing.
     *
     * @param string $type
     *      the resource type
     * @param string $id
     *      the resource id
     * @param string $path
     * @return Error
     */
    public function resourceExists(string $type, string $id, string $path = '/data'): Error
    {
        return Error::make()
            ->setStatus(409)
            ->setCode($this->trans('resource_exists', 'code'))
            ->setTitle($this->trans('resource_exists', 'title'))
            ->setDetail($this->trans('resource_exists', 'detail', compact('type', 'id')))
            ->setSourcePointer($path);
    }

    /**
     * Create an error for a resource identifier that does not exist.
     *
     * @param string $path
     * @return Error
     */
    public function resourceDoesNotExist(string $path): Error
    {
        return Error::make()
            ->setStatus(404)
            ->setCode($this->trans('resource_not_found', 'code'))
            ->setTitle($this->trans('resource_not_found', 'title'))
            ->setDetail($this->trans('resource_not_found', 'detail'))
            ->setSourcePointer($this->pointer($path));
    }

    /**
     * Create an error for when a resource field exists in both the attributes and relationships members.
     *
     * @param string $field
     * @param string $path
     * @return Error
     */
    public function resourceFieldExistsInAttributesAndRelationships(
        string $field,
        string $path = '/data'
    ): Error
    {
        $key = 'resource_field_exists_in_attributes_and_relationships';

        return Error::make()
            ->setStatus(400)
            ->setCode($this->trans($key, 'code'))
            ->setTitle($this->trans($key, 'title'))
            ->setDetail($this->trans($key, 'detail', compact('field')))
            ->setSourcePointer($this->pointer($path));
    }

    /**
     * Translate an error member value.
     *
     * @param string $key
     *      the key for the JSON API error object.
     * @param string $member
     *      the JSON API error object member name.
     * @param array $replace
     * @param string|null $locale
     * @return string|null
     */
    private function trans(string $key, string $member, array $replace = [], ?string $locale = null)
    {
        $namespace = JsonApiSpec::$translationNamespace;

        $value = $this->translator->get(
            $key = "{$namespace}::spec.{$key}.{$member}",
            $replace,
            $locale
        ) ?: null;

        return ($key !== $value) ? $value : null;
    }

    /**
     * Create a source pointer for the specified path and optional member at that path.
     *
     * @param string $path
     * @param string|null $member
     * @return string
     */
    private function pointer(string $path, ?string $member = null): string
    {
        /** Member can be '0' which is an empty string. */
        $withoutMember = is_null($member) || '' === $member;

        if ($withoutMember) {
            return $path;
        }

        return sprintf('%s/%s', rtrim($path, '/'), $member);
    }
}
