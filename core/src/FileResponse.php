<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms;

use Aimeos\Cms\Models\File;
use Aimeos\Cms\Models\PageView;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;
use League\Flysystem\Local\LocalFilesystemAdapter;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Response;


final class FileResponse
{
    /**
     * Delivers a private original or preview after validating its File UUID ownership.
     *
     * Local files are returned directly. Remote disks use a temporary URL when available and cap its expiry at the
     * enclosing page-access token.
     *
     * @param string $id File UUID
     * @param int|string|null $variant Preview width, or null for the original
     * @param bool $latest Whether editors should receive the latest draft version
     * @param int|null $expires Unix timestamp limiting a remote temporary URL
     * @return Response File response or redirect to a remote temporary URL
     */
    public static function make( string $id, int|string|null $variant = null,
        bool $latest = false, ?int $expires = null ) : Response
    {
        $file = File::select( 'id', 'tenant_id', 'disk', 'name', 'mime', 'path', 'previews', 'latest_id' )
            ->with( $latest ? ['latest' => fn( $query ) => $query->select( 'id', 'data' )] : [] )
            ->findOrFail( $id );

        if( $file->disk !== 'private' ) {
            abort( 404 );
        }

        $data = $latest ? $file->latest?->data : null;
        $variant = $variant === null ? null : (int) $variant;
        $previews = (array) ( $data->previews ?? $file->previews );
        $path = $variant === null
            ? (string) ( $data->path ?? $file->path )
            : (string) ( $previews[$variant] ?? '' );

        if( !$path || str_starts_with( $path, 'http' )
            || !Files::owns( (string) $file->tenant_id, (string) $file->id, $path ) ) {
            abort( 404 );
        }

        $name = $variant === null
            ? ( (string) ( $data->name ?? '' ) ?: (string) $file->name )
            : $path;
        $mime = $variant === null
            ? ( (string) ( $data->mime ?? '' ) ?: (string) $file->mime ?: 'application/octet-stream' )
            : self::mime( $path );

        $storage = Files::disk( (string) $file->disk );
        return self::send( $storage, $path, self::headers( $name, $mime ), $expires );
    }


    /**
     * Returns the headers for delivering the file securely.
     *
     * @param string $name File name for downloading
     * @param string $mime Mime type of the file
     * @return array<string, string> Response headers
     */
    private static function headers( string $name, string $mime ) : array
    {
        $inline = self::inline( $mime );
        $filename = self::filename( $name );
        $fallback = preg_match( '/^[\x20-\x7E]+$/', $filename ) && !str_contains( $filename, '%' )
            ? $filename : 'download';

        return [
            'Cache-Control' => 'private, no-store',
            'Content-Disposition' => HeaderUtils::makeDisposition(
                $inline ? 'inline' : 'attachment',
                $filename,
                $fallback,
            ),
            'Content-Security-Policy' => "sandbox; default-src 'none'",
            'Content-Type' => $inline ? $mime : 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
        ];
    }


    /**
     * Returns the response delivering the file from the storage.
     *
     * Storages providing temporary URLs redirect to them, others stream the file.
     *
     * @param FilesystemAdapter $storage Storage containing the file
     * @param string $path Path of the file in the storage
     * @param array<string, string> $headers Response headers
     * @param int|null $expires Expiry time of temporary URLs in seconds
     * @return Response File or redirect response
     */
    private static function send( FilesystemAdapter $storage, string $path, array $headers, ?int $expires ) : Response
    {
        $adapter = $storage->getAdapter();

        if( !( $adapter instanceof LocalFilesystemAdapter ) && $storage->providesTemporaryUrls() )
        {
            return redirect()->away( $storage->temporaryUrl(
                $path,
                self::expiry( $expires ),
                [
                    'ResponseCacheControl' => $headers['Cache-Control'],
                    'ResponseContentDisposition' => $headers['Content-Disposition'],
                    'ResponseContentType' => $headers['Content-Type'],
                ],
            ) )->withHeaders( $headers )->setPrivate();
        }

        if( $adapter instanceof LocalFilesystemAdapter ) {
            if( !is_file( $local = $storage->path( $path ) ) ) {
                abort( 404 );
            }

            return response()->file( $local, $headers )->setPrivate();
        }

        if( !$storage->exists( $path ) ) {
            abort( 404 );
        }

        return $storage->response( $path, null, $headers )->setPrivate();
    }


    /**
     * Generates the page-aware access URL for a private File.
     *
     * Restricted renders receive a short-lived signed URL so embedded media can reuse the page authorization.
     *
     * @param PageView $page Page authorizing access to the File
     * @param string $file File UUID
     * @param int|string|null $variant Preview width, or null for the original
     * @return string Page-aware access URL
     */
    public static function url( PageView $page, string $file, int|string|null $variant = null ) : string
    {
        $params = ['page' => $page->id, 'file' => $file];

        if( $variant !== null ) {
            $params['variant'] = (int) $variant;
        }

        if( config( 'cms.multidomain' ) ) {
            $params['domain'] = (string) $page->domain;
        }

        if( request()->attributes->get( 'cms.asset-token-page' ) === (string) $page->id ) {
            $params['tenant'] = Tenancy::value();

            return URL::temporarySignedRoute(
                'cms.asset',
                self::expiry(),
                $params,
            );
        }

        return route( 'cms.asset', $params );
    }


    /**
     * Returns the configured private URL expiry capped by the access token.
     *
     * @param int|null $expires Unix timestamp inherited from the access token
     * @return Carbon Expiration time for the generated temporary URL
     */
    private static function expiry( ?int $expires = null ) : Carbon
    {
        $expiration = now()->addSeconds( max( 1, (int) config( 'cms.disks.private.ttl', 300 ) ) );

        return $expires === null
            ? $expiration
            : $expiration->setTimestamp( min( $expiration->getTimestamp(), $expires ) );
    }


    /**
     * Returns a safe basename for a Content-Disposition header.
     *
     * @param string $name Stored original name or preview path
     * @return string Sanitized filename, or "download" when no usable name remains
     */
    private static function filename( string $name ) : string
    {
        $name = basename( str_replace( '\\', '/', $name ) );
        $name = trim( (string) preg_replace( '/[\x00-\x1F\x7F]/', '', $name ) );

        return $name ?: 'download';
    }


    /**
     * Whether the MIME type is safe to render in the browser.
     */
    private static function inline( string $mime ) : bool
    {
        return str_starts_with( $mime, 'image/' )
            || str_starts_with( $mime, 'audio/' )
            || str_starts_with( $mime, 'video/' );
    }


    /**
     * Returns a safe MIME type inferred from a preview path extension.
     *
     * @param string $path Preview storage path
     * @return string Supported image MIME type or application/octet-stream
     */
    private static function mime( string $path ) : string
    {
        return match( strtolower( pathinfo( $path, PATHINFO_EXTENSION ) ) ) {
            'gif' => 'image/gif',
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'svg' => 'image/svg+xml',
            'webp' => 'image/webp',
            default => 'application/octet-stream',
        };
    }
}
