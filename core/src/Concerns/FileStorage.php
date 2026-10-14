<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Concerns;

use Aimeos\Cms\Events\FilesRemoved;
use Aimeos\Cms\Files;
use Aimeos\Cms\Uploads;
use Aimeos\Cms\Jobs\DeleteFilePaths;
use Aimeos\Cms\Models\File;
use Aimeos\Cms\Models\Version;
use Aimeos\Cms\SafeHttp;
use Aimeos\Cms\Utils;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Intervention\Image\Interfaces\DriverInterface;
use Psr\Http\Message\StreamInterface;
use Aimeos\Cms\DB;
use Aimeos\Cms\Exception;
use Aimeos\Cms\InvalidException;
use Illuminate\Contracts\Filesystem\Filesystem;
use Aimeos\Cms\Tenancy;


/**
 * Ingests, relocates and removes the stored files of file items.
 *
 * @phpstan-require-extends \Aimeos\Cms\Models\File
 */
trait FileStorage
{
    /**
     * Adds the uploaded file to the storage and returns the path to it
     *
     * @param UploadedFile $upload File upload
     * @return self The current instance for method chaining
     */
    public function addFile( UploadedFile $upload ) : self
    {
        $this->path = null;

        $disk = $this->storage();
        $dir = $this->dir();

        $name = $this->filename( $upload->getClientOriginalName(), $upload->guessExtension() );
        $path = $dir . '/' . $name;

        if( $upload->getMimeType() === 'image/svg+xml' )
        {
            $content = file_get_contents( $upload->getRealPath() );

            if( !( $content = Uploads::cleanSvg( $content ) ) ) {
                $msg = 'Invalid file "%s"';
                throw new InvalidException( sprintf( $msg, $upload->getClientOriginalName() ) );
            }

            $stored = $disk->put( $path, $content );
        }
        else
        {
            $stored = $disk->putFileAs( $dir, $upload, $name );
        }

        if( !$stored ) {
            $msg = 'Unable to store file "%s" to "%s"';
            throw new Exception( sprintf( $msg, $upload->getClientOriginalName(), $path ) );
        }

        $this->path = $path;
        return $this;
    }


    /**
     * Fetches a URL as a bounded temporary stream.
     *
     * @param string $url URL to fetch
     * @param DriverInterface|null $driver Image driver for an optional format support check
     * @return resource|null Seekable tmpfile resource or null if not an image
     */
    public function fetchUrl( string $url, ?DriverInterface $driver = null )
    {
        $response = SafeHttp::request( $url, ['stream' => true], ['User-Agent' => 'Pagible/1.0 (+https://pagible.com)'] );

        if( !$response->successful() ) {
            throw new InvalidException( sprintf( 'Failed to download "%s"', $url ) );
        }

        $limit = Uploads::maxMegabytes();
        $max = Uploads::maxSize();
        $body = $response->toPsrResponse()->getBody();
        $length = trim( $response->header( 'Content-Length' ) );
        $message = $driver
            ? sprintf( 'Remote file exceeds the maximum size of %s MB', $limit )
            : 'Remote file exceeds the maximum upload size';

        try
        {
            if( $length !== '' && ctype_digit( $length ) && (int) $length > $max ) {
                throw new InvalidException( $message );
            }

            $bytes = $body->read( min( 4096, $max + 1 ) );

            if( strlen( $bytes ) > $max ) {
                throw new InvalidException( $message );
            }

            $this->mime = ( new \finfo( FILEINFO_MIME_TYPE ) )->buffer( $bytes ) ?: 'application/octet-stream';

            // SVG (incl. gzip-compressed SVGZ) isn't supported by the image drivers but is stored as preview itself
            if( $driver && !in_array( $this->mime, ['image/svg+xml', 'application/gzip'] )
                && !$driver->supports( $this->mime ) )
            {
                return null;
            }

            return self::spool( $body, $bytes, $max, $message );
        }
        finally
        {
            $body->close();
        }
    }


    /**
     * Copies the response body into a temporary file.
     *
     * @param StreamInterface $body Response body after the already read bytes
     * @param string $bytes Already read bytes of the body
     * @param int $max Maximum size in bytes
     * @param string $message Error message if the body exceeds the maximum size
     * @return resource Temporary file positioned at the beginning
     * @throws InvalidException If the body exceeds the maximum size
     */
    protected static function spool( StreamInterface $body, string $bytes, int $max, string $message )
    {
        if( !( $tmp = tmpfile() ) ) {
            throw new Exception( 'Unable to create temporary file' );
        }

        fwrite( $tmp, $bytes );
        $size = strlen( $bytes );

        while( !$body->eof() )
        {
            $chunk = $body->read( min( 1048576, $max - $size + 1 ) );
            $size += strlen( $chunk );

            if( $size > $max ) {
                fclose( $tmp );
                throw new InvalidException( $message );
            }

            fwrite( $tmp, $chunk );
        }

        fseek( $tmp, 0 );
        return $tmp;
    }


    /**
     * Removes the stored files of a failed ingest, reporting errors instead of throwing them.
     *
     * @param bool $file TRUE to remove the stored file too, FALSE for the previews only
     */
    protected function discard( bool $file ) : void
    {
        try {
            $this->removePreviews();

            if( $file ) {
                $this->removeFile();
            }
        } catch( \Throwable $t ) {
            report( $t );
        }
    }


    /**
     * Returns the downloaded file as uploaded file.
     *
     * @param resource|false|null $resource Temporary file containing the download
     * @param string $url URL of the downloaded file
     * @return UploadedFile Uploaded file using the temporary file
     * @throws Exception If the temporary file isn't available
     */
    protected static function downloaded( mixed $resource, string $url ) : UploadedFile
    {
        $path = is_resource( $resource ) ? stream_get_meta_data( $resource )['uri'] ?? null : null;

        if( !is_string( $path ) ) {
            throw new Exception( 'Unable to create temporary file' );
        }

        $name = basename( (string) parse_url( $url, PHP_URL_PATH ) ) ?: 'file';
        $upload = new UploadedFile( $path, $name, null, null, true );
        Uploads::checkUpload( $upload );

        return $upload;
    }


    /**
     * Validates and ingests a new primary file or preview outside the database transaction.
     *
     * Private remote URLs are downloaded into UUID-owned storage. Failed ingestion removes generated previews and any
     * uploaded primary path before rethrowing the original error.
     *
     * @param UploadedFile|string|null $source Uploaded primary file or local/remote path
     * @param UploadedFile|null $preview Uploaded preview or null for automatic previews
     * @return self The ingested file
     * @throws Exception If the source, type, size, storage disk, or generated preview is invalid
     */
    public function ingest( UploadedFile|string|null $source = null, ?UploadedFile $preview = null ) : self
    {
        if( $source instanceof UploadedFile ) {
            Uploads::checkUpload( $source );
        } elseif( is_string( $source ) && str_starts_with( $source, 'http' ) && !Utils::isValidUrl( $source ) ) {
            throw new InvalidException( sprintf( 'Invalid URL "%s"', $source ) );
        }

        if( $preview ) {
            Uploads::checkUpload( $preview, preview: true );
        }

        $resource = null;
        $started = false;

        try
        {
            // private files must not be publicly accessible by their URL, so they are downloaded
            if( is_string( $source ) && str_starts_with( $source, 'http' ) && $this->getAttribute( 'disk' ) === 'private' ) {
                $source = self::downloaded( $resource = $this->fetchUrl( $source ), $source );
            }

            $started = true;

            if( $source instanceof UploadedFile ) {
                $this->ingestUpload( $source, $preview );
            } elseif( is_string( $source ) ) {
                $this->ingestPath( $source, $preview );
            } elseif( $preview ) {
                $this->addPreviews( $preview );
            }

            if( $source !== null ) {
                Uploads::checkMimetype( (string) $this->mime );
            }

            return $this;
        }
        catch( \Throwable $t )
        {
            if( $started ) {
                $this->discard( $source instanceof UploadedFile );
            }

            throw $t;
        }
        finally
        {
            if( is_resource( $resource ) ) {
                fclose( $resource );
            }
        }
    }


    /**
     * Assigns a public URL or existing managed path and creates requested previews.
     *
     * @param string $source Public URL or managed storage path
     * @param UploadedFile|null $preview Explicit preview upload, or null for remote automatic previews
     */
    protected function ingestPath( string $source, ?UploadedFile $preview ) : void
    {
        $this->path = $source;
        $this->name = $this->name ?: ( str_starts_with( $source, 'http' )
            ? substr( $source, 0, 255 )
            : pathinfo( $source, PATHINFO_BASENAME ) );

        if( $preview ) {
            $this->addPreviews( $preview );
        } elseif( str_starts_with( $source, 'http' ) ) {
            $this->addPreviews( $source );
        }

        $this->mime = $this->mime ?: Uploads::mimetype( $source, (string) $this->disk );
    }


    /**
     * Stores an uploaded file and creates its previews.
     *
     * @param UploadedFile $source Validated primary upload
     * @param UploadedFile|null $preview Explicit preview upload, or null to derive previews from images
     */
    protected function ingestUpload( UploadedFile $source, ?UploadedFile $preview ) : void
    {
        $this->addFile( $source );
        $this->mime = Uploads::mimetype( (string) $this->path, (string) $this->disk );
        $this->name = $this->name ?: pathinfo( $source->getClientOriginalName(), PATHINFO_BASENAME );

        if( $preview || str_starts_with( (string) $source->getMimeType(), 'image/' ) ) {
            $this->addPreviews( $preview ?? $source );
        }
    }


    /**
     * Returns the new name for the uploaded file
     *
     * @param string $filename Name of the file
     * @param string|null $ext File extension to use, if not given, the original file extension is used
     * @param string $size Names of the preview sizes, if used
     * @return string New file name
     */
    protected function filename( string $filename, ?string $ext = null, string $size = '' ) : string
    {
        $regex = '/([[:cntrl:]]|[[:blank:]]|\/|\.)+/smu';

        $ext = Uploads::extension( $ext ?: pathinfo( $filename, PATHINFO_EXTENSION ) );
        $name = preg_replace( $regex, '', pathinfo( $filename, PATHINFO_FILENAME ) );

        $hash = strtr( base64_encode( random_bytes( 3 ) ), '+/', '-_' );

        return $name . '_' . $size . '_' . $hash . '.' . $ext;
    }


    /**
     * Moves the current and historical managed paths to another logical disk.
     *
     * The caller must hold the tenant storage lock.
     *
     * @param string $disk Target logical disk, either "public" or "private"
     * @param string $editor Name of the editor relocating the file
     * @return self The current instance for method chaining
     * @throws Exception If a path is remote, foreign, missing or can't be copied and verified
     */
    public function relocate( string $disk, string $editor ) : self
    {
        $paths = $this->relocatable( $disk );
        $source = $this->storage();
        $target = Files::disk( $disk );

        foreach( $paths as $path )
        {
            if( $source->exists( $path ) ) {
                self::transfer( $source, $target, $path );
            } elseif( !$target->exists( $path ) ) {
                throw new Exception( sprintf( 'File "%s" is missing from both storage disks', $path ) );
            }
        }

        if( !$paths->isEmpty() ) {
            $source->delete( $paths->all() );
        }

        foreach( $paths as $path ) {
            if( $source->exists( $path ) ) {
                throw new Exception( sprintf( 'Unable to remove file "%s" from its previous disk', $path ) );
            }
        }

        DB::transaction( function() use ( $disk, $editor ) {
            $locked = $this->newQuery()->lockForUpdate()->findOrFail( $this->id );
            $locked->disk = $disk;
            $locked->editor = $editor;
            self::withoutSyncingToSearch( fn() => $locked->save() );
        } );

        $this->disk = $disk;
        $this->editor = $editor;

        if( $disk === 'private' && !$paths->isEmpty() ) {
            FilesRemoved::dispatch( (string) $this->tenant_id, array_values( $paths->all() ) );
        }

        return $this;
    }


    /**
     * Copies the file to the target storage and verifies the copy.
     *
     * @param Filesystem $source Storage containing the file
     * @param Filesystem $target Storage the file is copied to
     * @param string $path Path of the file in both storages
     * @throws Exception If the file can't be copied
     */
    protected static function transfer( Filesystem $source, Filesystem $target, string $path ) : void
    {
        $size = $source->size( $path );

        if( !( $stream = $source->readStream( $path ) ) ) {
            throw new Exception( sprintf( 'Unable to read file "%s"', $path ) );
        }

        try {
            if( !$target->writeStream( $path, $stream ) ) {
                throw new Exception( sprintf( 'Unable to store file "%s"', $path ) );
            }
        } finally {
            if( is_resource( $stream ) ) {
                fclose( $stream );
            }
        }

        if( !$target->exists( $path ) || $size !== $target->size( $path ) ) {
            throw new Exception( sprintf( 'Unable to verify file "%s"', $path ) );
        }
    }


    /**
     * Returns the unique current and historical managed paths which can be relocated.
     *
     * @param string $disk Target logical disk
     * @return Collection<int, non-empty-string> Local storage paths
     * @throws Exception If a path is remote where unsupported or does not belong to the file UUID
     */
    protected function relocatable( string $disk ) : Collection
    {
        $paths = self::paths( collect( [$this] )->concat( Version::where( 'versionable_id', $this->id )
            ->where( 'versionable_type', self::class )
            ->select( 'data' )->cursor()->pluck( 'data' ) ) );

        if( $disk === 'private' && $paths->contains( fn( $path ) => str_starts_with( $path, 'http' ) ) ) {
            throw new Exception( 'Remote files cannot be relocated' );
        }

        $paths = $paths->reject( fn( $path ) => str_starts_with( $path, 'http' ) )->values();
        $this->checkPaths( $paths->all() );

        return $paths;
    }


    /**
     * Removes the file from the storage
     *
     * @return self The current instance for method chaining
     */
    public function removeFile() : self
    {
        if( $this->path && !str_starts_with( $this->path, 'http' ) ) {
            $this->erase( [$this->path] );
        }

        $this->path = null;
        return $this;
    }


    /**
     * Ensures newly assigned managed paths belong to the UUID directory of this file.
     *
     * @param array<array-key, mixed> $paths Storage paths or remote URLs
     * @throws Exception If a path is outside the UUID directory or a private file uses a remote path
     */
    public function checkPaths( array $paths ) : void
    {
        $tenant = Tenancy::value();

        foreach( $paths as $path )
        {
            if( $path === null || $path === '' ) {
                continue;
            }

            if( is_string( $path ) && str_starts_with( $path, 'http' ) )
            {
                if( $this->getAttribute( 'disk' ) === 'private' ) {
                    throw new Exception( 'Private files cannot use remote paths' );
                }

                continue;
            }

            if( !Files::owns( $tenant, (string) $this->id, $path ) ) {
                throw new Exception( sprintf( 'File path "%s" is outside its UUID directory', (string) $path ) );
            }
        }
    }


    /**
     * Ensures prepared local files still exist on the disk of this file while the ownership lock is held.
     *
     * @param array<array-key, mixed> $paths Prepared storage paths or remote URLs
     * @throws Exception If a path is invalid or not available on the storage disk
     */
    public function checkStored( array $paths ) : void
    {
        $storage = $this->storage();

        foreach( $paths as $path )
        {
            if( $path === null ) {
                continue;
            }

            $value = Files::checkPath( (string) $path );

            if( !str_starts_with( $value, 'http' ) && !$storage->exists( $value ) ) {
                throw new Exception( sprintf( 'Prepared file "%s" is not available', (string) $path ) );
            }
        }
    }


    /**
     * Returns the UUID-owned storage directory for this File.
     */
    public function dir() : string
    {
        $this->setUniqueIds();
        return Files::prefix( Tenancy::value() ) . (string) $this->getAttribute( 'id' );
    }


    /**
     * Returns the storage disk of the file.
     */
    protected function storage() : Filesystem
    {
        return Files::disk( (string) $this->disk );
    }


    /**
     * Deletes the given storage paths of this file immediately.
     *
     * @param array<int, string> $paths Storage paths
     */
    private function erase( array $paths ) : void
    {
        ( new DeleteFilePaths( $this->exists ? (string) $this->tenant_id : Tenancy::value(), $paths ) )->handle();
    }
}
