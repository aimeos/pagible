<?php

namespace Aimeos\Prisma\Contracts;


interface Provider
{
    /**
     * Create a new provider instance with the given configuration.
     *
     * @param array<string, mixed> $config Configuration options for the provider.
     */
    public function __construct( array $config );


    /**
     * Ensures that the provider has implemented the method.
     *
     * @param string $method Method name
     * @return Provider
     * @throws \Aimeos\Prisma\Exceptions\NotImplementedException
     */
    public function ensure( string $method ) : self;


    /**
     * Tests if the provider has implemented the method.
     *
     * @param string $method Method name
     * @return bool TRUE if implemented, FALSE if absent
     */
    public function has( string $method ) : bool;


    /**
     * Use the model passed by its name.
     *
     * Used if the provider supports more than one model and allows to select
     * between the different models. Otherwise, it's ignored.
     *
     * @param string|null $model Model name
     * @return self Provider interface
     */
    public function model( ?string $model ) : self;


    /**
     * Add client handler for the Guzzle HTTP client.
     *
     * @param \GuzzleHttp\HandlerStack $stack List of Guzzle middleware
     * @return self Provider interface
     */
    public function withClientHandler( \GuzzleHttp\HandlerStack $stack ) : self;


    /**
     * Add options for the Guzzle HTTP client.
     *
     * @param array<string, mixed> $options Associative list of name/value pairs
     * @return self Provider interface
     */
    public function withClientOptions( array $options ) : self;


    /**
     * Add a system prompt for the LLM.
     *
     * It may be used by providers supporting system prompts. Otherwise, it's
     * ignored.
     *
     * @param string|null $prompt System prompt
     * @return self Provider interface
     */
    public function withSystemPrompt( ?string $prompt ) : self;
}
