<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Resource;

use Aimeos\Cms\ContentAction;
use Aimeos\Cms\DB;
use Aimeos\Cms\Exception;
use Aimeos\Cms\Exceptions\ConflictException;
use Aimeos\Cms\Exceptions\InvalidInputException;
use Aimeos\Cms\Exceptions\PermissionException;
use Aimeos\Cms\FileStorage;
use Aimeos\Cms\Invalidation;
use Aimeos\Cms\Jobs\PruneVersions;
use Aimeos\Cms\LifecycleAction;
use Aimeos\Cms\Events\Purged;
use Aimeos\Cms\Models\Base;
use Aimeos\Cms\Scout;
use Aimeos\Cms\Merge;
use Aimeos\Cms\Models\File;
use Aimeos\Cms\Permission;
use Aimeos\Cms\Resource\Support\Bulk;
use Aimeos\Cms\Uploads;
use Aimeos\Cms\Utils;
use Aimeos\Cms\Validation;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Builder;
use Aimeos\Cms\Resource\Support\Items;
use Aimeos\Cms\Resource\Support\Langs;
use Aimeos\Cms\Concerns\ChangeChunks;
use Aimeos\Cms\Publication;


/**
 * Operations on files.
 *
 * Each method checks the permission of its action for the passed user, e.g. "file:save" for
 * save(), and throws a PermissionException if it's missing. Without user (NULL), the call is
 * anonymous and throws too unless it runs inside Permission::system(), e.g. in commands or jobs.
 *
 * Use it via the Aimeos\Cms\Facades\Files facade, which resolves the instance from the container.
 * Applications can replace it by a subclass overriding the public or protected methods, e.g. with
 * `$this->app->singleton( \Aimeos\Cms\Resource\Files::class, MyFiles::class )` in their service provider.
 *
 * @phpstan-type StoredFile array{disk: string, path: string|null, mime: string|null, previews: array<int|string, mixed>|null, prepared: bool}
 * @phpstan-type FileInput array{name?: string|null, lang?: string|null, description?: array<string, string>|object|null, transcription?: array<string, string>|object|null, path?: string|null, previews?: array<int|string, string>|null, ...}
 */
class Files
{
    use ChangeChunks;


    public const MAX_RELOCATE = 100;


    /**
     * Ingests the source as new media item with its first version.
     *
     * @param UploadedFile|string $source Uploaded file or remote URL
     * @param FileInput $input File values like name, lang and description
     * @param Authenticatable|null $user Authenticated user for editor tracking
     * @param string|null $disk Storage disk, "public" (default) or "private"
     * @param UploadedFile|null $preview Uploaded preview or null for automatic previews
     * @return File New file
     */
    public function add( UploadedFile|string $source, array $input = [], ?Authenticatable $user = null,
        ?string $disk = null, ?UploadedFile $preview = null ) : File
    {
        Permission::check( 'file:add', $user );

        $file = new File();
        $file->disk = $disk ?? 'public';
        $file->fill( $input );

        // checked before storing the file
        Permission::checkLang( $file->lang, $user );

        // Store the file and generate previews outside the transaction to keep
        // slow network, disk and image work off the database connection.
        $file->ingest( $source, $preview );

        return $this->insert( $file, $user );
    }


    /**
     * Persists a prepared file as a new media item with its first version.
     *
     * The caller fills the file's path, mime, previews, name, lang, description
     * and transcription; this method stores it, creates the initial version,
     * indexes it and broadcasts the change. The permission is checked by add().
     *
     * @param File $file Prepared file model (path/mime/previews/name set)
     * @param Authenticatable|null $user Authenticated user for editor tracking
     * @return File
     */
    protected function insert( File $file, ?Authenticatable $user = null ) : File
    {
        $editor = Utils::editor( $user );
        $file->setUniqueIds();
        $file->checkPaths( [$file->path, ...(array) $file->previews] );

        try
        {
            Validation::limits( File::class, $file->only( ['lang', 'mime', 'name', 'path'] ) );

            $result = File::locked( function() use ( $file, $editor ) {
                $file->checkStored( [$file->path, ...(array) $file->previews] );

                return DB::transaction( function() use ( $file, $editor ) {
                    $file->editor = $editor;

                    // Saves the file with its draft version so the draft (latest=true) row is indexed
                    $file->draft( [
                        'lang' => $file->lang,
                        'editor' => $editor,
                        ...File::snapshot( $file->toArray() ),
                    ], [] );

                    return $file;
                } );
            } );
        }
        catch( \Throwable $t )
        {
            $this->discardFile( $file );
            throw $t;
        }

        $result->announce( ContentAction::Added, $editor );
        return $result;
    }


    /**
     * Moves the managed paths of a file to another logical disk.
     *
     * @param string $id File UUID
     * @param string $disk Target logical disk, either "public" or "private"
     * @param Authenticatable|null $user Authenticated user authorizing and recording the relocation
     * @return File Relocated file
     * @throws ModelNotFoundException If the file doesn't exist
     */
    public function relocate( string $id, string $disk, ?Authenticatable $user = null ) : File
    {
        return Items::one( File::class, $this->relocateMany( [$id], $disk, $user ), $id );
    }


    /**
     * Moves the managed paths of several Files to another logical disk under tenant and File locks.
     *
     * @param array<string> $ids File UUIDs
     * @param string $disk Target logical disk, either "public" or "private"
     * @param Authenticatable|null $user Authenticated user authorizing and recording the relocation
     * @return Collection<int, File>
     * @throws Exception If permissions, path ownership, remote paths, or storage verification prevent relocation
     */
    public function relocateMany( array $ids, string $disk,
        ?Authenticatable $user = null ) : Collection
    {
        FileStorage::diskName( $disk );
        $ids = array_values( array_unique( $ids ) );

        if( count( $ids ) > self::MAX_RELOCATE ) {
            throw new InvalidInputException( sprintf(
                'No more than %d files may be relocated at once.',
                self::MAX_RELOCATE,
            ) );
        }

        $ids = File::bulkIds( $ids );

        if( !$ids ) {
            return ( new File() )->newCollection();
        }

        $editor = Utils::editor( $user );
        $changed = [];

        $found = File::whereIn( 'id', $ids )->pluck( 'id' )->map( strval(...) )->flip();

        foreach( $ids as $id ) {
            if( !$found->has( $id ) ) {
                File::findOrFail( $id );
            }
        }

        Permission::check( 'file:relocate', $user );

        try
        {
            return File::locked( function() use ( &$changed, $disk, $editor, $ids, $user ) {
                $result = [];

                if( Permission::langs( $user ) !== null ) {
                    Langs::authorize( File::whereIn( 'id', $ids )->get( ['id', 'lang', 'latest_id'] ), $user );
                }

                foreach( $ids as $id ) {
                    $result[] = $this->relocateFile( $id, $disk, $editor, $changed );
                }

                return ( new File() )->newCollection( $result );
            } );
        }
        finally
        {
            if( $changed )
            {
                $files = ( new File() )->newCollection( array_values( $changed ) );

                Invalidation::refs( [], $files->modelKeys() );
                File::announceMany( $files, ContentAction::Saved, $editor, ['disk' => $disk], bulk: true );
            }
        }
    }


    /**
     * Moves the managed paths of one locked File to another logical disk.
     *
     * @param string $id File UUID
     * @param string $disk Target logical disk, either "public" or "private"
     * @param string $editor Name of the editor
     * @param array<string, File> $changed Relocated files by ID, the file is added if it was moved
     * @return File File on the target disk
     */
    protected function relocateFile( string $id, string $disk, string $editor, array &$changed ) : File
    {
        $file = File::findOrFail( $id );

        if( $file->getAttribute( 'disk' ) !== $disk )
        {
            $file->relocate( $disk, $editor );
            $changed[$id] = $file;
        }

        return $file;
    }


    /**
     * Ingests optional file data, updates metadata, and creates a conflict-aware version.
     *
     * Storage work completes before the transaction; the File lock then verifies the prepared paths and logical disk.
     *
     * @param string $id File UUID
     * @param FileInput $input File fields to update
     * @param Authenticatable|null $user Authenticated user for editor tracking
     * @param string|null $latestId Version ID the editor was working on (for conflict detection)
     * @param UploadedFile|null $upload File upload to store
     * @param UploadedFile|false|null $preview Preview upload, false to clear, null for auto-detect
     * @return File
     */
    public function save( string $id, array $input, ?Authenticatable $user = null,
        ?string $latestId = null, ?UploadedFile $upload = null, UploadedFile|false|null $preview = null ) : File
    {
        Permission::check( 'file:save', $user );

        Validation::limits( File::class, $input );
        $editor = Utils::editor( $user );
        $stored = ['disk' => 'public', 'path' => null, 'mime' => null, 'previews' => null, 'prepared' => false];

        // checked again while the file is locked
        if( Permission::langs( $user ) !== null ) {
            Langs::authorize( $this->file()->findOrFail( $id ), $user, $input );
        }

        try
        {
            // Prepare storage and remote image work before opening the transaction.
            $this->prepareFile( $stored, $id, $input, $upload, $preview );
            $file = $this->writeFile( $id, $input, $editor, $latestId, $preview, $stored, $user );
        }
        catch( \Throwable $t )
        {
            $this->discardFile( ( new File() )->forceFill( [
                'id' => $id,
                'disk' => $stored['disk'],
                'path' => $stored['path'],
                'previews' => $stored['previews'] ?? [],
            ] ) );
            throw $t;
        }

        $file->announce( ContentAction::Saved, $editor );
        return $file;
    }


    /**
     * Applies the same input to several files at once.
     *
     * @param array<string> $ids File IDs to update
     * @param FileInput $input Fields applied to every file (e.g. ['lang' => 'de'])
     * @param Authenticatable|null $user Authenticated user for editor tracking
     * @return BulkResult
     * @throws InvalidInputException If the input carries the file-specific "path" or "previews"
     */
    public function bulk( array $ids, array $input, ?Authenticatable $user = null ) : BulkResult
    {
        Permission::check( 'file:save', $user );

        if( isset( $input['path'] ) || isset( $input['previews'] ) ) {
            throw new InvalidInputException( 'Bulk edits cannot change the path or previews of a file' );
        }

        Validation::limits( File::class, $input );

        // an explicit NULL language makes the files language-neutral
        Permission::checkLang( $input['lang'] ?? null, $user, array_key_exists( 'lang', $input ) );

        return Bulk::save( File::class, $ids, $input, $user, function( string $id, array $refs, string $editor ) use ( $input, $user ) : ?File {

            if( !( $file = $this->file()->lockForUpdate()->find( $id ) ) ) {
                return null;
            }

            Langs::authorize( $file, $user, $input );
            return $this->apply( $file, $input, $editor );
        } );
    }

    /**
     * Publishes or schedules the latest version of the file.
     *
     * @param string $id File ID
     * @param Authenticatable|null $user Authenticated user for editor tracking
     * @param string|null $at Publication date or NULL to publish immediately
     * @return File Published file
     * @throws ModelNotFoundException If the file doesn't exist
     */
    public function publish( string $id, ?Authenticatable $user = null, ?string $at = null ) : File
    {
        return Items::one( File::class, $this->publishMany( [$id], $user, $at ), $id );
    }


    /**
     * Publishes or schedules the latest versions of the files.
     *
     * @param array<string> $ids File IDs
     * @param Authenticatable|null $user Authenticated user for editor tracking
     * @param string|null $at Publication date or NULL to publish immediately
     * @return Collection<int, File> Published files
     */
    public function publishMany( array $ids, ?Authenticatable $user = null, ?string $at = null ) : Collection
    {
        return Publication::publish( File::class, $ids, $user, $at );
    }


    /**
     * Moves the file to the trash.
     *
     * @param string $id File ID
     * @param Authenticatable|null $user Authenticated user for editor tracking
     * @return File Trashed file
     * @throws ModelNotFoundException If the file doesn't exist
     */
    public function drop( string $id, ?Authenticatable $user = null ) : File
    {
        return Items::one( File::class, $this->dropMany( [$id], $user ), $id );
    }


    /**
     * Permanently deletes the file.
     *
     * @param string $id File ID
     * @param Authenticatable|null $user Authenticated user for editor tracking
     * @return File Deleted file
     * @throws ModelNotFoundException If the file doesn't exist
     */
    public function purge( string $id, ?Authenticatable $user = null ) : File
    {
        return Items::one( File::class, $this->purgeMany( [$id], $user ), $id );
    }


    /**
     * Restores the trashed file.
     *
     * @param string $id File ID
     * @param Authenticatable|null $user Authenticated user for editor tracking
     * @return File Restored file
     * @throws ModelNotFoundException If the file doesn't exist
     */
    public function restore( string $id, ?Authenticatable $user = null ) : File
    {
        return Items::one( File::class, $this->restoreMany( [$id], $user ), $id );
    }


    /**
     * Moves files to the trash.
     *
     * @param array<string> $ids File IDs
     * @param Authenticatable|null $user Authenticated user for editor tracking
     * @param array<string> $fields Requested response fields
     * @return Collection<int, File> Trashed files
     */
    public function dropMany( array $ids, ?Authenticatable $user = null, array $fields = [] ) : Collection
    {
        Permission::check( 'file:drop', $user );

        $ids = File::bulkIds( $ids );
        $editor = Utils::editor( $user );

        $items = File::locked( fn() => Scout::mute( [File::class], fn() => $this->change( File::class, $ids, $fields, [], function( $items ) use ( $editor ) {
            // dispatched after commit
            Invalidation::refs( [], array_map( strval( ... ), $items->modelKeys() ) );
            File::trashMany( $items, $editor );
        }, $user ) ) );

        File::announceMany( $items, LifecycleAction::Dropped, $editor, ['deleted_at' => (string) $items->first()?->deleted_at] );

        // the CMS search ignores trashed items, other search engines keep them if soft deletes are enabled
        if( Scout::keepsTrashed() ) {
            Scout::index( File::class, $this->ids( $items ) );
        } else {
            Scout::unindex( File::class, $this->ids( $items ) );
        }

        return $items;
    }


    /**
     * Permanently deletes files.
     *
     * @param array<string> $ids File IDs
     * @param Authenticatable|null $user Authenticated user for editor tracking
     * @param array<string> $fields Requested response fields
     * @return Collection<int, File> Deleted files
     */
    public function purgeMany( array $ids, ?Authenticatable $user = null, array $fields = [] ) : Collection
    {
        Permission::check( 'file:purge', $user );

        $ids = File::bulkIds( $ids );
        $editor = Utils::editor( $user );

        // a single file is announced with its publication state, which is removed with its versions
        $states = count( $ids ) === 1 && Base::announces( Purged::class );

        $purge = fn( array $ids ) => $this->change( File::class, $ids, $fields, ['path', 'previews'], function( $items ) use ( $states ) {
            // the references are removed with the files, pages using trashed files were invalidated when they were trashed
            Invalidation::refs( [], array_map( strval( ... ), $items->reject( fn( File $item ) => $item->trashed() )->modelKeys() ) );

            if( $states ) {
                $items->load( ['latest' => fn( $query ) => $query->select( 'id', 'published', 'publish_at', 'created_at' )] );
            }

            File::forceDeleteMany( $items );
        }, $user );

        [$items, $denied] = File::locked( fn() => Scout::mute( [File::class], function() use ( $purge, $ids ) {
            try {
                return [$purge( $ids ), null];
            } catch( PermissionException $e ) {
                throw $e;
            } catch( \Exception $e ) {
                // files whose storage can't be cleaned up must not prevent purging the others
                $items = collect();
                $denied = null;

                foreach( $ids as $id ) {
                    try {
                        $items->push( ...$purge( [$id] ) );
                    } catch( PermissionException $e ) {
                        // thrown after the purged files are announced and unindexed
                        $denied ??= $e;
                    } catch( \Exception $e ) {
                        report( $e );
                    }
                }

                return [$items, $denied];
            }
        } ) );

        // many files are announced as one bulk event because they may be purged in several transactions
        File::announceMany( $items, LifecycleAction::Purged, $editor, bulk: count( $ids ) !== 1 );
        Scout::unindex( File::class, $this->ids( $items ) );

        if( $denied ) {
            throw $denied;
        }

        return $items;
    }


    /**
     * Restores trashed files.
     *
     * @param array<string> $ids File IDs
     * @param Authenticatable|null $user Authenticated user for editor tracking
     * @param array<string> $fields Requested response fields
     * @return Collection<int, File> Restored files
     */
    public function restoreMany( array $ids, ?Authenticatable $user = null, array $fields = [] ) : Collection
    {
        Permission::check( 'file:keep', $user );

        $ids = File::bulkIds( $ids );
        $editor = Utils::editor( $user );

        // all columns are required to update the search index
        $items = File::locked( fn() => Scout::mute( [File::class], fn() => $this->change( File::class, $ids, $fields, File::SELECT_COLUMNS, function( $items ) use ( $editor ) {
            // dispatched after commit
            Invalidation::refs( [], array_map( strval( ... ), $items->modelKeys() ) );
            File::restoreMany( $items, $editor );
        }, $user ) ) );

        File::announceMany( $items, LifecycleAction::Restored, $editor, ['deleted_at' => null] );
        Scout::index( File::class, $this->ids( $items ) );

        return $items;
    }


    /**
     * Returns the file query with the latest version columns required for saving.
     *
     * @return Builder<File> File query including trashed files
     */
    protected function file() : Builder
    {
        return File::withTrashed()->with( ['latest' => fn( $q ) => $q->select( 'id', 'versionable_id', 'data', 'aux', 'lang', 'editor' )] );
    }


    /**
     * Three-way merges input into an already loaded File and creates its new latest version.
     *
     * Shared by saveFile() (single) and bulkFile() (bulk). Must run inside a transaction.
     *
     * @param File $orig File model with the "latest" relation loaded
     * @param array<string, mixed> $input File fields to update (merged with latest version)
     * @param string $editor Name of the editing user
     * @param string|null $latestId Version ID the editor was working on for conflict detection
     * @param StoredFile|null $stored Prepared storage data of an upload, if any
     * @param bool $clearPreviews TRUE to remove the previews if no new ones were stored
     * @return File Updated file with the new version as "latest"
     */
    protected function apply( File $orig, array $input, string $editor, ?string $latestId = null,
        ?array $stored = null, bool $clearPreviews = false ) : File
    {
        $storedPath = $stored['path'] ?? null;
        $storedPreviews = $stored['previews'] ?? null;
        $previews = $orig->latestValue( 'previews' );
        $path = $orig->latestValue( 'path' );

        $file = clone $orig;

        $input = File::snapshot( $input );
        [$data, $aux, $diffs] = Merge::file( $orig, $input['data'], $input['aux'], $latestId );
        $file->fill( $data + $aux );

        $paths = array_map( FileStorage::checkPath( ... ), array_values( (array) ( $input['data']['previews'] ?? [] ) ) );
        array_push( $paths, $storedPath, ...array_values( $storedPreviews ?? [] ) );

        $file->previews = $input['data']['previews'] ?? $previews;
        $file->path = $storedPath ?? FileStorage::checkPath( $input['data']['path'] ?? null ) ?? $path;

        if( isset( $input['data']['path'] ) ) {
            $paths[] = $file->path;
        }

        $orig->checkPaths( $paths );
        $file->editor = $editor;

        if( $file->path !== $path )
        {
            $file->mime = ( $stored['mime'] ?? null ) ?? Uploads::mimetype( $file->path, (string) $file->disk );

            Uploads::checkMimetype( (string) $file->mime );
        }

        if( $storedPreviews !== null ) {
            $file->previews = $storedPreviews;
        } elseif( $clearPreviews ) {
            $file->previews = [];
        }

        $snapshot = File::snapshot( $file->toArray() );

        $orig->draft( [
            'lang' => $file->lang,
            'editor' => $editor,
            'data' => $snapshot['data'],
            'aux' => array_replace( $snapshot['aux'], $aux ),
        ], [], $diffs );

        return $orig;
    }


    /**
     * Removes the data stored for a file whose save failed.
     *
     * Cleanup errors are reported instead of thrown so they don't hide the error of the failed save.
     *
     * @param File $file File with the stored path and previews
     */
    protected function discardFile( File $file ) : void
    {
        try {
            $file->removePreviews()->removeFile();
        } catch( \Throwable $cleanup ) {
            report( $cleanup );
        }
    }


    /**
     * Stores the uploaded or fetched file and its previews before the file is saved.
     *
     * @param StoredFile $stored
     *  Prepared storage data, updated while preparing so it can be removed if a later step fails
     * @param string $id File UUID
     * @param array<string, mixed> $input File fields to update
     * @param UploadedFile|null $upload File upload to store
     * @param UploadedFile|false|null $preview Preview upload, false to clear, null for auto-detect
     */
    protected function prepareFile( array &$stored, string $id, array $input, ?UploadedFile $upload,
        UploadedFile|false|null $preview ) : void
    {
        $tmp = ( new File() )->forceFill( ['id' => $id] );
        $newPath = FileStorage::checkPath( $input['path'] ?? null );
        $previewUpload = $preview instanceof UploadedFile ? $preview : null;
        $currentPath = null;

        if( $upload || $previewUpload || $newPath !== null )
        {
            $current = $this->file()->findOrFail( $id );
            $currentPath = (string) $current->latestValue( 'path' );
            $stored['disk'] = $tmp->disk = (string) $current->getAttribute( 'disk' );
            $tmp->name = (string) ( $input['name'] ?? $current->latestValue( 'name' ) );

            if( $newPath !== null && !( $stored['disk'] === 'private' && str_starts_with( $newPath, 'http' ) ) ) {
                $current->checkPaths( [$newPath] );
            }
        }

        $source = $upload ?? ( $newPath !== null && $newPath !== $currentPath ? $newPath : null );

        if( $source === null && !$previewUpload ) {
            return;
        }

        $remote = is_string( $source ) && str_starts_with( $source, 'http' );

        $stored['prepared'] = true;
        $tmp->ingest( $source, $previewUpload );
        $stored['path'] = $upload !== null || ( $stored['disk'] === 'private' && $remote ) ? $tmp->path : null;
        $stored['mime'] = $source !== null ? (string) $tmp->mime : null;

        // uploads replace the previews of the previous file, non-image uploads have none
        if( $previewUpload || $remote || $upload !== null ) {
            $stored['previews'] = (array) $tmp->previews;
        } elseif( is_string( $source ) && $preview === null && !isset( $input['previews'] ) ) {
            // previews of the previous image don't belong to the new path, they are created for supported images
            $stored['previews'] = $tmp->syncPreviews( [] ) ?? [];
        }
    }


    /**
     * Saves the file with the prepared storage data while the file is locked.
     *
     * @param string $id File UUID
     * @param array<string, mixed> $input File fields to update
     * @param string $editor Name of the editing user
     * @param string|null $latestId Version ID the editor was working on (for conflict detection)
     * @param UploadedFile|false|null $preview Preview upload, false to clear, null for auto-detect
     * @param StoredFile $stored Prepared storage data
     * @param Authenticatable|null $user Authenticated user whose writable languages are checked
     * @return File Saved file
     */
    protected function writeFile( string $id, array $input, string $editor, ?string $latestId,
        UploadedFile|false|null $preview, array $stored, ?Authenticatable $user ) : File
    {

        return File::locked( function() use ( $id, $input, $editor, $latestId, $preview, $stored, $user ) {
            $file = $this->file()->findOrFail( $id );
            Langs::authorize( $file, $user, $input );

            if( $stored['prepared'] && $file->getAttribute( 'disk' ) !== $stored['disk'] ) {
                throw new ConflictException( 'File disk changed while saving; retry the request' );
            }

            $file->checkStored( [$stored['path'], ...( $stored['previews'] ?? [] )] );

            return DB::transaction( function() use ( $file, $input, $editor, $latestId, $preview, $stored ) {
                $this->apply( $file, $input, $editor, $latestId, $stored, $preview === false );
                PruneVersions::dispatchChunked( File::class, [$file->id] );

                return $file;
            } );
        } );
    }


    /**
     * Returns the IDs of the changed items.
     *
     * @param Collection<int, covariant Base> $items Changed items
     * @return array<string> Item IDs
     */
    protected function ids( Collection $items ) : array
    {
        return $items->pluck( 'id' )->map( strval( ... ) )->all();
    }
}
