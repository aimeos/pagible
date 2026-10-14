<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Concerns;

use Aimeos\Cms\Uploads;
use Aimeos\Cms\Utils;
use Illuminate\Http\UploadedFile;
use Intervention\Image\ImageManager;
use Intervention\Image\Interfaces\DriverInterface;
use Intervention\Image\Interfaces\ImageInterface;
use Aimeos\Cms\Exception;
use Aimeos\Cms\InvalidException;


/**
 * Creates, compares and stores the preview images of files.
 *
 * @phpstan-require-extends \Aimeos\Cms\Models\File
 * @phpstan-type PreviewDiff array{keep: array<int, string>, missing: array<string, array<string, mixed>>}
 */
trait FilePreviews
{
    /**
     * Creates and adds the preview images
     *
     * @param UploadedFile|string $resource File upload or URL to the file
     * @return self The current instance for method chaining
     */
    public function addPreviews( UploadedFile|string $resource ) : self
    {
        $sizes = config( 'cms.image.preview-sizes', [[]] );
        $manager = $this->imageManager();

        if( is_string( $resource ) && Utils::isValidUrl( $resource ) ) {
            $resource = $this->fetchUrl( $resource, $manager->driver() );

            if( !is_resource( $resource ) ) {
                return $this;
            }

            // SVG images can't be rasterized, so store the SVG itself as preview
            if( in_array( $this->mime, ['image/svg+xml', 'application/gzip'] ) ) {
                return $this->addSvgPreview( $resource );
            }
        }

        if( $resource instanceof UploadedFile ) {
            $filename = $resource->getClientOriginalName();
            $mime = (string) $resource->getMimeType();
        } else {
            $filename = $this->name;
            $mime = $this->mime;
        }

        if( !$manager->driver()->supports( $mime ) ) {
            return $this;
        }

        if( is_string( $resource ) ) {
            throw new InvalidException( 'Invalid image URL' );
        }

        // previews of the previous image must not remain if creating the new ones fails
        $this->previews = [];
        $this->previews = $this->storePreviews( $resource, $sizes, [], $filename );

        return $this;
    }


    /**
     * Removes all preview images from the storage
     *
     * @return self The current instance for method chaining
     */
    public function removePreviews() : self
    {
        $previews = array_values( (array) $this->previews );

        if( !empty( $previews ) ) {
            $this->erase( $previews );
        }

        $this->previews = [];
        return $this;
    }


    /**
     * Tests if previews can be generated from the file itself.
     *
     * @return bool TRUE if the file is an image supported by the image driver, FALSE if not
     */
    protected function previewable() : bool
    {
        return (string) $this->path !== '' && $this->imageManager()->driver()->supports( (string) $this->mime );
    }


    /**
     * Compares the previews with the configured sizes without generating images.
     *
     * The configured size of a preview is taken from its file name. Previews of files which
     * aren't supported images as well as remote and SVG previews are never changed.
     *
     * @param array<int|string, string> $previews Existing previews as width/path pairs
     * @param bool $force Replace all previews
     * @return PreviewDiff
     *  Previews to keep and missing sizes by name
     */
    public function diffPreviews( array $previews, bool $force = false ) : array
    {
        $config = [];
        $keep = [];

        foreach( config( 'cms.image.preview-sizes', [[]] ) as $size ) {
            $config[self::sizeName( $size )] = $size;
        }

        $missing = $config;
        $all = [];

        foreach( $previews as $width => $path ) {
            $all[(int) $width] = (string) $path;
        }

        if( !$this->previewable() ) {
            return ['keep' => $all, 'missing' => []];
        }

        foreach( $all as $width => $path )
        {
            if( str_starts_with( $path, 'http' ) || strtolower( pathinfo( $path, PATHINFO_EXTENSION ) ) === 'svg' ) {
                return ['keep' => $all, 'missing' => []];
            }

            // previews without the sizes in their file names are replaced
            if( ( $names = array_flip( self::previewSizes( $path ) ) ) && array_intersect_key( $config, $names ) ) {
                $missing = array_diff_key( $missing, $names );
                $keep[$width] = $path;
            }
        }

        if( $force ) {
            return ['keep' => [], 'missing' => $config];
        }

        return ['keep' => $keep, 'missing' => $missing];
    }


    /**
     * Adds previews for missing configured sizes and leaves out previews of sizes no longer configured.
     *
     * Missing previews are generated from the file itself if it's a supported image. Removed
     * previews are not deleted from the storage.
     *
     * @param array<int|string, string> $previews Existing previews as width/path pairs
     * @param PreviewDiff|null $diff
     *  Result of diffPreviews() for these previews if already available
     * @return array<int, string>|null New previews as width/path pairs or NULL if nothing changed
     * @throws Exception If the source image is not available or invalid
     * @see diffPreviews()
     */
    public function syncPreviews( array $previews, ?array $diff = null ) : ?array
    {
        ['keep' => $map, 'missing' => $sizes] = $diff ?? $this->diffPreviews( $previews );

        if( $sizes && ( $resource = $this->previewSource( $this->imageManager()->driver() ) ) )
        {
            // all sizes are required to find the ones resulting in the same width as the missing sizes
            try {
                $map = $this->storePreviews( $resource, config( 'cms.image.preview-sizes', [[]] ), $map, $this->name ?: 'image' );
            } finally {
                fclose( $resource );
            }
        }

        ksort( $map );
        $old = array_map( 'strval', $previews );
        ksort( $old );

        return $map === $old ? null : $map;
    }


    /**
     * Stores a downloaded SVG image locally and uses it as the preview.
     *
     * SVG images can't be rasterized into webp/jpg previews, so the sanitized
     * SVG itself is stored on the disk and referenced as the preview at the
     * largest configured preview width. Gzip-compressed SVGZ content is
     * decompressed so the stored preview is a plain, browser-renderable SVG.
     * Returns without a preview if the content isn't a valid SVG.
     *
     * @param resource $resource Seekable file pointer to the downloaded SVG content
     * @return self The current instance for method chaining
     */
    protected function addSvgPreview( $resource ) : self
    {
        $raw = (string) stream_get_contents( $resource );

        // decompress SVGZ so the stored preview is a plain, browser-renderable SVG
        if( !( $content = Uploads::cleanSvg( Uploads::gunzip( $raw ) ) ) ) {
            return $this;
        }

        $disk = $this->storage();
        $dir = $this->dir();
        $path = $dir . '/' . $this->filename( $this->name ?: 'image.svg', 'svg' );

        if( !$disk->put( $path, $content ) ) {
            throw new Exception( sprintf( 'Unable to store preview "%s"', $path ) );
        }

        $widths = array_filter( array_column( config( 'cms.image.preview-sizes', [[]] ), 'width' ) );

        $this->mime = 'image/svg+xml';
        $this->previews = [( $widths ? max( $widths ) : 1920 ) => $path];
        return $this;
    }


    /**
     * Returns the image manager for the configured image driver.
     */
    protected function imageManager() : ImageManager
    {
        return ImageManager::withDriver( '\\Intervention\\Image\\Drivers\\' . ucFirst( config( 'cms.image.driver', 'gd' ) ) . '\Driver' );
    }


    /**
     * Returns the image previews can be generated from as temporary file.
     *
     * @param DriverInterface $driver Image driver for the format support check
     * @return resource|null Seekable temporary file or NULL if the file is no supported image
     */
    protected function previewSource( DriverInterface $driver )
    {
        $path = (string) $this->path;

        if( $path === '' || !$driver->supports( (string) $this->mime ) ) {
            return null;
        }

        if( str_starts_with( $path, 'http' ) ) {
            return Utils::isValidUrl( $path ) ? $this->fetchUrl( $path, $driver ) : null;
        }

        $disk = $this->storage();

        if( !( $stream = $disk->readStream( $path ) ) ) {
            throw new Exception( sprintf( 'Unable to read file "%s"', $path ) );
        }

        try
        {
            if( !( $tmp = tmpfile() ) ) {
                throw new Exception( 'Unable to create temporary file' );
            }

            stream_copy_to_stream( $stream, $tmp );
            fseek( $tmp, 0 );

            return $tmp;
        }
        finally
        {
            fclose( $stream );
        }
    }


    /**
     * Stores previews of the image for the sizes not contained in the file names of the existing previews.
     *
     * Sizes resulting in the same width, e.g. because the image is smaller, share one preview
     * whose file name contains all of them. An existing preview of that width is replaced if
     * its file name doesn't contain all of them.
     *
     * @param UploadedFile|resource $resource Uploaded image or image file handle
     * @param iterable<array<string, mixed>> $sizes Maximum preview widths and heights
     * @param array<int, string> $map Existing previews as width/path pairs
     * @param string $filename Name of the original file
     * @return array<int, string> Existing and new previews as width/path pairs
     */
    protected function storePreviews( mixed $resource, iterable $sizes, array $map, string $filename ) : array
    {
        Uploads::checkPixels( $resource );

        $image = $this->imageManager()->read( $resource );
        $existing = array_merge( ...array_map( self::previewSizes( ... ), array_values( $map ) ) );
        $targets = [];
        $created = [];

        foreach( $sizes as $size )
        {
            $target = $image->size()->scaleDown( $size['width'] ?? null, $size['height'] ?? null );
            $targets[$target->width()][0] ??= $target;
            $targets[$target->width()][1][] = self::sizeName( $size );
        }

        krsort( $targets );

        try
        {
            foreach( $targets as $width => [$target, $names] )
            {
                if( !array_diff( $names, $existing ) ) {
                    continue;
                }

                // each preview is scaled down from the next larger one, so the full image is scaled only once
                if( $image->width() !== $target->width() || $image->height() !== $target->height() ) {
                    $image->resize( $target->width(), $target->height() );
                }

                $map[$width] = $created[] = $this->storePreview( $image, implode( '-', $names ), $filename );
            }
        }
        catch( \Throwable $t )
        {
            $this->storage()->delete( $created );
            throw $t;
        }

        ksort( $map );
        return $map;
    }


    /**
     * Returns the name of the preview size used in the preview file names.
     *
     * @param array<string, mixed> $size Maximum preview width and height
     * @return string Width, height if no width is configured or empty string
     */
    protected static function sizeName( array $size ) : string
    {
        return (string) ( $size['width'] ?? $size['height'] ?? '' );
    }


    /**
     * Returns the names of the preview sizes contained in the preview file name.
     *
     * @param string $path Preview path
     * @return list<string> Names of the preview sizes or empty if the file name contains none
     */
    protected static function previewSizes( string $path ) : array
    {
        if( preg_match( '/_([\d-]*)_[A-Za-z0-9_-]{4}\.[A-Za-z0-9]+$/', $path, $match ) !== 1 ) {
            return [];
        }

        return explode( '-', $match[1] );
    }


    /**
     * Stores the image as preview.
     *
     * @param ImageInterface $image Image scaled down to the preview size
     * @param string $size Names of the configured preview sizes used in the file name
     * @param string $filename Name of the original file
     * @return string Path of the stored preview
     */
    protected function storePreview( ImageInterface $image, string $size, string $filename ) : string
    {
        $ext = $this->imageManager()->driver()->supports( 'image/webp' ) ? 'webp' : 'jpg';
        $disk = $this->storage();

        $ptr = $image->encodeByExtension( $ext, quality: (int) config( 'cms.image.quality', 75 ) )->toFilePointer();
        $path = $this->dir() . '/' . $this->filename( $filename, $ext, $size );

        if( !$disk->put( $path, $ptr ) ) {
            throw new Exception( sprintf( 'Unable to store preview "%s"', $path ) );
        }

        return $path;
    }
}
