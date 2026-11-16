<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Import\Joomla;


/**
 * Renders the texts of the Joomla custom field values as HTML by their field type like the layouts of the field plugins.
 */
class FieldTypes
{
    /** Field types with special values */
    protected const TYPE_URL = 'url';
    protected const TYPE_CALENDAR = 'calendar';
    protected const TYPE_INTEGER = 'integer';
    protected const TYPE_MEDIA = 'media';
    protected const TYPE_IMAGE_LIST = 'imagelist';

    /** Field types whose values are HTML */
    protected const HTML_TYPES = ['editor', 'textarea'];

    /** Field types whose values are images shown without separator */
    protected const IMAGE_TYPES = [self::TYPE_MEDIA, self::TYPE_IMAGE_LIST];

    /** Link text of URL fields to external sites if the URL isn't shown */
    protected const VISIT_WEBSITE = 'Visit Website';

    /** Link text of URL fields to local pages if the URL isn't shown */
    protected const VISIT_LINK = 'Visit Link';

    /** Directory of the Joomla media files the image list fields select files from */
    protected const IMAGES_DIR = 'images/';

    /** Values of image list fields which select no image */
    protected const NO_IMAGES = ['0', '-1'];

    /** Options of the field types */
    protected const OPTION_SHOW_URL = 'show_url';
    protected const OPTION_SHOW_TIME = 'showtime';
    protected const OPTION_DIRECTORY = 'directory';

    /** Regular expression matching the characters which aren't allowed in internal URLs */
    protected const UNSAFE_URL_PATTERN = '/["\' <>]/';

    /** Schemes of the absolute URLs which can be ones of the Joomla site */
    protected const WEB_SCHEMES = ['http', 'https'];


    /**
     * Initializes the object.
     *
     * @param Origin $origin Hosts and paths of the Joomla site from the base URL of the files and the domain, whose URLs are internal
     * @param \DateTimeZone $timezone Time zone of the Joomla site the dates are shown in
     * @param Version $version Major Joomla version, Joomla 3 always shows the URLs as link texts
     */
    public function __construct( protected Origin $origin, protected \DateTimeZone $timezone, protected Version $version )
    {
    }


    /**
     * Tests if the values of the field type are images, which are shown without separator.
     *
     * @param string $type Field type like "media" or "text"
     */
    public function images( string $type ): bool
    {
        return in_array( $type, self::IMAGE_TYPES, true );
    }


    /**
     * Returns a text of the field as HTML like the layouts of the Joomla field plugins render it.
     *
     * @param string $type Field type like "url" or "calendar"
     * @param string $value Text of a value of the field
     * @param array<array-key, mixed> $params Field parameters
     * @param FieldContext $context How the HTML of the field is prepared and the language
     * @return string HTML or an empty string if the value isn't supported
     */
    public function render( string $type, string $value, array $params, FieldContext $context ): string
    {
        return match( true ) {
            // Joomla doesn't escape the HTML of text areas and runs the content plugins for both
            in_array( $type, self::HTML_TYPES, true ) => $context->prepared( $value ),
            $type === self::TYPE_URL => $this->url( $value, $params, $context->locale ),
            $type === self::TYPE_CALENDAR => $this->date( $value, $params, $context->locale ),
            $type === self::TYPE_INTEGER => $this->integer( $value ),
            $type === self::TYPE_MEDIA => $this->media( $value ),
            $type === self::TYPE_IMAGE_LIST => $this->imageList( $value, $params ),
            default => e( $value ),
        };
    }


    /**
     * Returns the value of an integer field.
     *
     * Joomla stores the selected integer and shows its integer value, e.g. "12" for "12abc". Values not starting
     * with a number aren't integers, Joomla would show "0" for them, so nothing is shown instead.
     *
     * @param string $value Text of the value like "42"
     * @return string Integer like "42" or an empty string if the value doesn't start with an integer
     */
    protected function integer( string $value ): string
    {
        return preg_match( '/^\s*[+-]?\d/', $value ) ? (string) (int) $value : '';
    }


    /**
     * Returns the link of a URL field value.
     *
     * Joomla shows "Visit Website" for external and "Visit Link" for internal URLs unless the URL itself should be shown,
     * Joomla 3 always shows the URL.
     *
     * @param array<array-key, mixed> $params Field parameters
     * @param string|null $locale Language code the texts are translated to, NULL for the current locale
     */
    protected function url( string $value, array $params, ?string $locale ): string
    {
        $external = !$this->internal( $value );
        $visit = __( $external ? self::VISIT_WEBSITE : self::VISIT_LINK, [], $locale );
        $shown = $this->version->upTo( Version::JOOMLA_3 ) || !empty( $params[self::OPTION_SHOW_URL] );

        return Markup::link( $value, $shown ? $value : $visit, $external );
    }


    /**
     * Tests if the URL is one of the Joomla site.
     *
     * Like the links in the texts, relative URLs and absolute HTTP(S) URLs of the hosts of the base URL and the
     * domain are internal, also with other cases of the host or without scheme like "//example.com/page". The
     * hosts are compared as a whole, so "example.co" isn't internal for "example.com", and Joomla sites in
     * subdirectories only match URLs in their directory. Without known hosts, absolute URLs are external. URLs
     * with quotes, spaces or angle brackets and other schemes like "mailto:" are external too.
     *
     * @param string $url URL of a URL field
     */
    protected function internal( string $url ): bool
    {
        $url = str_replace( '\\', '/', $url );

        if( preg_match( self::UNSAFE_URL_PATTERN, $url ) || ( $parts = parse_url( $url ) ) === false ) {
            return false;
        }

        if( isset( $parts['scheme'] ) && !in_array( strtolower( $parts['scheme'] ), self::WEB_SCHEMES, true ) ) {
            return false;
        }

        if( !isset( $parts['host'] ) ) {
            return !isset( $parts['scheme'] );
        }

        return $this->origin->internal( $url );
    }


    /**
     * Returns the date of a calendar field value in the date format of the language, with time if configured.
     *
     * Joomla stores the dates in UTC and shows them in the time zone of the site. The formats are the British English
     * ones of Joomla and can be translated like the DATE_FORMAT_LC4 and DATE_FORMAT_LC5 strings of the Joomla languages.
     * Like the texts of Laravel's JSON translations, the formats themselves are the translation keys, so languages
     * without translation use the British English format. Empty dates of Joomla like "0000-00-00 00:00:00" show nothing.
     *
     * @param array<array-key, mixed> $params Field parameters
     * @param string|null $locale Language code the format is translated to, NULL for the current locale
     * @return string Formatted date, the escaped value if it's no date or an empty string for empty dates
     */
    protected function date( string $value, array $params, ?string $locale ): string
    {
        if( Date::first( $value ) === null ) {
            return '';
        }

        if( !( $date = Date::parse( $value ) ) ) {
            return e( $value );
        }

        $format = !empty( $params[self::OPTION_SHOW_TIME] ) ? Date::DATETIME_FORMAT : Date::DATE_FORMAT;
        return Date::format( $date, __( $format, [], $locale ), $this->timezone );
    }


    /**
     * Returns the image tag of a media field value.
     *
     * Joomla 4+ stores the path and the alternative text as JSON, Joomla 3 only the path.
     */
    protected function media( string $value ): string
    {
        $data = Value::decode( $value );
        // the suffix of the Joomla 4+ media field is removed when the image of the tag is imported
        $path = is_string( $data['imagefile'] ?? null ) ? $data['imagefile'] : ( $data ? '' : $value );

        if( $path === '' ) {
            return '';
        }

        $alt = is_string( $data['alt_text'] ?? null ) ? $data['alt_text'] : '';

        // like Joomla, the alternative text is only added if there's one or it's marked as empty
        return Markup::image( $path, $alt !== '' || !empty( $data['alt_empty'] ) ? $alt : null );
    }


    /**
     * Returns the image tag of an image list field value, which is a file in the configured directory below "images".
     *
     * @param array<array-key, mixed> $params Field parameters
     */
    protected function imageList( string $value, array $params ): string
    {
        if( in_array( $value, self::NO_IMAGES, true ) ) {
            return '';
        }

        $dir = trim( Value::string( $params[self::OPTION_DIRECTORY] ?? null ), '/' );
        $path = self::IMAGES_DIR . ( $dir !== '' ? $dir . '/' : '' ) . $value;

        return Markup::image( $path );
    }
}
