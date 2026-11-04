<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Import;


/**
 * Converts the HTML of the Drupal site to content elements per language and tracks the removed HTML tags.
 */
class DrupalConverters
{
    /** @var array<string, DrupalHtml> Converters of the HTML of the text fields by Drupal language code */
    protected array $html = [];

    /** @var array<string, bool> HTML tags removed from the converted content since they have been returned */
    protected array $removed = [];


    /**
     * Initializes the object.
     *
     * @param DrupalFiles $files Imports the files of the Drupal site
     * @param \Closure(string, string): ?array{elements: array<int, array<string, mixed>>, fileIds?: array<int, string>} $embed Returns the content of the embedded media or entity tag by language code
     */
    public function __construct( protected DrupalFiles $files, protected \Closure $embed )
    {
    }


    /**
     * Returns the HTML converter which imports the images, embedded entities and files of the Drupal site.
     *
     * @param string $langcode Drupal language code of the converted content, used for the embedded entities
     */
    public function html( string $langcode ): DrupalHtml
    {
        return $this->html[$langcode] ??= new DrupalHtml( fn( string $tag ) => $this->files->image( $tag ), fn( string $tag ) => ( $this->embed )( $tag, $langcode ),
            fn( string $url, string $text ) => $this->files->linked( $url, $text ), fn( string $type, string $src ) => $this->files->player( $type, $src ) );
    }


    /**
     * Returns the HTML tags removed from the converted content and forgets them.
     *
     * @return array<string, bool> Lower case tag names as keys
     */
    public function removed(): array
    {
        foreach( $this->html as $html ) {
            $this->removed += $html->removed();
        }

        $removed = $this->removed;
        $this->removed = [];

        return $removed;
    }


    /**
     * Remembers the markup of the HTML which is removed by the HTML sanitizer.
     *
     * Iframes, videos and audios aren't tracked because they become separate elements.
     */
    public function track( string $html ): void
    {
        if( preg_match_all( '/<(script|object|embed|form|applet)\b/i', $html, $matches ) ) {
            $this->removed += array_fill_keys( array_map( 'strtolower', $matches[1] ), true );
        }
    }
}
