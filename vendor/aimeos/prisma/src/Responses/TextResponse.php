<?php

namespace Aimeos\Prisma\Responses;

use Aimeos\Prisma\Concerns\HasMeta;
use Aimeos\Prisma\Concerns\HasUsage;


/**
 * Text based response.
 */
class TextResponse
{
    use HasMeta, HasUsage;


    /** @var array<string|int, mixed> */
    private array $structured = [];
    private ?string $text = null;


    final private function __construct()
    {
    }


    /**
     * Create a text response instance.
     *
     * @param string $text|null Text content
     * @return self TextResponse instance
     */
    public static function fromText( ?string $text ) : self
    {
        $instance = new self;
        $instance->text = !is_null( $text ) ? trim( $text ) : null;

        return $instance;
    }


    /**
     * Get the structured data.
     *
     * @return array<string|int, mixed> Structured data
     */
    public function structured() : array
    {
        return $this->structured;
    }


    /**
     * Get the text content.
     *
     * @return string|null Text content
     */
    public function text() : ?string
    {
        return $this->text;
    }


    /**
     * Sets the structured data.
     *
     * @param array<string|int, mixed> $structured Structured data
     * @return static TextResponse instance
     */
    public function withStructured( array $structured ) : static
    {
        $this->structured = $structured;
        return $this;
    }
}
