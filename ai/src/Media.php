<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms;

use Aimeos\Cms\Exceptions\PermissionException;
use Aimeos\Cms\Models\File;
use Aimeos\Prisma\Files\Audio;
use Aimeos\Prisma\Files\File as PrismaFile;
use Aimeos\Prisma\Files\Image;
use Aimeos\Prisma\Files\Video;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Mime\MimeTypes;


/**
 * Converts between CMS files and the media files of the AI services.
 */
final class Media
{
    /**
     * Prisma file classes by media type.
     */
    private const MEDIA = [
        'audio' => Audio::class,
        'image' => Image::class,
        'video' => Video::class,
    ];

    /**
     * Image formats supported by the AI providers.
     */
    private const IMAGES = ['image/jpeg', 'image/png', 'image/webp'];

    /**
     * Columns required to load the file content.
     */
    private const COLUMNS = ['id', 'tenant_id', 'latest_id', 'disk', 'path', 'mime'];


    /**
     * Checks if files of the MIME type can be described.
     *
     * @param string $mime MIME type, e.g. "image/png" or "audio/" if unknown
     * @return bool TRUE for audio, video and supported images
     */
    public static function describable( string $mime ) : bool
    {
        return isset( self::MEDIA[explode( '/', $mime, 2 )[0]] ) && self::accepts( $mime, PrismaFile::class );
    }


    /**
     * Loads a stored file as Prisma file object.
     *
     * @template T of PrismaFile
     * @param string $id File UUID
     * @param Authenticatable|null $user User or NULL inside Permission::system()
     * @param class-string<T> $class Prisma file class, PrismaFile::class for all media types
     * @return T Prisma file object
     * @throws PermissionException If the user can't view files
     * @throws Exception If the file is missing or not supported
     */
    public static function file( string $id, ?Authenticatable $user, string $class = Image::class ) : PrismaFile
    {
        return self::load( [$id], $user, $class )[0];
    }


    /**
     * Loads stored files as Prisma file objects, together limited by "cms.ai.maxmedia".
     *
     * @template T of PrismaFile
     * @param array<int, string> $ids File UUIDs
     * @param Authenticatable|null $user User or NULL inside Permission::system()
     * @param class-string<T> $class Prisma file class, PrismaFile::class for all media types
     * @return array<int, T> Prisma file objects in the order of the IDs
     * @throws PermissionException If the user can't view files
     * @throws Exception If a file is missing or not supported or the files are too large
     */
    public static function load( array $ids, ?Authenticatable $user, string $class = Image::class ) : array
    {
        $ids = array_values( array_unique( $ids ) );

        if( !$ids ) {
            return [];
        }

        Permission::check( 'file:view', $user );

        $order = array_flip( $ids );
        $files = File::with( 'latest' )->whereIn( 'id', $ids )->select( self::COLUMNS )->get()
            ->filter( fn( File $file ) => self::accepts( (string) $file->latestValue( 'mime' ), $class ) )
            ->sortBy( fn( File $file ) => $order[(string) $file->id] )
            ->values();

        if( $files->count() !== count( $ids ) )
        {
            $type = self::kind( $class );
            $replace = ['label' => ucfirst( self::label( $class ) ), 'type' => (string) $type];

            throw new Exception( match( $type ) {
                'image' => __( ':label not found or not a JPEG, PNG or WebP image', $replace ),
                false => __( ':label not found or not supported, images must be JPEG, PNG or WebP', $replace ),
                default => __( ':label not found or not a file of type ":type"', $replace ),
            } );
        }

        // all files are sent in one request
        self::limit( $files->sum( fn( File $file ) => self::size( $file ) ) );

        return $files->map( fn( File $file ) => self::prisma( $file, $class ) )->all();
    }


    /**
     * Builds a Prisma file object from the latest version, so AI operations use the draft.
     *
     * @template T of PrismaFile
     * @param File $file File with latest, disk, path and mime loaded
     * @param class-string<T> $class Prisma file class
     * @return T Prisma file object
     * @throws Exception If the file exceeds "cms.ai.maxmedia"
     */
    public static function media( File $file, string $class = PrismaFile::class ) : PrismaFile
    {
        self::limit( self::size( $file ) );
        return self::prisma( $file, $class );
    }


    /**
     * Passes a base64 image returned by the AI service as temporary upload to the callback.
     *
     * @param string $base64 Base64 encoded image
     * @param string $name Upload name without extension
     * @param \Closure(UploadedFile): mixed $callback Receives the temporary upload
     * @return mixed Return value of the callback
     * @throws Exception If the data isn't a JPEG, PNG or WebP image
     */
    public static function store( string $base64, string $name, \Closure $callback ) : mixed
    {
        // invalid base64 becomes empty, which isn't an image
        $binary = (string) base64_decode( $base64, true );
        $mime = (string) ( new \finfo( FILEINFO_MIME_TYPE ) )->buffer( $binary );

        // no formats which can contain scripts
        if( !in_array( $mime, self::IMAGES, true ) ) {
            throw new Exception( __( 'The AI service returned an invalid image' ) );
        }

        $ext = MimeTypes::getDefault()->getExtensions( $mime )[0] ?? 'png';

        return Uploads::upload( $binary, $name . '.' . $ext, $mime, $callback );
    }


    /**
     * Returns the media type of a Prisma file if it can be described.
     *
     * @param PrismaFile $media Audio, image or video file
     * @return string|null "audio", "image", "video" or NULL if not supported
     */
    public static function type( PrismaFile $media ) : ?string
    {
        $type = self::kind( $media::class );
        return $type !== false && self::describable( $media->mimeType() ?? $type . '/' ) ? $type : null;
    }


    /**
     * Limits the query to describable files without published description.
     *
     * @param Builder $query File query
     */
    public static function undescribed( Builder $query ) : void
    {
        // PostgreSQL can't compare json values
        $query->whereRaw( "CAST(description AS CHAR(2)) = '{}'" )->where( function( Builder $query ) {
            foreach( array_keys( self::MEDIA ) as $type )
            {
                if( $type === 'image' ) {
                    $query->orWhereIn( 'mime', self::IMAGES );
                } else {
                    $query->orWhere( 'mime', 'like', $type . '/%' );
                }
            }
        } );
    }


    /**
     * Validates an upload and returns it as Prisma file object.
     *
     * @template T of PrismaFile
     * @param UploadedFile $upload Uploaded file
     * @param class-string<T> $class Prisma file class
     * @param string|null $label Name in error messages, e.g. "mask", or NULL for the media type
     * @return T Prisma file object
     * @throws Exception If the upload is invalid, too large or not supported
     */
    public static function uploaded( UploadedFile $upload, string $class = Image::class, ?string $label = null ) : PrismaFile
    {
        Uploads::checkUpload( $upload );
        self::limit( (int) $upload->getSize() );

        $mime = (string) $upload->getMimeType();

        if( !self::accepts( $mime, $class ) ) {
            throw new Exception( __( ':label type ":mime" is not allowed', ['label' => ucfirst( $label ?? self::label( $class ) ), 'mime' => $mime] ) );
        }

        if( self::image( $mime ) ) {
            Uploads::checkPixels( $upload );
        }

        return $class::fromBinary( $upload->getContent(), $mime );
    }


    /**
     * Tests if files of the MIME type can be used as the Prisma file class.
     *
     * @param string $mime MIME type
     * @param class-string<PrismaFile> $class Prisma file class, PrismaFile::class for all media types
     * @return bool TRUE for the media type of the class, images only as JPEG, PNG or WebP
     */
    private static function accepts( string $mime, string $class ) : bool
    {
        $type = self::kind( $class );

        return str_starts_with( $mime, $type !== false ? $type . '/' : '' )
            && ( !self::image( $mime ) || in_array( $mime, self::IMAGES, true ) );
    }


    /**
     * Tests if the MIME type is of an image.
     *
     * @param string $mime MIME type
     * @return bool TRUE for images
     */
    private static function image( string $mime ) : bool
    {
        return str_starts_with( $mime, 'image/' );
    }


    /**
     * Returns the media type of the Prisma file class.
     *
     * @param class-string<PrismaFile> $class Prisma file class
     * @return string|false Media type or FALSE for PrismaFile::class
     */
    private static function kind( string $class ) : string|false
    {
        return array_search( $class, self::MEDIA, true );
    }


    /**
     * Returns the media type name for error messages.
     *
     * @param class-string<PrismaFile> $class Prisma file class
     * @return string Media type or "file"
     */
    private static function label( string $class ) : string
    {
        return self::kind( $class ) ?: 'file';
    }


    /**
     * Ensures files don't exceed "cms.ai.maxmedia", they are held in memory several times when sent.
     *
     * @param int $bytes Size in bytes
     * @throws Exception If the files are too large
     */
    private static function limit( int $bytes ) : void
    {
        $max = (float) config( 'cms.ai.maxmedia' );

        if( $max > 0 && $bytes > $max * 1024 * 1024 ) {
            throw new Exception( __( 'Files sent to the AI service must not exceed :max MB', ['max' => $max] ) );
        }
    }


    /**
     * Builds a Prisma file object without checking its size.
     *
     * @template T of PrismaFile
     * @param File $file File with latest, disk, path and mime loaded
     * @param class-string<T> $class Prisma file class
     * @return T Prisma file object
     */
    private static function prisma( File $file, string $class ) : PrismaFile
    {
        $mime = $file->latestValue( 'mime' );
        $path = (string) $file->latestValue( 'path' );
        $type = explode( '/', (string) $mime, 2 )[0];
        $media = $class === PrismaFile::class && isset( self::MEDIA[$type] ) ? self::MEDIA[$type] : $class;

        if( self::remote( $path ) ) {
            return $media::fromUrl( $path, $mime, !(bool) config( 'cms.allow-internal' ) );
        }

        return $media::fromStoragePath( $path, FileStorage::diskName( (string) $file->disk ), $mime );
    }


    /**
     * Tests if the path is a remote URL.
     *
     * @param string $path Path or URL
     * @return bool TRUE for HTTP(S) URLs
     */
    private static function remote( string $path ) : bool
    {
        return (bool) preg_match( '#^https?://#i', $path );
    }


    /**
     * Returns the size of the latest version's file.
     *
     * @param File $file File with latest, disk and path loaded
     * @return int Bytes, 0 for remote and missing files
     */
    private static function size( File $file ) : int
    {
        $path = (string) $file->latestValue( 'path' );

        if( self::remote( $path ) ) {
            return 0;
        }

        $disk = FileStorage::diskName( (string) $file->disk );
        // missing files are reported by fromStoragePath()
        return (int) rescue( fn() => Storage::disk( $disk )->size( $path ), 0, false );
    }
}
