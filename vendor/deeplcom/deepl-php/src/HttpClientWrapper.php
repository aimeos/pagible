<?php

// Copyright 2022 DeepL SE (https://www.deepl.com)
// Use of this source code is governed by an MIT
// license that can be found in the LICENSE file.

namespace DeepL;

use Http\Discovery\Psr17FactoryDiscovery;
use Http\Discovery\Psr18Client;
use Http\Message\MultipartStream\MultipartStreamBuilder;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\RequestExceptionInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Log\LoggerInterface;
use Psr\Http\Client\ClientInterface;

/**
 * Internal class implementing HTTP requests.
 * @private
 */
class HttpClientWrapper
{
    private $serverUrl;
    private $headers;
    private $maxRetries;
    private $minTimeout;
    private $logger;
    private $proxy;
    private $customHttpClient;
    private $requestFactory;
    /**
     * PSR-18 client that is only used to construct the HTTP request, not to send it.
     */
    private $streamClient;
    private $streamFactory;

    /**
     * @var resource cURL handle, or null if using a custom HTTP client.
     * @see HttpClientWrapper::__construct
     */
    private $curlHandle;

    public const OPTION_FILE = 'file';
    public const OPTION_HEADERS = 'headers';
    public const OPTION_PARAMS = 'params';
    public const OPTION_OUTFILE = 'outfile';
    public const OPTION_JSON = 'json';

    public function __construct(
        string           $serverUrl,
        array            $headers,
        float            $timeout,
        int              $maxRetries,
        ?LoggerInterface $logger,
        ?string          $proxy,
        ?ClientInterface $customHttpClient = null
    ) {
        $this->serverUrl = $serverUrl;
        $this->maxRetries = $maxRetries;
        $this->minTimeout = $timeout;
        $this->headers = $headers;
        $this->logger = $logger;
        $this->proxy = $proxy;
        $this->customHttpClient = $customHttpClient;
        $this->requestFactory = Psr17FactoryDiscovery::findRequestFactory();
        $this->streamClient = new Psr18Client();
        $this->streamFactory = Psr17FactoryDiscovery::findStreamFactory();
        $this->curlHandle = $customHttpClient === null ? \curl_init() : null;
    }

    public function __destruct()
    {
        if ($this->customHttpClient === null) {
            \curl_close($this->curlHandle);
        }
    }

    /**
     * Makes API request retrying if necessary, and returns (as Promise) response.
     * @param string $method HTTP method, for example 'GET'.
     * @param string $url Path to endpoint, excluding base server URL.
     * @param array|null $options Array of options, possible arguments are given by OPTIONS_ constants.
     * @return array Status code and content.
     * @throws DeepLException
     */
    public function sendRequestWithBackoff(string $method, string $url, ?array $options = []): array
    {
        $url = $this->serverUrl . $url;
        $headers = array_replace(
            $this->headers,
            $options[self::OPTION_HEADERS] ?? []
        );
        $file = $options[self::OPTION_FILE] ?? null;
        $params = $options[self::OPTION_PARAMS] ?? [];
        $this->logInfo("Request to DeepL API $method $url");
        $this->logDebug('Request details: ' . json_encode($params));
        $backoff = new BackoffTimer();
        $response = null;
        $exception = null;
        while ($backoff->getNumRetries() <= $this->maxRetries) {
            $outFile = isset($options[self::OPTION_OUTFILE]) ? fopen($options[self::OPTION_OUTFILE], 'w') : null;
            $timeout = max($this->minTimeout, $backoff->getTimeUntilDeadline());
            $response = null;
            $exception = null;
            $json = isset($options[self::OPTION_JSON]) ? $options[self::OPTION_JSON] : null;
            try {
                $response = $this->sendRequest($method, $url, $timeout, $headers, $params, $file, $outFile, $json);
            } catch (ConnectionException $e) {
                $exception = $e;
            }

            if ($outFile) {
                fclose($outFile);
            }

            if (!$this->shouldRetry($response, $exception) || $backoff->getNumRetries() + 1 >= $this->maxRetries) {
                break;
            }

            if ($exception !== null) {
                $this->logDebug("Encountered a retryable-error: {$exception->getMessage()}");
            }

            $this->logInfo('Starting retry ' . ($backoff->getNumRetries() + 1) .
                " for request $method $url after sleeping for {$backoff->getTimeUntilDeadline()} seconds.");
            $backoff->sleepUntilDeadline();
        }

        if ($exception !== null) {
            throw $exception;
        } else {
            list($statusCode, $content) = $response;
            $this->logInfo("DeepL API response $method $url $statusCode");
            $this->logDebug("Response details: $content");
            return $response;
        }
    }

    /**
     * Sends a HTTP request. Note that in the case of a custom HTTP client, some of these options are
     * ignored in favor of whatever is set in the client (e.g. timeouts and proxy). If we fall back to cURL,
     * those options are respected.
     * @param string $method HTTP method to use.
     * @param string $url Absolute URL to query.
     * @param float $timeout Time to wait before triggering timeout, in seconds.
     * @param array $headers Array of headers to include in request.
     * @param array $params Array of parameters to include in body.
     * @param string|null $filePath If not null, path to file to upload with request.
     * @param resource|null $outFile If not null, file to write output to.
     * @param string|null $json If not null, JSON content to include in body.
     * @return array Array where the first element is the HTTP status code and the second element is the response body.
     * @throws ConnectionException
     */
    private function sendRequest(
        string $method,
        string $url,
        float $timeout,
        array $headers,
        array $params,
        ?string $filePath,
        $outFile,
        ?string $json
    ): array {
        if ($this->customHttpClient !== null) {
            return $this->sendCustomHttpRequest($method, $url, $headers, $params, $filePath, $outFile, $json);
        } else {
            return $this->sendCurlRequest($method, $url, $timeout, $headers, $params, $filePath, $outFile, $json);
        }
    }

    /**
     * Creates a PSR-7 compliant HTTP request with the given arguments.
     * @param string $method HTTP method to use
     * @param string $uri The URI for the request
     * @param array $headers Array of headers for the request
     * @param StreamInterface $body body to be used for the request.
     * @return RequestInterface HTTP request object
     */
    private function createHttpRequest(string $method, string $url, array $headers, StreamInterface $body)
    {
        $request = $this->requestFactory->createRequest($method, $url);
        foreach ($headers as $header_key => $header_val) {
            $request = $request->withHeader($header_key, $header_val);
        }
        $request = $request->withBody($body);
        return $request;
    }

    /**
     * Sends a HTTP request using the custom HTTP client.
     * @param string $method HTTP method to use.
     * @param string $url Absolute URL to query.
     * @param array $headers Array of headers to include in request.
     * @param array $params Array of parameters to include in body.
     * @param string|null $filePath If not null, path to file to upload with request.
     * @param resource|null $outFile If not null, file to write output to.
     * @param string|null $json If not null, JSON content to include in body.
     * @return array Array where the first element is the HTTP status code and the second element is the response body.
     * @throws ConnectionException
     */
    private function sendCustomHttpRequest(
        string $method,
        string $url,
        array $headers,
        array $params,
        ?string $filePath,
        $outFile,
        ?string $json
    ): array {
        $body = null;
        if ($filePath !== null) {
            $builder = new MultipartStreamBuilder($this->streamClient);
            $builder->addResource('file', fopen($filePath, 'r'));
            foreach ($params as $param_name => $value) {
                $builder->addResource($param_name, $value);
            }
            $body = $builder->build();
            $boundary = $builder->getBoundary();
            $headers['Content-Type'] = "multipart/form-data; boundary=\"$boundary\"";
        } elseif (count($params) > 0) {
            $headers['Content-Type'] = 'application/x-www-form-urlencoded';
            $body = $this->streamFactory->createStream(
                $this->urlEncodeWithRepeatedParams($params)
            );
        } elseif (isset($json)) {
            $headers['Content-Type'] = 'application/json';
            $body = $this->streamFactory->createStream($json);
        } else {
            $body = $this->streamFactory->createStream('');
        }
        $request = $this->createHttpRequest($method, $url, $headers, $body);
        try {
            $response = $this->customHttpClient->sendRequest($request);
            $response_data = (string) $response->getBody();
            if ($outFile) {
                fwrite($outFile, $response_data);
            }
            return [$response->getStatusCode(), $response_data];
        } catch (RequestExceptionInterface $e) {
            throw new ConnectionException($e->getMessage(), $e->getCode(), null, false);
        } catch (ClientExceptionInterface $e) {
            throw new ConnectionException($e->getMessage(), $e->getCode(), null, true);
        }
    }

    /**
     * Sends a HTTP request using cURL
     * @param string $method HTTP method to use.
     * @param string $url Absolute URL to query.
     * @param float $timeout Time to wait before triggering timeout, in seconds.
     * @param array $headers Array of headers to include in request.
     * @param array $params Array of parameters to include in body.
     * @param string|null $filePath If not null, path to file to upload with request.
     * @param resource|null $outFile If not null, file to write output to.
     * @param string|null $json If not null, JSON content to include in body.
     * @return array Array where the first element is the HTTP status code and the second element is the response body.
     * @throws ConnectionException
     */
    private function sendCurlRequest(
        string $method,
        string $url,
        float $timeout,
        array $headers,
        array $params,
        ?string $filePath,
        $outFile,
        ?string $json
    ): array {
        $curlOptions = [];
        $curlOptions[\CURLOPT_HEADER] = false;

        switch ($method) {
            case "POST":
                $curlOptions[\CURLOPT_POST] = true;
                break;
            case "GET":
                $curlOptions[\CURLOPT_HTTPGET] = true;
                break;
            default:
                $curlOptions[\CURLOPT_CUSTOMREQUEST] = $method;
                break;
        }

        $curlOptions[\CURLOPT_URL] = $url;
        $curlOptions[\CURLOPT_CONNECTTIMEOUT] = $timeout;
        $curlOptions[\CURLOPT_TIMEOUT_MS] = $timeout * 1000;

        if ($this->proxy !== null) {
            $curlOptions[\CURLOPT_PROXY] = $this->proxy;
        }

        // Convert headers from an associative array to an array of "key: value" elements
        $curlOptions[\CURLOPT_HTTPHEADER] = \array_map(function (string $key, string $value): string {
            return "$key: $value";
        }, array_keys($headers), array_values($headers));

        if ($filePath !== null) {
            // If a file is to be uploaded, add it to the list of body parameters
            $params['file'] = \curl_file_create($filePath);
            $curlOptions[\CURLOPT_POSTFIELDS] = $params;
        } elseif (count($params) > 0) {
            // If there are repeated parameters, passing the parameters directly to cURL will index the repeated
            // parameters which is not what we need, so instead we encode the parameters without indexes.
            // This case only occurs if no file is uploaded.
            $curlOptions[\CURLOPT_POSTFIELDS] = $this->urlEncodeWithRepeatedParams($params);
        } elseif (!is_null($json)) {
            $curlOptions[\CURLOPT_POSTFIELDS] = $json;
            $curlOptions[\CURLOPT_HTTPHEADER][] = 'Content-Type: application/json';
        }

        if ($outFile) {
            // Stream response content to specified file
            $curlOptions[\CURLOPT_FILE] = $outFile;
        } else {
            // Return response content as function result
            $curlOptions[\CURLOPT_RETURNTRANSFER] = true;
        }

        \curl_reset($this->curlHandle);

        // The next 3 curl calls are unqualified so that we can mock them, see
        // https://github.com/php-mock/php-mock-phpunit#restrictions
        curl_setopt_array($this->curlHandle, $curlOptions);

        $result = curl_exec($this->curlHandle);
        if ($result !== false) {
            $statusCode = curl_getinfo($this->curlHandle, CURLINFO_HTTP_CODE);
            return [$statusCode, $result];
        } else {
            $errorMessage = \curl_error($this->curlHandle);
            $errorCode = \curl_errno($this->curlHandle);
            switch ($errorCode) {
                case \CURLE_UNSUPPORTED_PROTOCOL:
                case \CURLE_URL_MALFORMAT:
                case \CURLE_URL_MALFORMAT_USER:
                    $shouldRetry = false;
                    $errorMessage = "Invalid server URL. $errorMessage";
                    break;
                case \CURLE_OPERATION_TIMEOUTED:
                case \CURLE_COULDNT_CONNECT:
                case \CURLE_GOT_NOTHING:
                    $shouldRetry = true;
                    break;
                default:
                    $shouldRetry = false;
                    break;
            }
            throw new ConnectionException($errorMessage, $errorCode, null, $shouldRetry);
        }
    }

    private function shouldRetry(?array $response, ?ConnectionException $exception): bool
    {
        if ($exception !== null) {
            return $exception->shouldRetry;
        }
        list($statusCode, ) = $response;

        // Retry on Too-Many-Requests error and internal errors
        return $statusCode === 429 || $statusCode >= 500;
    }

    public function logDebug(string $message): void
    {
        if ($this->logger) {
            $this->logger->debug($message);
        }
    }

    public function logInfo(string $message): void
    {
        if ($this->logger) {
            $this->logger->info($message);
        }
    }

    private static function urlEncodeWithRepeatedParams(?array $params): string
    {
        $params = $params ?? [];
        $fields = [];
        foreach ($params as $key => $value) {
            $name = \urlencode($key);
            if (is_array($value)) {
                $fields[] = implode(
                    '&',
                    array_map(
                        function (string $textElement) use ($name): string {
                            return $name . '=' . \urlencode($textElement);
                        },
                        $value
                    )
                );
            } elseif (is_null($value)) {
                // Parameters with null value are skipped
            } else {
                $fields[] = $name . '=' . \urlencode($value);
            }
        }

        return implode("&", $fields);
    }
}
