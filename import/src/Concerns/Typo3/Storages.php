<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Concerns\Typo3;

use Aimeos\Cms\Import\Files;
use Aimeos\Cms\Import\Typo3;
use Aimeos\Cms\Models\File;
use Illuminate\Support\Collection;


/**
 * Reads the files of the TYPO3 installation and remembers the files imported from the TYPO3 file storages.
 */
trait Storages
{
    /** @var Collection<string, string> URLs of the created files by source path */
    protected Collection $createdFileUrls;

    /** @var array<int, string|null> URLs of the TYPO3 online media by sys_file UID */
    protected array $onlineMedia = [];

    /** @var array<int, string>|null Folders of the TYPO3 file storages relative to the public directory by storage UID */
    protected ?array $storageFolders = null;


    /**
     * Returns the base URLs of the TYPO3 file storages other than fileadmin by storage UID.
     *
     * The storages are below the same base URL as fileadmin, e.g. "https://example.com/uploads".
     *
     * @return array<int, string> Base URLs without trailing slash
     */
    protected function storages(): array
    {
        if( !preg_match( '#^(.*)/fileadmin$#i', $this->fileBase, $matches ) ) {
            return [];
        }

        $storages = [];

        foreach( $this->reader->storageBases() as $uid => $base )
        {
            if( strtolower( $base ) !== 'fileadmin' ) {
                $storages[$uid] = $matches[1] . ( $base !== '' ? '/' . $base : '' );
            }
        }

        return $storages;
    }


    /**
     * Returns the URL of the YouTube or Vimeo video of the TYPO3 online media file.
     *
     * TYPO3 stores the ID of the video in the ".youtube" or ".vimeo" file.
     *
     * @param object $ref sys_file_reference record with its sys_file record in the "sys_file" property
     */
    protected function onlineMedia( object $ref ): ?string
    {
        $sysFile = get_object_vars( $ref )['sys_file'] ?? null;

        if( !( $sysFile instanceof \stdClass ) || !( $source = $this->files->source( $ref ) ) ) {
            return null;
        }

        // the same video can be used several times
        if( ( $uid = (int) ( $sysFile->uid ?? 0 ) ) > 0 && array_key_exists( $uid, $this->onlineMedia ) ) {
            return $this->onlineMedia[$uid];
        }

        $type = strtolower( trim( (string) ( $sysFile->extension ?? '' ) ) ?: pathinfo( (string) ( $sysFile->identifier ?? '' ), PATHINFO_EXTENSION ) );
        $type = str_contains( (string) ( $sysFile->mime_type ?? '' ), 'vimeo' ) ? 'vimeo' : ( str_contains( (string) ( $sysFile->mime_type ?? '' ), 'youtube' ) ? 'youtube' : $type );
        $identifier = (string) ( $sysFile->identifier ?? '' );

        if( str_starts_with( $source, 'http' ) )
        {
            // the files only contain the video ID, the download uses the same URL checks as the other files
            $resource = Files::fetch( $source )['resource'];
            $id = (string) fread( $resource, 1024 );
            fclose( $resource );
        }
        elseif( is_file( $source ) ) {
            $id = (string) file_get_contents( $source, false, null, 0, 1024 );
        } elseif( $this->path !== '' && is_file( $file = $this->localFile( $identifier, (int) ( $sysFile->storage ?? 0 ) ) ) ) {
            $id = (string) file_get_contents( $file, false, null, 0, 1024 );
        } else {
            return null;
        }

        $url = preg_match( '/^[\w-]+$/D', $id = trim( $id ) ) ? match( $type ) {
            'youtube' => 'https://www.youtube.com/watch?v=' . $id,
            'vimeo' => 'https://vimeo.com/' . $id,
            default => null,
        } : null;

        return $uid > 0 ? $this->onlineMedia[$uid] = $url : $url;
    }


    /**
     * Returns the local path of a file in the directory of its file storage in the TYPO3 installation.
     */
    protected function localFile( string $identifier, int $storage = 0 ): string
    {
        $identifier = ltrim( $identifier, '/' );
        $folder = $this->storageFolder( $storage );

        foreach( ['/public/' . $folder . '/', '/' . $folder . '/'] as $dir )
        {
            if( is_file( $file = $this->path . $dir . $identifier ) ) {
                return $file;
            }
        }

        return $this->path . '/' . $folder . '/' . $identifier;
    }


    /**
     * Returns the folder of the TYPO3 file storage relative to the public directory, "fileadmin" for the default storage.
     */
    protected function storageFolder( int $storage ): string
    {
        $this->storageFolders ??= $this->reader->storageBases();
        return ( $this->storageFolders[$storage] ?? '' ) ?: 'fileadmin';
    }


    /**
     * Returns the content of a TSconfig file of the TYPO3 installation, e.g. "EXT:site/Configuration/page.tsconfig".
     *
     * Wildcards like "EXT:site/Configuration/TsConfig/*.tsconfig" include all matching files.
     */
    protected function includeFile( string $source ): ?string
    {
        $source = (string) preg_replace( '/^FILE:/i', '', trim( $source ) );
        $candidates = [];

        if( preg_match( '#^EXT:([\w-]+)/(.+)$#', $source, $matches ) )
        {
            [, $ext, $rest] = $matches;
            $package = str_replace( '_', '-', $ext );

            foreach( ["/typo3conf/ext/{$ext}", "/public/typo3conf/ext/{$ext}", "/packages/{$ext}", "/packages/{$package}",
                "/vendor/*/{$ext}", "/vendor/*/{$package}"] as $dir
            ) {
                $candidates[] = $this->path . $dir . '/' . $rest;
            }
        }
        elseif( !str_contains( $source, '..' ) && !str_starts_with( $source, '/' ) )
        {
            $candidates = [$this->path . '/' . $source, $this->path . '/public/' . $source];
        }

        foreach( $candidates as $pattern )
        {
            if( $files = array_filter( glob( $pattern ) ?: [], 'is_file' ) ) {
                return implode( "\n", array_map( fn( $file ) => (string) file_get_contents( $file ), $files ) );
            }
        }

        return null;
    }


    /**
     * Returns the PageTSconfig loaded by TYPO3 for all pages.
     *
     * Since TYPO3 12, the "Configuration/page.tsconfig" files of the extensions and site sets and
     * the "page.tsconfig" files of the site configurations are loaded automatically.
     */
    protected function globalTsconfig(): string
    {
        $files = [];

        foreach( ['/typo3conf/ext/*', '/public/typo3conf/ext/*', '/packages/*', '/vendor/*/*'] as $dir )
        {
            foreach( ['/Configuration/page.tsconfig', '/Configuration/Sets/*/page.tsconfig'] as $file ) {
                array_push( $files, ...( glob( $this->path . $dir . $file ) ?: [] ) );
            }
        }

        array_push( $files, ...( glob( $this->path . '/config/sites/*/page.tsconfig' ) ?: [] ) );

        return implode( "\n", array_map( fn( $file ) => (string) file_get_contents( $file ), array_unique( array_filter( $files, 'is_file' ) ) ) );
    }


    /**
     * Keeps only the URLs of the created and reused files which are still known.
     */
    #[\Override]
    protected function forgetFiles(): void
    {
        parent::forgetFiles();

        if( $this->createdFileUrls->count() > $this->createdFiles->count() + count( $this->reusedFiles ) ) {
            $this->createdFileUrls = $this->createdFileUrls->only( [...$this->createdFiles->keys()->all(), ...array_keys( $this->reusedFiles )] );
        }
    }


    /**
     * Adds the file of a previous import so it's used instead of creating it again.
     *
     * The URLs of the stored files are used in the HTML of the content elements.
     *
     * @param string $path Source URL or path of the file
     */
    protected function reuseFile( string $path, File $file ): void
    {
        parent::reuseFile( $path, $file );
        $this->createdFileUrls->put( $path, Typo3\Files::stored( $file ) );
    }


    /**
     * Remembers the URL of the file created by importFile(), URLs of rolled back files are overwritten when the file is created again.
     *
     * @param string $path Source URL or path of the file
     * @param File $file Created file
     */
    #[\Override]
    protected function importedFile( string $path, File $file ): void
    {
        $this->createdFileUrls->put( $path, Typo3\Files::stored( $file ) );
    }


    /**
     * Returns the label of the file reference in the warnings.
     *
     * @param object $ref sys_file_reference record
     */
    protected function refLabel( object $ref ): string
    {
        $data = get_object_vars( $ref );
        $label = fn( string $key ) => is_scalar( $data[$key] ?? null ) ? (string) $data[$key] : '?';

        return "file reference [{$label( 'uid' )}] of {$label( 'tablenames' )} [{$label( 'uid_foreign' )}]";
    }
}
