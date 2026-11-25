<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Import\Typo3;

use Aimeos\Cms\Import\Database;
use Aimeos\Cms\Import\Files as BaseFiles;
use Aimeos\Cms\DB;
use Aimeos\Cms\Models\File;
use Illuminate\Support\Collection;


/**
 * Resolves TYPO3 file references and fileadmin URLs to Pagible files.
 */
class Files
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
     * @param \Closure(string, string, string, bool, array<string, mixed>): string $create Creates a file from MIME type, name, path, TRUE if $known has just returned no file for the path
     *  and the options ("description" by language, "crop" area relative to the image size) and returns its ID
     * @param \Closure(string): ?string $url Returns the URL of the file created from the path
     * @param (\Closure(string): ?string)|null $known Returns the ID of the file created from the path before or NULL if there's none
     * @param array<int, string> $storages Base URLs of the TYPO3 file storages other than fileadmin without trailing slash by storage UID
     * @param array<int, string> $folders Base paths of the TYPO3 file storages other than fileadmin relative to the public directory by storage UID, e.g. "files"
     * @param (\Closure(string, ?int): ?\stdClass)|null $original Returns the sys_file record of the original file of a processed file by identifier and storage UID
     * @param (\Closure(int): string)|null $lang Returns the language code of the TYPO3 language UID of the file references
     */
    public function __construct( protected \Closure $files, protected string $base, protected \Closure $create, protected \Closure $url,
        protected ?\Closure $known = null, protected array $storages = [], protected array $folders = [], protected ?\Closure $original = null,
        protected ?\Closure $lang = null )
    {
    }


    /**
     * Returns the regular expression matching the paths of the URLs of the TYPO3 files, e.g. "/fileadmin/a.jpg" or "/uploads/pics/a.jpg".
     */
    public function pattern(): string
    {
        return '#(?:^|/)(?:' . $this->bases() . ')/.+#i';
    }


    /**
     * Returns the alternation of the quoted base folders of the TYPO3 files for regular expressions using "#" as delimiter.
     */
    protected function bases(): string
    {
        $folders = array_map( fn( $folder ) => trim( (string) $folder, '/' ), $this->folders );
        $bases = array_unique( ['fileadmin', 'uploads', ...array_values( array_filter( $folders ) )] );

        return implode( '|', array_map( fn( $base ) => preg_quote( $base, '#' ), $bases ) );
    }


    /**
     * Imports the TYPO3 file of the sys_file record, e.g. linked by "t3://file?uid=1".
     *
     * @return array{id: string, url: string}|null
     */
    public function file( object $sysFile ): ?array
    {
        $identifier = (string) ( $sysFile->identifier ?? '' );
        $path = $this->path( $identifier, (int) ( $sysFile->storage ?? 0 ) );
        $id = $this->known ? ( $this->known )( $path ) : '';

        if( !$id )
        {
            $extension = trim( (string) ( $sysFile->extension ?? '' ) ) ?: pathinfo( $identifier, PATHINFO_EXTENSION );
            $mime = $this->mime( (string) ( $sysFile->mime_type ?? '' ), (string) $extension );
            $id = ( $this->create )( $mime, (string) ( $sysFile->name ?? basename( $identifier ) ), $path, $id === null, [] );
        }

        $newUrl = ( $this->url )( $path );

        return $id !== '' && $newUrl ? ['id' => $id, 'url' => $newUrl] : null;
    }


    /**
     * Returns the source URL or path of the file of the TYPO3 file reference.
     *
     * @param object $ref sys_file_reference record with its sys_file record in the "sys_file" property
     */
    public function source( object $ref ): ?string
    {
        $sysFile = get_object_vars( $ref )['sys_file'] ?? null;
        return $sysFile instanceof \stdClass ? $this->path( (string) ( $sysFile->identifier ?? '' ), (int) ( $sysFile->storage ?? 0 ) ) : null;
    }


    /**
     * Returns the URL of the stored file used in the content.
     *
     * Local managed files must remain portable across hosts and preview ports, remote disks keep their absolute URL.
     */
    public static function stored( File $file ): string
    {
        return BaseFiles::stored( $file );
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

        ['identifier' => $identifier, 'decoded' => $decodedIdentifier, 'path' => $path, 'storage' => $storage] = $location;

        // processed files like resized images are replaced by their original files
        if( $this->original && preg_match( '#/_processed_/#', $decodedIdentifier ) && ( $sysFile = ( $this->original )( $decodedIdentifier, $storage ) ) ) {
            return $this->file( $sysFile );
        }

        // files used before don't need their sys_file record
        $id = $this->known ? ( $this->known )( $path ) : '';

        if( $id && ( $newUrl = ( $this->url )( $path ) ) ) {
            return ['id' => $id, 'url' => $newUrl];
        }

        $sysFile = $storage === 0 ? null : $this->sysFile( $identifier, $decodedIdentifier, $storage );
        $extension = trim( (string) ( $sysFile->extension ?? '' ) ) ?: pathinfo( $decodedIdentifier, PATHINFO_EXTENSION );
        $name = $sysFile->name ?? basename( $decodedIdentifier );
        $mime = $this->mime( (string) ( $sysFile->mime_type ?? '' ), (string) $extension );

        // files which aren't known aren't looked up again when they are created
        $id = ( $this->create )( $mime, (string) $name, $path, $id === null, [] );
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
                $path = $this->path( (string) ( $sysFile->identifier ?? '' ), (int) ( $sysFile->storage ?? 0 ) );
                $paths[] = $this->key( $path, $this->cropArea( $ref, $sysFile, $path ) );
            }
        }

        $identifiers = [];

        foreach( $texts as $text )
        {
            // all URLs are used, also the ones of srcset attributes
            preg_match_all( '#[^"\'\s>,=]*(?:' . $this->bases() . ')/[^"\'\s>,]+#i', (string) $text, $matches );

            foreach( $matches[0] as $url )
            {
                if( ( $location = $this->location( $url ) ) && $location['storage'] !== 0 && !str_contains( $location['decoded'], '/_processed_/' ) )
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
     * Returns the identifiers, the storage and the source path of the URL of a TYPO3 file.
     *
     * Files in "fileadmin/" and the base folders of the other storages are found by their sys_file record,
     * files in the legacy "uploads/" folder have no sys_file record and use the storage 0.
     *
     * @return array{identifier: string, decoded: string, path: string, storage: int|null}|null NULL if it's no URL of a TYPO3 file
     */
    protected function location( string $url ): ?array
    {
        $url = html_entity_decode( trim( $url ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
        $scheme = strtolower( (string) parse_url( $url, PHP_URL_SCHEME ) );

        if( $scheme !== '' && !in_array( $scheme, ['http', 'https'], true ) ) {
            return null;
        }

        $urlPath = parse_url( $url, PHP_URL_PATH );

        if( !is_string( $urlPath ) ) {
            return null;
        }

        $storage = null;

        if( preg_match( '#(?:^|/)fileadmin/(.+)$#i', $urlPath, $matches ) ) {
            $path = $this->path( $matches[1] );
            // the files of the fileadmin storage are preferred if other storages contain files with the same identifier
            $storage = ( $uid = array_search( 'fileadmin', array_map( fn( $folder ) => strtolower( trim( (string) $folder, '/' ) ), $this->folders ), true ) ) !== false ? (int) $uid : null;
        } elseif( $match = $this->folder( $urlPath ) ) {
            [$storage, $matches] = $match;
            $path = $this->path( $matches[1], $storage );
        } elseif( preg_match( '#(?:^|/)uploads/(.+)$#i', $urlPath, $matches ) ) {
            $storage = 0;
            // the uploads folder is next to the fileadmin folder, bases without path like "https://t3.org" are the site root
            $root = rtrim( $this->base, '/' );
            $root = preg_match( '#[^/]$#', (string) preg_replace( '#^[a-z][a-z0-9+.-]*://[^/]+#i', '', $root ) ) ? dirname( $root ) : $root;
            $path = ( $root !== '' ? $root . '/' : '' ) . 'uploads/' . ltrim( $matches[1], '/' );
        } else {
            return null;
        }

        $identifier = '/' . ltrim( $matches[1], '/' );

        if( $this->base === '' && in_array( $scheme, ['http', 'https'], true ) ) {
            $path = preg_replace( '/[?#].*$/', '', $url ) ?: $url;
        }

        return ['identifier' => $identifier, 'decoded' => '/' . ltrim( rawurldecode( $matches[1] ), '/' ), 'path' => $path, 'storage' => $storage];
    }


    /**
     * Returns the storage UID and the path within the storage if the URL path is in the base folder of a storage other than fileadmin.
     *
     * @return array{0: int, 1: array<int, string>}|null Storage UID and matches with the path in the storage as second entry or NULL if not found
     */
    protected function folder( string $urlPath ): ?array
    {
        foreach( $this->folders as $uid => $folder )
        {
            if( $folder !== '' && strtolower( $folder ) !== 'fileadmin' && preg_match( '#(?:^|/)' . preg_quote( trim( $folder, '/' ), '#' ) . '/(.+)$#i', $urlPath, $matches ) ) {
                return [(int) $uid, $matches];
            }
        }

        return null;
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

        // the alternative text and the caption are the description of the file
        $name = trim( (string) ( $reference['title'] ?? '' ) ) ?: (string) $sysFile->name;
        $text = trim( (string) ( $reference['alternative'] ?? '' ) ) ?: trim( strip_tags( (string) ( $reference['description'] ?? '' ) ) );
        $lang = $this->lang ? ( $this->lang )( (int) ( $reference['sys_language_uid'] ?? 0 ) ) : '';
        $mime = $this->mime( (string) ( $sysFile->mime_type ?? '' ), $this->extension( $sysFile ) );
        $source = $this->path( (string) $sysFile->identifier, (int) ( $sysFile->storage ?? 0 ) );
        $crop = $this->cropArea( $ref, $sysFile, $source );
        $path = $this->key( $source, $crop );

        return ( $this->create )( $mime, $name, $path, false, array_filter( [
            'description' => $text !== '' && $lang !== '' ? [$lang => $text] : null,
            'crop' => $crop,
        ] ) );
    }


    /**
     * Returns the area of the image shown by TYPO3 relative to the image size or NULL if the whole image is shown.
     *
     * TYPO3 8 and newer store the crop areas of the variants as JSON like
     * {"default":{"cropArea":{"x":0.1,"y":0,"width":0.8,"height":1}}}, the "default" variant is used.
     *
     * @param object $ref sys_file_reference record
     * @return array{x: float, y: float, width: float, height: float}|null Crop area or NULL if the image isn't cropped
     */
    public function crop( object $ref ): ?array
    {
        $config = json_decode( (string) ( get_object_vars( $ref )['crop'] ?? '' ), true );

        if( !is_array( $config ) ) {
            return null;
        }

        $variant = $config['default'] ?? reset( $config );
        $area = is_array( $variant ) ? $variant['cropArea'] ?? null : null;

        if( !is_array( $area ) ) {
            return null;
        }

        $values = [];

        foreach( ['x', 'y', 'width', 'height'] as $key )
        {
            if( !is_numeric( $area[$key] ?? null ) || ( $value = (float) $area[$key] ) < 0 || $value > 1 ) {
                return null;
            }

            $values[$key] = round( $value, 4 );
        }

        if( $values['width'] <= 0 || $values['height'] <= 0 || $values === ['x' => 0.0, 'y' => 0.0, 'width' => 1.0, 'height' => 1.0] ) {
            return null;
        }

        return $values;
    }


    /**
     * Returns the crop area of the file reference if the file is a raster image which is downloaded.
     *
     * Referenced files can't be cropped, the whole image is used.
     *
     * @return array{x: float, y: float, width: float, height: float}|null
     */
    protected function cropArea( object $ref, \stdClass $sysFile, string $path ): ?array
    {
        if( !str_starts_with( $path, 'http' ) ) {
            return null;
        }

        $mime = $this->mime( (string) ( $sysFile->mime_type ?? '' ), $this->extension( $sysFile ) );
        return str_starts_with( $mime, 'image/' ) && $mime !== 'image/svg+xml' ? $this->crop( $ref ) : null;
    }


    /**
     * Returns the file extension of the sys_file record.
     */
    protected function extension( \stdClass $sysFile ): string
    {
        return trim( (string) ( $sysFile->extension ?? '' ) ) ?: pathinfo( (string) ( $sysFile->identifier ?? '' ), PATHINFO_EXTENSION );
    }


    /**
     * Returns the key of the file for the path and crop area, cropped images are separate files.
     *
     * @param array{x: float, y: float, width: float, height: float}|null $crop Crop area relative to the image size
     */
    public static function key( string $path, ?array $crop ): string
    {
        return $crop ? $path . '#crop=' . implode( ',', $crop ) : $path;
    }


    /**
     * Returns the sys_file record of the identifier or the decoded identifier.
     *
     * The records are cached because the same files are often linked several times.
     */
    protected function sysFile( string $identifier, string $decoded, ?int $storage = null ): ?\stdClass
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

            // the records by "<storage>:<identifier>" are used if the storage of the URL is known
            foreach( $files as $key => $file )
            {
                if( !str_starts_with( (string) $key, '/' ) ) {
                    $this->identifiers[$key] = $file;
                }
            }
        }

        $candidates = array_filter( [
            $storage !== null ? $this->identifiers[$storage . ':' . $identifier] ?? null : null,
            $storage !== null ? $this->identifiers[$storage . ':' . $decoded] ?? null : null,
            $this->identifiers[$identifier] ?? null,
            $this->identifiers[$decoded] ?? null,
        ], fn( $file ) => $file instanceof \stdClass && ( $storage === null || !isset( $file->storage ) || (int) $file->storage === $storage ) );

        $file = reset( $candidates ) ?: null;
        $this->identifiers = Database::limit( $this->identifiers, static::CACHE_SIZE, count( $missing ) );

        return $file;
    }


    /**
     * Corrects generic TYPO3 MIME values when the file extension is definitive.
     */
    protected function mime( string $mime, string $extension ): string
    {
        $mime = strtolower( trim( $mime ) );
        $guessed = BaseFiles::mime( 'file.' . $extension );

        if( $mime === '' || $mime === 'application/octet-stream' || ( $mime === 'text/plain' && $guessed === 'image/svg+xml' ) ) {
            return $guessed;
        }

        return $mime;
    }


    /**
     * Resolves a TYPO3 file identifier to a full URL or path.
     *
     * Files of other storages than fileadmin use the base URL of their storage.
     *
     * @param int|null $storage UID of the TYPO3 file storage
     */
    protected function path( string $identifier, ?int $storage = null ): string
    {
        $identifier = ltrim( $identifier, '/' );
        $base = $this->storages[(int) $storage] ?? $this->base;

        return $base ? $base . '/' . $identifier : $identifier;
    }
}
