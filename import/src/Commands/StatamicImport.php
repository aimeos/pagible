<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Commands;

use Aimeos\Cms\Commands;
use Aimeos\Cms\Concerns\PendingVersions;
use Aimeos\Cms\Concerns\Statamic\Assets;
use Aimeos\Cms\Concerns\Statamic\Entries;
use Aimeos\Cms\Concerns\Statamic\Index;
use Aimeos\Cms\Concerns\Statamic\Redirects;
use Aimeos\Cms\Concerns\Statamic\Reports;
use Aimeos\Cms\Concerns\Statamic\Sites;
use Aimeos\Cms\Concerns\Statamic\Translations;
use Aimeos\Cms\Concerns\Statamic\Vanished;
use Aimeos\Cms\Import\Statamic\Content;
use Aimeos\Cms\Import\Statamic\Meta;
use Aimeos\Cms\Import\Statamic\Reader;
use Aimeos\Cms\Import\Variants;
use Aimeos\Cms\Models\PageView;
use Illuminate\Support\Facades\DB;


class StatamicImport extends Commands
{
    use PendingVersions;
    use Assets;
    use Entries;
    use Index;
    use Redirects;
    use Reports;
    use Sites;
    use Translations;
    use Vanished;

    /** Maximum number of entry IDs listed in warnings */
    protected const LIST_SIZE = 10;

    /** Number of tree nodes or entries whose data is fetched and decoded at once */
    protected const TREE_SIZE = 100;

    /** Fields of the entries containing the featured image, asset fields with a single asset of the blueprint are used too */
    protected const IMAGE_FIELDS = ['featured_image', 'hero_image', 'image', 'cover'];

    /** Fields of the entries containing the social media image, which aren't used as featured image */
    protected const META_IMAGE_FIELDS = ['og_image', 'twitter_image'];

    /** Regular expression matching the file extensions of images */
    protected const IMAGE_PATTERN = '/\.(?:avif|gif|jpe?g|png|svg|webp)$/i';

    /**
     * Command name
     */
    protected $signature = 'cms:statamic-import
        {--connection=statamic : Database connection name for the Statamic database}
        {--domain= : Domain name for the imported pages}
        {--lang= : Language code for the imported pages, the language of the site by default or "en"}
        {--tenant= : Tenant ID for multi-tenant setups}
        {--editor=statamic-import : Editor name for imported records}
        {--file-base=* : Base URL of the assets, for all containers (e.g. https://example.com/assets) or per container (e.g. --file-base=images=https://cdn.example.com/images)}
        {--container= : Handle of the asset container of asset fields without container, the only container of the asset_containers table or "assets" by default}
        {--collections=pages : Comma-separated list of Statamic collections to import}
        {--fields=body,content,article,text,page_builder,page_sections,blocks : Comma-separated list of the entry fields containing the content}
        {--site= : Statamic site handle to import, the first site by default or "default"}
        {--language=* : Statamic site handle imported as page translation, with its language code and optional URL of a separate domain or URL path of the site if the sites aren\'t stored in the database (e.g. --language=de, --language=de:de, --language=de:de:/de/ or --language=fr:fr:https://example.fr/)}
        {--since= : Only import the entries changed since the date (e.g. 2026-01-31), the pages of the others are kept as imported before}
        {--dry-run : Show what would be imported without making changes}';

    /**
     * Command description
     */
    protected $description = 'Imports Statamic entries into Pagible CMS pages';

    protected Content $content;
    protected Meta $meta;
    protected Reader $reader;
    protected ?PageView $root = null;
    protected string $container = 'assets';
    /** Handle of the imported Statamic site */
    protected string $site = '';
    /** ID of the entry imported as root page */
    protected int|string|null $homeId = null;
    /** @var array<string, array{lang: string, domain: string}> Languages of the translations by site handle */
    protected array $languages = [];
    /** @var array<int|string, true> IDs of the entries whose import was attempted, they aren't imported again */
    protected array $attempted = [];


    /**
     * Execute command
     */
    public function handle(): void
    {
        if( !$this->init( 'Statamic' ) || !$this->sinceOption() || !$this->siteOptions() || !$this->parseOptions() ) {
            return;
        }

        $warn = fn( string $key, string $msg ) => $this->warnOnce( $key, '  ' . $msg );

        $this->reader = new Reader( DB::connection( $this->connection ), $this->site, $this->listOption( 'collections' ) );
        $this->content = new Content( $this->importAsset( ... ), $this->reference( ... ), $this->container,
            $this->listOption( 'fields' ) ?: Content::FIELDS, $warn );
        $this->meta = new Meta( fn( string $asset ) => $this->importAsset( $asset ), $this->container, $warn );
        $this->root = $this->pendingVariants = null;

        $this->index();
        $this->checkSource();

        if( $this->entries === [] ) {
            $this->warn( 'No Statamic entries found.' );
            return;
        }

        $this->info( 'Found ' . count( $this->entries ) . ' Statamic entries.' );

        if( $this->option( 'dry-run' ) ) {
            $this->printDryRun();
            return;
        }

        try {
            $this->importCollections();
            $this->disableVanished();
        } finally {
            $this->forgetDownloads();
        }
    }


    /**
     * Shows warnings about the Statamic data which isn't imported or only partly.
     */
    protected function checkSource(): void
    {
        foreach( $this->unroutable as $collection => $count ) {
            $this->warn( "Skipped {$count} entries of the \"{$collection}\" collection because the collection has no route for the \"{$this->site}\" site, so they have no URLs" );
        }

        $collections = array_values( array_unique( $this->collectionOf ) );

        foreach( $collections as $collection )
        {
            if( !$this->reader->hasSettings( $collection ) ) {
                $this->warn( "The settings of the \"{$collection}\" collection aren't stored in the database, so its routes, tree, mount, date behavior and protection are ignored. "
                    . 'Move them to the database by "php please eloquent:import-collections" in your Statamic installation' );
            } elseif( $this->reader->isStructured( $collection ) && !isset( $this->trees[$collection] ) ) {
                $this->warn( "The tree of the \"{$collection}\" collection isn't stored in the database for the \"{$this->site}\" site, so its entries aren't ordered and nested. "
                    . 'Move it to the database by "php please eloquent:import-collections" in your Statamic installation' );
            } elseif( isset( $this->parentTrees[$collection] ) ) {
                $this->warn( "The tree of the \"{$collection}\" collection isn't stored in the database for the \"{$this->site}\" site, so its entries are nested by their parents but not ordered like in the tree. "
                    . 'Move it to the database by "php please eloquent:import-collections" in your Statamic installation' );
            }
        }

        foreach( $collections as $collection )
        {
            if( !$this->reader->hasBlueprints( $collection ) ) {
                $this->warn( "The blueprints of the \"{$collection}\" collection aren't stored in the database, so the settings of its Markdown, Text, asset and Grid fields are unknown and guessed from the field names. "
                    . 'Move them to the database by "php please eloquent:import-blueprints" in your Statamic installation' );
            }
        }

        if( $this->languages !== [] && ( $ids = $this->reader->unrelated( array_keys( $this->languages ) ) ) !== [] )
        {
            $list = implode( ', ', array_slice( $ids, 0, static::LIST_SIZE ) ) . ( count( $ids ) > static::LIST_SIZE ? ', …' : '' );
            $this->warn( 'Skipped ' . count( $ids ) . " entries of the translated sites because they aren't localizations of entries in the \"{$this->site}\" site: {$list}" );
        }
    }


    /**
     * Returns the comma separated values of the option.
     *
     * @return array<int, string> Non-empty values
     */
    protected function listOption( string $name ): array
    {
        return array_values( array_filter( array_map( 'trim', explode( ',', $this->stringOption( $name ) ) ) ) );
    }


    /**
     * Reads the asset container, the base URLs of the assets and the languages of the translations.
     *
     * @return bool TRUE if the options are valid, FALSE if not
     */
    protected function parseOptions(): bool
    {
        // Statamic uses the only asset container for asset fields without container
        $containers = Reader::containerHandles( DB::connection( $this->connection ) ) ?? [];
        $this->container = $this->stringOption( 'container' ) ?: ( count( $containers ) === 1 ? $containers[0] : 'assets' );
        $this->fileBases = [];
        $this->fileBase = '';

        foreach( (array) $this->option( 'file-base' ) as $value )
        {
            if( !is_string( $value ) || ( $value = trim( $value ) ) === '' ) {
                continue;
            }

            // base URLs of containers are prefixed by the container handle, e.g. "images=https://cdn.example.com/images"
            if( preg_match( '/^([\w-]+)=(.+)$/s', $value, $match ) ) {
                $this->fileBases[$match[1]] = rtrim( $match[2], '/' );
            } else {
                $this->fileBase = rtrim( $value, '/' );
            }
        }

        $values = [];
        $this->prefixes = [];

        foreach( (array) $this->option( 'language' ) as $value )
        {
            $value = $this->siteLanguage( $value );

            if( is_string( $value ) && count( $parts = explode( ':', trim( $value ), 3 ) ) >= 2 )
            {
                // the URL path of the site like "/de/" prefixes the URLs of its entries in Statamic
                $this->prefixes[$parts[0]] = $this->prefix( $parts[2] ?? $this->sites[$parts[0]]['url'] ?? '' );

                // URLs without domain like "/de/" only set the path of the site
                if( isset( $parts[2] ) && preg_match( '#^/(?!/)#', $parts[2] ) ) {
                    $value = $parts[0] . ':' . $parts[1];
                }
            }

            $values[] = $value;
        }

        try {
            $this->languages = Variants::options( $values, 'Statamic' );
        } catch( \InvalidArgumentException $e ) {
            $this->error( $e->getMessage() );
            return false;
        }

        return true;
    }


}
