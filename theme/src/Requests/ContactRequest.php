<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Requests;

use Illuminate\Foundation\Http\FormRequest;


class ContactRequest extends FormRequest
{
    private const DEFAULT_MANDATORY_FIELDS = ['name', 'email'];
    private const STANDARD_FIELDS = ['name', 'company', 'telephone', 'email', 'subject'];

    /** @var array{mandatory: array<int, string>, optional: array<int, string>}|null */
    private ?array $sets = null;


    /** @return array<string, string> */
    public function attributes(): array
    {
        $attributes = ['message' => __( 'Message' ), 'source' => __( 'Source page' )];

        foreach( [...$this->mandatory(), ...$this->optional()] as $field ) {
            $attributes[self::key( $field )] = __( $field === 'email' ? 'E-Mail' : \Illuminate\Support\Str::headline( $field ) );
        }

        return $attributes;
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

        return [
            'required' => __( ':attribute: This field is required.' ),
            'email' => __( ':attribute: Please enter a valid e-mail address.' ),
            'max' => __( ':attribute: The text is too long.' ),
            'required_with' => $invalid,
            'string' => $invalid,
            'size' => $invalid,
            'url' => $invalid,
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

        foreach( [...$mandatory, ...$this->optional()] as $field )
        {
            $rule = in_array( $field, $mandatory, true ) ? 'required' : 'nullable';
            $rules[self::key( $field )] = $field === 'email'
                ? [$rule, 'email:rfc,dns', 'max:254']
                : [$rule, 'string', 'max:255'];
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


    public static function schema( mixed $mandatory = null, mixed $optional = [] ): string
    {
        $mandatory ??= self::DEFAULT_MANDATORY_FIELDS;
        $mandatory = self::values( $mandatory );
        $optional = self::values( $optional, $mandatory );

        return json_encode( compact( 'mandatory', 'optional' ),
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
     * @return array{mandatory: array<int, string>, optional: array<int, string>}|null
     */
    private function sets(): ?array
    {
        if( !$this->has( 'schema' ) ) {
            return ['mandatory' => self::DEFAULT_MANDATORY_FIELDS, 'optional' => []];
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

            if( !is_array( $sets )
                || self::schema( $sets['mandatory'] ?? null, $sets['optional'] ?? null ) !== $schema
            ) {
                return null;
            }

            return ['mandatory' => $sets['mandatory'], 'optional' => $sets['optional']];
        } )();
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
