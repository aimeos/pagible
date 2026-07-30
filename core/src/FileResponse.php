<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms;

use Aimeos\Cms\Models\File;
use Aimeos\Cms\Models\Page;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use League\Flysystem\Local\LocalFilesystemAdapter;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Response;


final class FileResponse
{
    /**
     * Delivers a private original or preview from local or remote storage.
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
            || File::owner( (string) $file->tenant_id, $path ) !== strtolower( (string) $file->id ) ) {
            abort( 404 );
        }

        $storage = Storage::disk( File::diskName( (string) $file->disk ) );

        $adapter = $storage->getAdapter();
        $name = $variant === null
            ? ( (string) ( $data->name ?? '' ) ?: (string) $file->name )
            : $path;
        $mime = $variant === null
            ? ( (string) ( $data->mime ?? '' ) ?: (string) $file->mime ?: 'application/octet-stream' )
            : self::mime( $path );
        $inline = self::inline( $mime );
        $filename = self::filename( $name );
        $fallback = preg_match( '/^[\x20-\x7E]+$/', $filename ) && !str_contains( $filename, '%' )
            ? $filename : 'download';
        $headers = [
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
     * Generates the access-controlled URL for a private File.
     */
    public static function url( Page $page, string $file, int|string|null $variant = null ) : string
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
     */
    private static function expiry( ?int $expires = null ) : Carbon
    {
        $expiration = now()->addSeconds( max( 1, (int) config( 'cms.disks.private.ttl', 300 ) ) );

        return $expires === null
            ? $expiration
            : $expiration->setTimestamp( min( $expiration->getTimestamp(), $expires ) );
    }


    /**
     * Returns a safe download name.
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
     * Returns the MIME type for a preview.
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
