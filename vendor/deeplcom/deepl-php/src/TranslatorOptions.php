<?php

// Copyright 2022 DeepL SE (https://www.deepl.com)
// Use of this source code is governed by an MIT
// license that can be found in the LICENSE file.

namespace DeepL;

use Psr\Log\LoggerInterface;

/**
 * Options that can be specified when constructing a Translator.
 * Please note that using any options from TranslatorOptions::IGNORED_OPTIONS_WITH_CUSTOM_HTTP_CLIENT,
 * such as proxy or timeout, are ignored when using a custom HTTP client.
 * @see Translator::__construct
 */
class TranslatorOptions
{
    /**
     * Array of all strings in this class that are used to reference translator options in the options array.
     * If you add a new option, please add it here as well for proper validation.
     * The value for each key does not matter.
     */
    private const OPTIONS_KEYS = [
        TranslatorOptions::SERVER_URL => true,
        TranslatorOptions::HEADERS => true,
        TranslatorOptions::TIMEOUT => true,
        TranslatorOptions::MAX_RETRIES => true,
        TranslatorOptions::PROXY => true,
        TranslatorOptions::LOGGER => true,
        TranslatorOptions::HTTP_CLIENT => true,
        TranslatorOptions::SEND_PLATFORM_INFO => true,
        TranslatorOptions::APP_INFO => true,
    ];

    /** List of all options that are ignored when using a custom HTTP client. */
    private const IGNORED_OPTIONS_WITH_CUSTOM_HTTP_CLIENT = [
        TranslatorOptions::TIMEOUT,
        TranslatorOptions::PROXY,
    ];

    /**
     * Base URL of DeepL API, can be overridden for example for testing purposes. By default, the correct DeepL API URL
     * is selected based on the user account type (free or paid).
     * @see DEFAULT_SERVER_URL
     * @see DEFAULT_SERVER_URL_FREE
     */
    public const SERVER_URL = 'server_url';

    /**
     * HTTP headers attached to every HTTP request. By default, no extra headers are used. Note that during Translator
     * initialization headers for Authorization and User-Agent are added, unless they are overridden in this option.
     */
    public const HEADERS = 'headers';

    /**
     * Connection timeout used for each HTTP request retry, as a float in seconds.
     * @see DEFAULT_TIMEOUT
     */
    public const TIMEOUT = 'timeout';

    /**
     * The maximum number of failed attempts that Translator will retry, per request. Note: only errors due to
     * transient conditions are retried.
     * @see DEFAULT_MAX_RETRIES
     */
    public const MAX_RETRIES = 'max_retries';

    /**
     * Proxy server URL, for example 'https://user:pass@10.10.1.10:3128'.
     */
    public const PROXY = 'proxy';

    /**
     * The PSR-3 compatible logger to log messages to.
     * @see LoggerInterface
     */
    public const LOGGER = 'logger';

    /**
     * The PSR-18 compatible HTTP client used to make HTTP requests, or null to use the default client.
     */
    public const HTTP_CLIENT = 'http_client';

    /** The default server URL used for DeepL API Pro accounts (if SERVER_URL is unspecified). */
    public const DEFAULT_SERVER_URL = 'https://api.deepl.com';

    /** The default server URL used for DeepL API Free accounts (if SERVER_URL is unspecified). */
    public const DEFAULT_SERVER_URL_FREE = 'https://api-free.deepl.com';

    /** The default timeout (if TIMEOUT is unspecified) is 10 seconds. */
    public const DEFAULT_TIMEOUT = 10.0;

    /** The default maximum number of request retries (if MAX_RETRIES is unspecified) is 5. */
    public const DEFAULT_MAX_RETRIES = 5;

    /**
     * Flag that determines if the library sends more detailed information about the platform it runs
     * on with each API call. This is overriden if the User-Agent header is set in the HEADERS field.
     * @see HEADERS
     */
    public const SEND_PLATFORM_INFO = 'send_platform_info';

    /** Name and version of the application that uses this client library. */
    public const APP_INFO = 'app_info';

    /**
     * Validates the options array passed to the Translator object.
     */
    public static function isValid(array $options): bool
    {
        $is_valid = true;
        $maybe_logger = $options[TranslatorOptions::LOGGER] ?? null;
        if (isset($options[TranslatorOptions::HTTP_CLIENT])) {
            foreach (TranslatorOptions::IGNORED_OPTIONS_WITH_CUSTOM_HTTP_CLIENT as $ignored_option) {
                $is_valid &= !TranslatorOptions::isIgnoredHttpOptionSet($ignored_option, $options, $maybe_logger);
            }
        }
        foreach ($options as $option_key => $option_value) {
            if (!array_key_exists($option_key, TranslatorOptions::OPTIONS_KEYS)) {
                if ($maybe_logger !== null) {
                    $maybe_logger->warning("Option $option_key is not recognized and thus ignored.");
                }
                $is_valid = false;
            }
        }
        return $is_valid;
    }

    private static function isIgnoredHttpOptionSet(
        string $keyToCheck,
        array $options,
        ?LoggerInterface $maybe_logger
    ): bool {
        if (array_key_exists($keyToCheck, $options)) {
            if ($maybe_logger !== null) {
                $maybe_logger->warning("Option $keyToCheck is ignored as a custom HTTP client is used.");
            }
            return true;
        }
        return false;
    }
}
