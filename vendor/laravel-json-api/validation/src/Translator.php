<?php
/*
 * Copyright 2024 Cloud Creativity Limited
 *
 * Use of this source code is governed by an MIT-style
 * license that can be found in the LICENSE file or at
 * https://opensource.org/licenses/MIT.
 */

declare(strict_types=1);

namespace LaravelJsonApi\Validation;

use Illuminate\Contracts\Translation\Translator as TranslatorContract;
use Illuminate\Support\Collection;
use LaravelJsonApi\Core\Document\Error;
use LaravelJsonApi\Core\Support\Str;
use function in_array;
use function strtolower;

class Translator
{

    /**
     * @var TranslatorContract
     */
    private TranslatorContract $translator;

    /**
     * Translator constructor.
     *
     * @param TranslatorContract $translator
     */
    public function __construct(TranslatorContract $translator)
    {
        $this->translator = $translator;
    }

    /**
     * Create a generic validation error.
     *
     * @param string $pointer
     * @param string|null $detail
     *      the validation message (already translated).
     * @param array $failed
     *      rule failure details
     * @return Error
     */
    public function invalid(string $pointer, ?string $detail = null, array $failed = []): Error
    {
        return Error::make()
            ->setStatus(422)
            ->setTitle($this->trans('invalid', 'title'))
            ->setDetail($detail ?: $this->trans('invalid', 'detail'))
            ->setCode($this->trans('invalid', 'code'))
            ->setSourcePointer($pointer)
            ->setMeta($failed ? compact('failed') : null);
    }

    /**
     * Create an error for an invalid resource.
     *
     * @param string $pointer
     * @param string|null $detail
     *      the validation message (already translated).
     * @param array $failed
     *      rule failure details
     * @return Error
     */
    public function invalidResource(string $pointer, ?string $detail = null, array $failed = []): Error
    {
        return Error::make()
            ->setStatus(422)
            ->setTitle($this->trans('resource_invalid', 'title'))
            ->setDetail($detail ?: $this->trans('resource_invalid', 'detail'))
            ->setCode($this->trans('resource_invalid', 'code'))
            ->setSourcePointer($pointer)
            ->setMeta($failed ? compact('failed') : null);
    }

    /**
     * Create an error for an invalid query parameter.
     *
     * @param string $param
     * @param string|null $detail
     *      the validation message (already translated).
     * @param array $failed
     *      rule failure details.
     * @return Error
     */
    public function invalidQueryParameter(string $param, ?string $detail = null, array $failed = []): Error
    {
        return Error::make()
            ->setStatus(400)
            ->setTitle($this->trans('query_invalid', 'title'))
            ->setDetail($detail ?: $this->trans('query_invalid', 'detail'))
            ->setCode($this->trans('query_invalid', 'code'))
            ->setSourceParameter($param)
            ->setMeta($failed ? compact('failed') : null);
    }

    /**
     * Create an error for a resource delete request failing.
     *
     * @param string|null $detail
     *      the validation message (already translated).
     * @return Error
     */
    public function invalidDeleteRequest(?string $detail = null): Error
    {
        return Error::make()
            ->setStatus(422)
            ->setTitle($this->trans('delete_invalid', 'title'))
            ->setDetail($detail ?: $this->trans('delete_invalid', 'detail'))
            ->setCode($this->trans('delete_invalid', 'code'));
    }

    /**
     * Translate validation failures.
     *
     * @param array $failures
     * @return Collection
     */
    public function validationFailures(array $failures): Collection
    {
        return collect($failures)
            ->map(fn($options, $rule) => $this->validationFailure($rule, $options))
            ->values();
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
        $namespace = JsonApiValidation::$translationNamespace;

        $value = $this->translator->get(
            $key = "{$namespace}::validation.{$key}.{$member}",
            $replace,
            $locale
        ) ?: null;

        return ($key !== $value) ? $value : null;
    }

    /**
     * @param string $rule
     * @param array|null $options
     * @return string[]
     */
    private function validationFailure(string $rule, ?array $options): array
    {
        $failure = ['rule' => $this->convertRuleName($rule)];

        if (!empty($options) && $this->failedRuleHasOptions($rule)) {
            $failure['options'] = $options;
        }

        return $failure;
    }

    /**
     * @param string $rule
     * @return string
     */
    private function convertRuleName(string $rule): string
    {
        return $this->translator->get(
            Str::dasherize(class_basename($rule))
        );
    }

    /**
     * Should options for the rule be displayed?
     *
     * @param string $rule
     * @return bool
     */
    private function failedRuleHasOptions(string $rule): bool
    {
        return !in_array(strtolower($rule), [
            'exists',
            'unique',
        ], true);
    }
}
