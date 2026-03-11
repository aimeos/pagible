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
                    $this->elementId = $this->createElement( $lang );
                    $root = $this->createRoot( $lang );
                    $this->createTree( $root, $lang );
                } );
            } );
        } );
    }


    /**
     * Create an image file and publish it
     */
    protected function createFile( string $lang ): string
    {
        $name = trim( preg_replace( '/[^\p{L}\p{N} ]/u', '', $this->faker->realTextBetween( 10, 30 ) ) );

        $file = File::forceCreate( [
            'mime' => 'image/png',
            'lang' => $lang,
            'name' => $name,
            'path' => 'https://placehold.co/1500x1000',
            'previews' => ['500' => 'https://placehold.co/500x333', '1000' => 'https://placehold.co/1000x666'],
            'editor' => $this->editor,
        ] );

        $version = $file->versions()->forceCreate( [
            'lang' => $lang,
            'data' => [
                'mime' => 'image/png',
                'lang' => $lang,
                'name' => $name,
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
            'type' => 'text',
            'name' => "Footer ({$lang})",
            'data' => ['type' => 'text', 'data' => ['text' => $text]],
            'editor' => $this->editor,
        ] );

        $version = $element->versions()->forceCreate( [
            'lang' => $lang,
            'data' => [
                'lang' => $lang,
                'type' => 'text',
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
     * Create the root page for a language
     */
    protected function createRoot( string $lang ): Page
    {
        $fileId = $this->createFile( $lang );

        $content = [
            ['id' => Utils::uid(), 'type' => 'heading', 'group' => 'main', 'data' => ['title' => $this->faker->realTextBetween( 20, 80 )]],
            ['id' => Utils::uid(), 'type' => 'image', 'group' => 'main', 'data' => ['file' => ['id' => $fileId, 'type' => 'file']]],
            ['id' => Utils::uid(), 'type' => 'text', 'group' => 'main', 'data' => ['text' => $this->faker->realTextBetween( 200, 500 )]],
            ['type' => 'ref', 'id' => $this->elementId, 'group' => 'footer'],
        ];

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

        $page = Page::forceCreate( $data + ['content' => $content] );
        $page->makeRoot();

        $version = $page->versions()->forceCreate( [
            'lang' => $lang,
            'data' => $data,
            'aux' => ['content' => $content],
            'published' => false,
            'editor' => $this->editor,
        ] );

        $version->files()->attach( $fileId );
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
            $level1 = $this->createPage( $root, $lang, 1, $i );

            for( $j = 0; $j < 10; $j++ )
            {
                $level2 = $this->createPage( $level1, $lang, 2, $i * 10 + $j );
                echo '.';

                DB::connection( config( 'cms.db', 'sqlite' ) )->transaction( function() use ( $level2, $lang, $i, $j ) {
                    for( $k = 0; $k < 100; $k++ )
                    {
                        $this->createPage( $level2, $lang, 3, $i * 1000 + $j * 100 + $k );
                    }
                } );
            }
        }

        echo "\n";
    }


    /**
     * Create a single page with content, version, and publish it
     */
    protected function createPage( Page $parent, string $lang, int $level, int $index ): Page
    {
        $name = match( $level ) {
            1 => trim( preg_replace( '/[^\p{L}\p{N} ]/u', '', $this->faker->realTextBetween( 5, 15 ) ) ),
            2 => trim( preg_replace( '/[^\p{L}\p{N} ]/u', '', $this->faker->realTextBetween( 10, 25 ) ) ),
            default => trim( preg_replace( '/[^\p{L}\p{N} ]/u', '', $this->faker->realTextBetween( 15, 40 ) ) ),
        };
        $path = Utils::slugify( $name ) . '-' . $index;

        $fileId = $this->createFile( $lang );

        $content = [
            ['id' => Utils::uid(), 'type' => 'heading', 'group' => 'main', 'data' => ['title' => $this->faker->realTextBetween( 20, 80 )]],
            ['id' => Utils::uid(), 'type' => 'image', 'group' => 'main', 'data' => ['file' => ['id' => $fileId, 'type' => 'file']]],
            ['id' => Utils::uid(), 'type' => 'text', 'group' => 'main', 'data' => ['text' => $this->faker->realTextBetween( 200, 500 )]],
            ['id' => Utils::uid(), 'type' => 'text', 'group' => 'main', 'data' => ['text' => $this->faker->realTextBetween( 200, 500 )]],
            ['id' => Utils::uid(), 'type' => 'text', 'group' => 'main', 'data' => ['text' => $this->faker->realTextBetween( 200, 500 )]],
            ['type' => 'ref', 'id' => $this->elementId, 'group' => 'footer'],
        ];

        $title = trim( preg_replace( '/[^\p{L}\p{N} ]/u', '', $this->faker->realTextBetween( 20, 60 ) ) );

        $data = [
            'lang' => $lang,
            'name' => $name,
            'title' => $title,
            'path' => $path,
            'status' => 1,
            'editor' => $this->editor,
        ];

        $page = Page::forceCreate( $data + ['content' => $content] );
        $page->appendToNode( $parent )->save();
        $page->elements()->attach( $this->elementId );

        $version = $page->versions()->forceCreate( [
            'lang' => $lang,
            'data' => $data,
            'aux' => ['content' => $content],
            'published' => false,
            'editor' => $this->editor,
        ] );

        $version->files()->attach( $fileId );
        $page->forceFill( ['latest_id' => $version->id] )->saveQuietly();
        $page->publish( $version );

        return $page;
    }
}
