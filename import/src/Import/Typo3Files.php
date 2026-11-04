<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Import;

use Aimeos\Cms\DB;
use Aimeos\Cms\FileStorage;
use Aimeos\Cms\Models\File;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;


/**
 * Resolves TYPO3 file references and fileadmin URLs to Pagible files.
 */
class Typo3Files
{
    /** Maximum number of cached sys_file records */
    protected const CACHE_SIZE = 2000;

    /** @var array<string, mixed> TYPO3 sys_file records or NULL by identifier with leading slash */
    protected array $identifiers = [];
    /** @var array<int, string> Identifiers of the URLs found by paths() whose records are fetched together when the first one is needed */
    protected array $pending = [];


    /**
     * @param \Closure(array<int, string>): Collection<string, mixed> $files Fetches the TYPO3 sys_file records by identifiers with leading slash
     * @param string $base Base URL of the TYPO3 files without trailing slash
     * @param \Closure(string, string, string, bool): string $create Creates a file from MIME type, name, path and TRUE if $known has just returned no file for the path and returns its ID
     * @param \Closure(string): ?string $url Returns the URL of the file created from the path
     * @param (\Closure(string): ?string)|null $known Returns the ID of the file created from the path before or NULL if there's none
     */
    public function __construct( protected \Closure $files, protected string $base, protected \Closure $create, protected \Closure $url,
        protected ?\Closure $known = null )
    {
    }


    /**
     * Returns the URL of the stored file used in the content.
     *
     * Local managed files must remain portable across hosts and preview ports, remote disks keep their absolute URL.
     */
    public static function stored( File $file ): string
    {
        $disk = FileStorage::diskName( (string) $file->disk );
        $url = Storage::disk( $disk )->url( (string) $file->path );

        if( config( 'filesystems.disks.' . $disk . '.driver' ) === 'local' ) {
            $url = parse_url( $url, PHP_URL_PATH ) ?: $url;
        }

        return $url;
    }


    /**
     * Imports one fileadmin URL found in HTML.
     *
     * @return array{id: string, url: string}|null
     */
    public function html( string $url ): ?array
    {
        if( !( $location = $this->location( $url ) ) ) {
            return null;
        }

        ['identifier' => $identifier, 'decoded' => $decodedIdentifier, 'path' => $path] = $location;

        // files used before don't need their sys_file record
        $id = $this->known ? ( $this->known )( $path ) : '';

        if( $id && ( $newUrl = ( $this->url )( $path ) ) ) {
            return ['id' => $id, 'url' => $newUrl];
        }

        $sysFile = $this->sysFile( $identifier, $decodedIdentifier );
        $extension = trim( (string) ( $sysFile->extension ?? '' ) ) ?: pathinfo( $decodedIdentifier, PATHINFO_EXTENSION );
        $name = $sysFile->name ?? basename( $decodedIdentifier );
        $mime = $this->mime( (string) ( $sysFile->mime_type ?? '' ), (string) $extension );

        // files which aren't known aren't looked up again when they are created
        $id = ( $this->create )( $mime, (string) $name, $path, $id === null );
        $newUrl = ( $this->url )( $path );

        return $id !== '' && $newUrl ? ['id' => $id, 'url' => $newUrl] : null;
    }


    /**
     * Returns the source paths of the files used by the file references and the fileadmin URLs in the HTML texts.
     *
     * URLs in unusual HTML may be missed, they are looked up when they are imported.
     *
     * @param iterable<object> $refs sys_file_reference records with their sys_file record in the "sys_file" property
     * @param iterable<mixed> $texts HTML texts of the content elements
     * @return array<int, string> Unique source URLs or paths of the files
     */
    public function paths( iterable $refs, iterable $texts = [] ): array
    {
        $paths = [];

        foreach( $refs as $ref )
        {
            if( ( $sysFile = get_object_vars( $ref )['sys_file'] ?? null ) instanceof \stdClass ) {
                $paths[] = $this->path( (string) ( $sysFile->identifier ?? '' ) );
            }
        }

        $identifiers = [];

        foreach( $texts as $text )
        {
            // all URLs are used, also the ones of srcset attributes
            preg_match_all( '#[^"\'\s>,=]*fileadmin/[^"\'\s>,]+#i', (string) $text, $matches );

            foreach( $matches[0] as $url )
            {
                if( $location = $this->location( $url ) )
                {
                    $paths[] = $location['path'];
                    $identifiers[$location['identifier']] = $identifiers[$location['decoded']] = true;
                }
            }
        }

        // the sys_file records of the URLs in the HTML are fetched at once by html() when the first one is needed
        $this->pending = array_keys( $identifiers );

        return array_values( array_unique( $paths ) );
    }


    /**
     * Returns the identifiers and the source path of a fileadmin URL.
     *
     * @return array{identifier: string, decoded: string, path: string}|null NULL if it's no fileadmin URL
     */
    protected function location( string $url ): ?array
    {
        $url = html_entity_decode( trim( $url ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
        $scheme = strtolower( (string) parse_url( $url, PHP_URL_SCHEME ) );

        if( $scheme !== '' && !in_array( $scheme, ['http', 'https'], true ) ) {
            return null;
        }

        $urlPath = parse_url( $url, PHP_URL_PATH );

        if( !is_string( $urlPath ) || !preg_match( '#(?:^|/)fileadmin/(.+)$#i', $urlPath, $matches ) ) {
            return null;
        }

        $identifier = '/' . ltrim( $matches[1], '/' );
        $path = $this->path( ltrim( $identifier, '/' ) );

        if( $this->base === '' && in_array( $scheme, ['http', 'https'], true ) ) {
            $path = preg_replace( '/[?#].*$/', '', $url ) ?: $url;
        }

        return ['identifier' => $identifier, 'decoded' => '/' . ltrim( rawurldecode( $matches[1] ), '/' ), 'path' => $path];
    }


    /**
     * Imports one TYPO3 file reference.
     *
     * @param object $ref sys_file_reference record with its sys_file record in the "sys_file" property
     */
    public function reference( object $ref ): ?string
    {
        $reference = get_object_vars( $ref );

        if( !( ( $sysFile = $reference['sys_file'] ?? null ) instanceof \stdClass ) ) {
            return null;
        }

        $name = ( $reference['title'] ?? '' ) ?: ( $reference['alternative'] ?? '' ) ?: $sysFile->name;
        $extension = trim( (string) ( $sysFile->extension ?? '' ) ) ?: pathinfo( (string) $sysFile->identifier, PATHINFO_EXTENSION );
        $mime = $this->mime( (string) $sysFile->mime_type, $extension );

        return ( $this->create )( $mime, $name, $this->path( $sysFile->identifier ), false );
    }


    /**
     * Returns the sys_file record of the identifier or the decoded identifier.
     *
     * The records are cached because the same files are often linked several times.
     */
    protected function sysFile( string $identifier, string $decoded ): ?\stdClass
    {
        // the records of the other URLs found by paths() are fetched together, files created before don't need them
        $missing = array_values( array_filter( array_unique( [$identifier, $decoded, ...$this->pending] ), fn( $id ) => !array_key_exists( $id, $this->identifiers ) ) );
        $this->pending = [];

        foreach( array_chunk( $missing, DB::IN_SIZE ) as $chunk )
        {
            $files = ( $this->files )( $chunk );

            foreach( $chunk as $id ) {
                $this->identifiers[$id] = $files->get( $id );
            }
        }

        $file = $this->identifiers[$identifier] ?? $this->identifiers[$decoded];
        $this->identifiers = Database::limit( $this->identifiers, static::CACHE_SIZE, count( $missing ) );

        return $file instanceof \stdClass ? $file : null;
    }


    /**
     * Corrects generic TYPO3 MIME values when the file extension is definitive.
     */
    protected function mime( string $mime, string $extension ): string
    {
        $mime = strtolower( trim( $mime ) );
        $guessed = Files::mime( 'file.' . $extension );

        if( $mime === '' || $mime === 'application/octet-stream' || ( $mime === 'text/plain' && $guessed === 'image/svg+xml' ) ) {
            return $guessed;
        }

        return $mime;
    }


    /**
     * Resolves a TYPO3 file identifier to a full URL or path.
     */
    protected function path( string $identifier ): string
    {
        $identifier = ltrim( $identifier, '/' );
        return $this->base ? $this->base . '/' . $identifier : $identifier;
    }
}
