<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;


class ContactRequest extends FormRequest
{
    public const FILE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'heic', 'pdf'];
    public const FILE_SIZE = 10; // MB per file
    public const MAX_FILES = 5;

    private const DEFAULT_MANDATORY_FIELDS = ['name', 'email'];
    private const IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp'];
    private const STANDARD_FIELDS = ['name', 'company', 'telephone', 'email', 'subject'];
    private const TOTAL_SIZE = 20; // MB for all files

    /** @var array{mandatory: array<int, string>, optional: array<int, string>, types: array<string, string|array<int, string>>, files: int}|null */
    private ?array $sets = null;


    /** @return array<string, string> */
    public function attributes(): array
    {
        $attributes = [
            'message' => __( 'Message' ),
            'source' => __( 'Source page' ),
            'files' => __( 'Attachments' ),
            'files.*' => __( 'Attachments' ),
        ];

        foreach( [...$this->mandatory(), ...$this->optional()] as $field ) {
            $attributes[self::key( $field )] = __( $field === 'email' ? 'E-Mail' : \Illuminate\Support\Str::headline( $field ) );
        }

        return $attributes;
    }


    /**
     * Returns the number of files visitors are allowed to attach.
     */
    public function files(): int
    {
        return $this->sets()['files'] ?? 0;
    }


    public static function key( string $field ): string
    {
        return in_array( $field, self::STANDARD_FIELDS, true )
            ? $field
            : 'field_' . substr( hash( 'sha256', $field ), 0, 32 );
    }


    /** @return array<int, string> */
    public function mandatory(): array
    {
        return $this->sets()['mandatory'] ?? [];
    }


    /** @return array<int, string> */
    public function optional(): array
    {
        return $this->sets()['optional'] ?? [];
    }


    /** @return array<string, string> */
    public function messages(): array
    {
        $invalid = __( ':attribute: The value is invalid.' );

        $large = __( ':attribute: The file is too large.' );

        return [
            'required' => __( ':attribute: This field is required.' ),
            'email' => __( ':attribute: Please enter a valid e-mail address.' ),
            'max' => __( ':attribute: The text is too long.' ),
            'files.max' => __( ':attribute: Too many files.' ),
            'files.*.max' => $large,
            'mimes' => __( ':attribute: The file type is not allowed.' ),
            'uploaded' => $large,
            'required_with' => $invalid,
            'prohibited' => $invalid,
            'string' => $invalid,
            'array' => $invalid,
            'file' => $invalid,
            'size' => $invalid,
            'url' => $invalid,
            'in' => $invalid,
        ];
    }


    /** @return array<string, mixed> */
    public function rules(): array
    {
        $rules = [
            'message' => 'required|string|max:5000',
            'schema' => [
                'sometimes',
                'required',
                'string',
                'max:8192',
                function( string $attribute, mixed $value, \Closure $fail ) : void {
                    if( $this->sets() === null ) {
                        $fail( 'validation.in' )->translate( ['attribute' => $attribute] );
                    }
                },
            ],
            'signature' => ['required_with:schema', 'string', 'size:64'],
            'source'  => [
                'nullable',
                'url:http,https',
                'max:2048',
                function( string $attribute, mixed $value, \Closure $fail ) : void {
                    if( is_string( $value )
                        && strcasecmp( (string) parse_url( $value, PHP_URL_HOST ), $this->getHost() ) !== 0
                    ) {
                        $fail( __( 'The source page must use the current host.' ) );
                    }
                },
            ],
        ];

        $mandatory = $this->mandatory();
        $types = $this->sets()['types'] ?? [];

        foreach( [...$mandatory, ...$this->optional()] as $field )
        {
            $rule = in_array( $field, $mandatory, true ) ? 'required' : 'nullable';
            $type = $types[$field] ?? null;

            $rules[self::key( $field )] = match( true ) {
                $field === 'email' => [$rule, 'email:rfc,dns', 'max:254'],
                $type === 'textarea' => [$rule, 'string', 'max:5000'],
                is_array( $type ) => [$rule, 'string', Rule::in( $type )],
                default => [$rule, 'string', 'max:255'],
            };
        }

        if( $files = $this->files() )
        {
            $rules['files'] = ['nullable', 'array', 'max:' . $files, function( string $attribute, mixed $value, \Closure $fail ) : void {
                $size = array_sum( array_map( fn( $file ) => $file instanceof UploadedFile ? (int) $file->getSize() : 0, (array) $value ) );

                if( $size > self::TOTAL_SIZE * 1024 * 1024 ) {
                    $fail( __( ':attribute: The files are too large.' ) );
                }
            }];
            $rules['files.*'] = ['file', 'mimes:' . implode( ',', self::FILE_EXTENSIONS ), 'max:' . self::FILE_SIZE * 1024,
                function( string $attribute, mixed $value, \Closure $fail ) : void {
                    if( $value instanceof UploadedFile && in_array( $value->guessExtension(), self::IMAGE_EXTENSIONS, true ) )
                    {
                        try {
                            \Aimeos\Cms\Models\File::checkPixels( $value );
                        } catch( \Aimeos\Cms\InvalidException ) {
                            $fail( __( ':attribute: The file is too large.' ) );
                        }
                    }
                },
            ];
        } else {
            $rules['files'] = ['prohibited'];
        }

        if( !app()->environment('local') && config('services.hcaptcha.secret') ) {
            $rules['h-captcha-response'] = ['required', new \Aimeos\Cms\Rules\Hcaptcha];
        }

        return $rules;
    }


    /**
     * Uses the language of the page containing the contact form for the validation messages.
     */
    protected function prepareForValidation(): void
    {
        $locale = $this->input( 'locale' );

        if( is_string( $locale ) && preg_match( '/^[A-Za-z]{2,3}([-_][A-Za-z0-9]{2,8}){0,3}$/', $locale ) ) {
            \Aimeos\Cms\Theme::translations( $locale );
        }
    }


    /**
     * Returns the field schema signed and submitted with the form.
     *
     * @param mixed $types Map of field names to "textarea" or a list of select options
     * @param int $files Number of files visitors can attach
     */
    public static function schema( mixed $mandatory = null, mixed $optional = [], mixed $types = [], int $files = 0 ): string
    {
        $mandatory ??= self::DEFAULT_MANDATORY_FIELDS;
        $mandatory = self::values( $mandatory );
        $optional = self::values( $optional, $mandatory );
        $types = self::types( $types, [...$mandatory, ...$optional] );
        $files = max( 0, min( $files, self::MAX_FILES ) );

        return json_encode( compact( 'mandatory', 'optional' ) + ( $types ? ['types' => (object) $types] : [] ) + ( $files ? ['files' => $files] : [] ),
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
        );
    }


    public static function signature( string $schema ): string
    {
        return hash_hmac( 'sha256', $schema, (string) config( 'app.key' ) );
    }


    /**
     * Returns the verified field sets or NULL if the submitted schema is invalid.
     *
     * @return array{mandatory: array<int, string>, optional: array<int, string>, types: array<string, string|array<int, string>>, files: int}|null
     */
    private function sets(): ?array
    {
        if( !$this->has( 'schema' ) ) {
            return ['mandatory' => self::DEFAULT_MANDATORY_FIELDS, 'optional' => [], 'types' => [], 'files' => 0];
        }

        return $this->sets ??= ( function() : ?array {
            $schema = $this->input( 'schema' );
            $signature = $this->input( 'signature' );

            if( !is_string( $schema ) || !is_string( $signature )
                || !hash_equals( self::signature( $schema ), $signature )
            ) {
                return null;
            }

            $sets = json_decode( $schema, true );
            $files = $sets['files'] ?? 0;

            if( !is_array( $sets ) || !is_int( $files )
                || self::schema( $sets['mandatory'] ?? null, $sets['optional'] ?? null, $sets['types'] ?? [], $files ) !== $schema
            ) {
                return null;
            }

            return [
                'mandatory' => $sets['mandatory'],
                'optional' => $sets['optional'],
                'types' => self::types( $sets['types'] ?? [], [...$sets['mandatory'], ...$sets['optional']] ),
                'files' => $files,
            ];
        } )();
    }


    /**
     * Returns the input types of the custom fields in the order of the fields.
     *
     * @param array<int, string> $fields
     * @return array<string, string|array<int, string>>
     */
    private static function types( mixed $types, array $fields ): array
    {
        $result = [];

        if( !is_array( $types ) ) {
            return $result;
        }

        foreach( $fields as $field )
        {
            $type = $types[$field] ?? null;

            if( in_array( $field, self::STANDARD_FIELDS, true ) ) {
                continue;
            }

            if( $type === 'textarea' ) {
                $result[$field] = $type;
            } elseif( is_array( $type ) && array_is_list( $type ) && ( $options = self::values( $type ) ) ) {
                $result[$field] = $options;
            }
        }

        return $result;
    }


    /**
     * @param array<int, string> $exclude
     * @return array<int, string>
     */
    private static function values( mixed $fields, array $exclude = [] ): array
    {
        $result = [];

        if( is_array( $fields ) && array_is_list( $fields ) )
        {
            foreach( $fields as $field )
            {
                if( count( $result ) >= 20 || !is_string( $field )
                    || trim( $field ) === '' || !preg_match( '/^[^\pC]{1,64}$/u', $field )
                    || in_array( $field, $exclude, true ) || in_array( $field, $result, true )
                ) {
                    continue;
                }

                $result[] = $field;
            }
        }

        return $result;
    }
}
