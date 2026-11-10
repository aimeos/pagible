<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Commands;

use Aimeos\Cms\Ai;
use Aimeos\Cms\AiClient;
use Aimeos\Cms\AiError;
use Aimeos\Cms\Concerns\ForTenants;
use Aimeos\Cms\Exception;
use Aimeos\Cms\Facades\Files;
use Aimeos\Cms\Facades\Variants;
use Aimeos\Cms\Media;
use Aimeos\Cms\Models\File;
use Aimeos\Cms\Models\PageView;
use Aimeos\Cms\Permission;
use Aimeos\Cms\Schema;
use Aimeos\Cms\Tenancy;
use Aimeos\Cms\Utils;
use Aimeos\Cms\Validation;
use Aimeos\Prisma\Exceptions\PrismaException;
use Illuminate\Console\Command;


class Description extends Command
{
    use ForTenants;


    /**
     * AI operation generating the file descriptions
     */
    private const FILES = AiClient::DESCRIBE;

    /**
     * AI operation generating the page descriptions
     */
    private const PAGES = AiClient::WRITE;

    /**
     * Number of pages or files loaded at once
     */
    private const CHUNK = 50;

    /**
     * Meta entry containing the page description
     */
    private const META = 'meta-tags';

    /**
     * Command name
     */
    protected $signature = 'cms:description
        {--tenant= : Only describe the pages and files of this tenant, use --tenant= for the default (empty) tenant}';

    /**
     * Command description
     */
    protected $description = 'Generates descriptions for pages and files if missing';

    /**
     * AI methods stopped because the provider denied the access
     *
     * @var array<string, bool>
     */
    protected array $stopped = [];


    /**
     * Execute command
     */
    public function handle() : int
    {
        // reused by Artisan::call() within the same process
        $this->stopped = [];

        $pages = AiClient::available( self::PAGES );
        $files = AiClient::available( self::FILES );

        if( !$pages && !$files ) {
            $this->error( sprintf( 'No AI provider configured in "%s" and "%s"', AiClient::config( self::PAGES ), AiClient::config( self::FILES ) ) );
            return self::FAILURE;
        }

        if( !$pages ) {
            $this->warn( sprintf( 'No AI provider configured in "%s", page descriptions are skipped', AiClient::config( self::PAGES ) ) );
        }

        if( !$files ) {
            $this->warn( sprintf( 'No AI provider configured in "%s", file descriptions are skipped', AiClient::config( self::FILES ) ) );
        }

        $tenants = $this->tenants( ['cms_pages', 'cms_files'] );

        if( $tenants === null ) {
            return self::FAILURE;
        }

        $failed = false;

        foreach( $tenants as $tenant ) {
            $failed = !Tenancy::run( $tenant, fn() => $this->tenant( $pages, $files ) ) || $failed;
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }


    /**
     * Returns the generated description of a file if its draft has none yet.
     *
     * @param File $file File model with its latest version
     * @return array<string, string>|null Description by language code or NULL if the draft has a description or can't be described
     * @throws Exception If no description is returned
     * @throws PrismaException If no provider is configured or the request fails
     */
    protected function caption( File $file ) : ?array
    {
        // existing draft descriptions aren't overwritten
        if( !empty( (array) $file->latestAux( 'description' ) ) || !Media::describable( (string) $file->latestValue( 'mime' ) ) ) {
            return null;
        }

        $lang = $file->lang ?: ( Utils::locales()[0] ?? app()->getLocale() );
        return [$lang => Ai::describe( Media::media( $file ), $lang )];
    }


    /**
     * Generates and saves the descriptions, fatal errors stop all further requests of the AI method.
     *
     * @template T
     * @param string $op AI method, i.e. "write" or "describe"
     * @param iterable<T> $items Pages or files to describe
     * @param \Closure(T): string $name Returns the name of the item shown in the error message
     * @param \Closure(T): void $fn Generates and saves the description of the item
     * @return bool TRUE if all descriptions have been generated, FALSE if one failed
     */
    protected function each( string $op, iterable $items, \Closure $name, \Closure $fn ) : bool
    {
        $failed = false;

        foreach( $items as $item )
        {
            try
            {
                $fn( $item );
            }
            catch( \Throwable $e )
            {
                $this->error( $name( $item ) . ': ' . AiError::redact( $e->getMessage() ) );
                $failed = true;

                if( AiError::fatal( $e ) )
                {
                    $this->stop( $op );
                    return false;
                }
            }
        }

        return !$failed;
    }


    /**
     * Generates descriptions for files without one
     *
     * @return bool TRUE if all descriptions have been generated, FALSE if one failed
     */
    protected function files() : bool
    {
        // the draft is checked by caption()
        $files = File::select( [...File::SELECT_COLUMNS, 'lang'] )
            ->with( 'latest' )
            ->where( Media::undescribed( ... ) )
            ->lazyById( self::CHUNK );

        return $this->each( self::FILES, $files, fn( File $file ) => (string) $file->name, function( File $file ) {
            if( $desc = $this->caption( $file ) ) {
                Files::save( (string) $file->id, ['description' => $desc], null, $file->latest_id );
            }
        } );
    }


    /**
     * Returns the draft meta data of the page with a generated meta description if it has none yet.
     *
     * @param PageView $page Page variant with its latest version
     * @return array<string, mixed>|null Meta data with the new description or NULL if a description exists or the page has no content
     * @throws Exception If the content is too large or no text is returned
     * @throws PrismaException If no provider is configured or the request fails
     */
    protected function meta( PageView $page ) : ?array
    {
        $meta = (array) $page->latestAux( 'meta' );
        // entries are objects or arrays depending on how the version was loaded
        $data = (array) ( ( (array) ( $meta[self::META] ?? [] ) )['data'] ?? [] );

        if( !empty( $data['description'] ) ) {
            return null;
        }

        // the shared elements are only loaded for pages which need a description
        $content = $page->latest ? (string) $page->latest->loadMissing( 'elements' ) : (string) $page;

        if( trim( $content ) === '' ) {
            return null;
        }

        $limit = Schema::field( 'meta', self::META, 'description' )['max'] ?? null;
        $max = is_numeric( $limit ) && $limit > 0 ? (int) $limit : null;

        $data['description'] = Ai::meta( (string) $page->latestValue( 'title' ), $content, $page->lang, $max );
        $meta[self::META] = Validation::entry( self::META, $data, 'meta' );

        return $meta;
    }


    /**
     * Generates meta descriptions for pages without one
     *
     * @return bool TRUE if all descriptions have been generated, FALSE if one failed
     */
    protected function pages() : bool
    {
        // all language variants have their own meta description
        $pages = PageView::allVariants()
            ->select( PageView::SELECT_COLUMNS )
            ->with( 'latest' )
            ->where( 'status', '>', 0 )
            ->lazyById( self::CHUNK, 'variant_id' );

        return $this->each( self::PAGES, $pages, fn( PageView $page ) => (string) $page->latestValue( 'title' ), function( PageView $page ) {
            if( $meta = $this->meta( $page ) ) {
                Variants::save( (string) $page->id, (string) $page->lang, ['meta' => $meta], null, $page->latest_id );
            }
        } );
    }


    /**
     * Stops all further requests of the AI method, also for the following tenants.
     *
     * @param string $op AI method, i.e. "write" or "describe"
     */
    protected function stop( string $op ) : void
    {
        $this->error( sprintf( 'The AI service in "%s" denied the access or its quota is exhausted, stopped', AiClient::config( $op ) ) );
        $this->stopped[$op] = true;
    }


    /**
     * Generates the missing descriptions of the current tenant.
     *
     * @param bool $pages TRUE to describe the pages
     * @param bool $files TRUE to describe the files
     * @return bool TRUE if all descriptions have been generated, FALSE if one failed
     */
    protected function tenant( bool $pages, bool $files ) : bool
    {
        // saved as system drafts to be reviewed before publishing
        return Permission::system( function() use ( $pages, $files ) {
            // both run even if the first one fails
            $pagesDone = !$pages || ( empty( $this->stopped[self::PAGES] ) && $this->pages() );
            $filesDone = !$files || ( empty( $this->stopped[self::FILES] ) && $this->files() );

            return $pagesDone && $filesDone;
        } );
    }
}
