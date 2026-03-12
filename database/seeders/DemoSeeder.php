<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Database\Seeders;

use Illuminate\Support\Facades\DB;
use Aimeos\Cms\Models\Element;
use Aimeos\Cms\Models\File;
use Aimeos\Cms\Models\Page;
use Aimeos\Cms\Utils;


class DemoSeeder
{
    private const LOCALES = [
        'en' => 'en_US', 'de' => 'de_DE', 'fr' => 'fr_FR',
        'es' => 'es_ES', 'it' => 'it_IT', 'nl' => 'nl_NL',
        'pt' => 'pt_PT', 'pl' => 'pl_PL', 'ja' => 'ja_JP',
        'zh' => 'zh_CN', 'ko' => 'ko_KR', 'ru' => 'ru_RU',
        'ar' => 'ar_SA', 'cs' => 'cs_CZ', 'da' => 'da_DK',
        'fi' => 'fi_FI', 'hu' => 'hu_HU', 'nb' => 'nb_NO',
        'sv' => 'sv_SE', 'tr' => 'tr_TR', 'uk' => 'uk_UA',
    ];

    private \Faker\Generator $faker;
    private string $editor;
    private string $domain;
    private string $fileId;
    private string $elementId;


    /**
     * Generate demo data for a single language
     *
     * @param string $lang Language code
     * @param string $domain Domain name
     * @param string $editor Editor name
     */
    public function run( string $lang, string $domain, string $editor ): void
    {
        $this->domain = $domain;
        $this->editor = $editor;
        $this->faker = \Faker\Factory::create( self::LOCALES[$lang] ?? 'en_US' );

        Page::withoutSyncingToSearch( function() use ( $lang ) {
            Element::withoutSyncingToSearch( function() use ( $lang ) {
                File::withoutSyncingToSearch( function() use ( $lang ) {
                    $this->fileId = $this->createFile( $lang );
                    $this->elementId = $this->createElement( $lang );
                    $root = $this->createRoot( $lang );
                    $this->createTree( $root, $lang );
                } );
            } );
        } );
    }


    /**
     * Create a shared image file for the language
     */
    protected function createFile( string $lang ): string
    {
        $file = File::forceCreate( [
            'mime' => 'image/png',
            'lang' => $lang,
            'name' => $this->faker->sentence( 3 ),
            'path' => 'https://placehold.co/1500x1000',
            'previews' => ['500' => 'https://placehold.co/500x333', '1000' => 'https://placehold.co/1000x666'],
            'editor' => $this->editor,
        ] );

        $version = $file->versions()->forceCreate( [
            'lang' => $lang,
            'data' => [
                'mime' => 'image/png',
                'lang' => $lang,
                'name' => $file->name,
                'path' => 'https://placehold.co/1500x1000',
                'previews' => ['500' => 'https://placehold.co/500x333', '1000' => 'https://placehold.co/1000x666'],
            ],
            'published' => false,
            'editor' => $this->editor,
        ] );

        $file->forceFill( ['latest_id' => $version->id] )->saveQuietly();
        $file->publish( $version );

        return $file->id;
    }


    /**
     * Create a shared footer element for the language
     */
    protected function createElement( string $lang ): string
    {
        $text = $this->faker->company() . ' — ' . $this->faker->catchPhrase();

        $element = Element::forceCreate( [
            'lang' => $lang,
            'type' => 'footer',
            'name' => "Footer ({$lang})",
            'data' => ['type' => 'footer', 'data' => ['text' => $text]],
            'editor' => $this->editor,
        ] );

        $version = $element->versions()->forceCreate( [
            'lang' => $lang,
            'data' => [
                'lang' => $lang,
                'type' => 'footer',
                'name' => "Footer ({$lang})",
                'data' => ['text' => $text],
            ],
            'published' => false,
            'editor' => $this->editor,
        ] );

        $element->forceFill( ['latest_id' => $version->id] )->saveQuietly();
        $element->publish( $version );

        return $element->id;
    }


    /**
     * Generate a meta-tags structure with a realistic description
     */
    protected function metaDescription(): array
    {
        return [
            'meta-tags' => [
                'id' => Utils::uid(),
                'type' => 'meta-tags',
                'group' => 'basic',
                'data' => ['description' => $this->faker->realText( 160 )],
            ],
        ];
    }


    /**
     * Create the root page for a language
     */
    protected function createRoot( string $lang ): Page
    {
        $content = [
            ['id' => Utils::uid(), 'type' => 'heading', 'group' => 'main', 'data' => ['title' => $this->faker->sentence()]],
            ['id' => Utils::uid(), 'type' => 'image', 'group' => 'main', 'data' => ['image' => ['id' => $this->fileId, 'type' => 'file'], 'title' => $this->faker->sentence()]],
            ['id' => Utils::uid(), 'type' => 'paragraph', 'group' => 'main', 'data' => ['text' => $this->faker->paragraphs( 3, true )]],
            ['type' => 'ref', 'id' => $this->elementId],
        ];

        $meta = $this->metaDescription();

        $data = [
            'lang' => $lang,
            'name' => "Home ({$lang})",
            'title' => "Home ({$lang})",
            'path' => '',
            'tag' => 'root',
            'domain' => $this->domain,
            'status' => 1,
            'editor' => $this->editor,
        ];

        $page = Page::forceCreate( $data + ['content' => $content, 'meta' => $meta] );
        $page->makeRoot();

        $version = $page->versions()->forceCreate( [
            'lang' => $lang,
            'data' => $data,
            'aux' => ['content' => $content, 'meta' => $meta],
            'published' => false,
            'editor' => $this->editor,
        ] );

        $version->files()->attach( $this->fileId );
        $page->forceFill( ['latest_id' => $version->id] )->saveQuietly();
        $page->elements()->attach( $this->elementId );
        $page->publish( $version );

        return $page;
    }


    /**
     * Create the 3-level page tree under a root
     */
    protected function createTree( Page $root, string $lang ): void
    {
        for( $i = 0; $i < 10; $i++ )
        {
            $level1 = $this->createPage( $root, $lang, $i );
            echo '.';

            for( $j = 0; $j < 10; $j++ )
            {
                $level2 = $this->createPage( $level1, $lang, $i * 10 + $j );
                echo '.';

                DB::connection( config( 'cms.db', 'sqlite' ) )->transaction( function() use ( $level2, $lang, $i, $j ) {
                    for( $k = 0; $k < 100; $k++ )
                    {
                        $this->createPage( $level2, $lang, $i * 1000 + $j * 100 + $k );
                        echo '.';
                    }
                } );
            }
        }

        echo "\n";
    }


    /**
     * Create a single page with content, version, and publish it
     */
    protected function createPage( Page $parent, string $lang, int $index ): Page
    {
        $name = rtrim( $this->faker->sentence( 3 ), '.' );
        $path = Utils::slugify( $name ) . '-' . $index;

        $content = [
            ['id' => Utils::uid(), 'type' => 'heading', 'group' => 'main', 'data' => ['title' => $this->faker->sentence()]],
            ['id' => Utils::uid(), 'type' => 'image', 'group' => 'main', 'data' => ['image' => ['id' => $this->fileId, 'type' => 'file'], 'title' => $this->faker->sentence()]],
            ['id' => Utils::uid(), 'type' => 'paragraph', 'group' => 'main', 'data' => ['text' => $this->faker->paragraphs( 3, true )]],
            ['id' => Utils::uid(), 'type' => 'paragraph', 'group' => 'main', 'data' => ['text' => $this->faker->paragraphs( 3, true )]],
            ['id' => Utils::uid(), 'type' => 'paragraph', 'group' => 'main', 'data' => ['text' => $this->faker->paragraphs( 3, true )]],
            ['type' => 'ref', 'id' => $this->elementId],
        ];

        $meta = $this->metaDescription();

        $data = [
            'lang' => $lang,
            'name' => $name,
            'title' => $name,
            'path' => $path,
            'status' => 1,
            'editor' => $this->editor,
        ];

        $page = Page::forceCreate( $data + ['content' => $content, 'meta' => $meta] );
        $page->appendToNode( $parent )->save();
        $page->elements()->attach( $this->elementId );

        $version = $page->versions()->forceCreate( [
            'lang' => $lang,
            'data' => $data,
            'aux' => ['content' => $content, 'meta' => $meta],
            'published' => false,
            'editor' => $this->editor,
        ] );

        $version->files()->attach( $this->fileId );
        $page->forceFill( ['latest_id' => $version->id] )->saveQuietly();
        $page->publish( $version );

        return $page;
    }
}
