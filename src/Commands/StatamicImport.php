<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Aimeos\Cms\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Aimeos\Cms\Models\Element;
use Aimeos\Cms\Models\File;
use Aimeos\Cms\Models\Page;
use Aimeos\Cms\Utils;


class StatamicImport extends Command
{
    /**
     * Command name
     */
    protected $signature = 'cms:statamic-import
        {--connection=statamic : Database connection name for the Statamic database}
        {--domain= : Domain name for the imported pages}
        {--lang=en : Language code for the imported pages}
        {--tenant= : Tenant ID for multi-tenant setups}
        {--editor=statamic-import : Editor name for imported records}
        {--file-base= : Base URL for Statamic assets (e.g. https://example.com/assets)}
        {--collections=pages : Comma-separated list of Statamic collections to import}
        {--site=en : Statamic site handle to import}
        {--dry-run : Show what would be imported without making changes}';

    /**
     * Command description
     */
    protected $description = 'Imports Statamic entries into Pagible CMS pages';

    protected string $stConnection;
    protected string $domain;
    protected string $lang;
    protected string $editor;
    protected string $fileBase;
    protected string $site;
    /** @var Collection<int|string, mixed> */
    protected Collection $assetsMeta;
    /** @var Collection<int|string, mixed> */
    protected Collection $createdFiles;


    /**
     * Execute command
     */
    public function handle(): void
    {
        $this->stConnection = strval( $this->option( 'connection' ) ); // @phpstan-ignore argument.type
        $this->domain = strval( $this->option( 'domain' ) ?: '' ); // @phpstan-ignore argument.type
        $this->lang = strval( $this->option( 'lang' ) ); // @phpstan-ignore argument.type
        $this->editor = strval( $this->option( 'editor' ) ); // @phpstan-ignore argument.type
        $this->fileBase = rtrim( strval( $this->option( 'file-base' ) ?: '' ), '/' ); // @phpstan-ignore argument.type
        $this->site = strval( $this->option( 'site' ) ); // @phpstan-ignore argument.type
        $this->createdFiles = Collection::make();

        $this->setupTenant();

        if( !$this->check() ) {
            return;
        }

        $entries = $this->fetchEntries();

        if( $entries->isEmpty() ) {
            $this->warn( 'No Statamic entries found.' );
            return;
        }

        $this->info( "Found {$entries->count()} Statamic entries." );

        if( $this->option( 'dry-run' ) ) {
            $this->printDryRun( $entries );
            return;
        }

        $this->assetsMeta = $this->fetchAssetsMeta();

        $tree = $this->fetchTree();
        $root = $this->getOrCreateRoot();

        $this->importEntries( $entries, $tree, $root );
    }


    /**
     * Converts a Bard blockquote node to markdown.
     *
     * @param array<string, mixed> $node
     */
    protected function bardBlockquoteToMarkdown( array $node ): string
    {
        $inner = '';
        foreach( $node['content'] ?? [] as $child ) {
            $inner .= $this->bardNodeToMarkdown( $child );
        }

        $lines = explode( "\n", trim( $inner ) );

        return implode( "\n", array_map( fn( $l ) => '> ' . $l, $lines ) ) . "\n\n";
    }


    /**
     * Converts a Bard bullet list node to markdown.
     *
     * @param array<string, mixed> $node
     */
    protected function bardBulletListToMarkdown( array $node ): string
    {
        $md = '';
        foreach( $node['content'] ?? [] as $item ) {
            $inner = '';
            foreach( $item['content'] ?? [] as $child ) {
                $inner .= $this->bardNodeToMarkdown( $child );
            }
            $md .= '- ' . trim( $inner ) . "\n";
        }
        return $md . "\n";
    }


    /**
     * Converts Bard ProseMirror JSON content into Pagible content elements.
     *
     * @param array<int|string, mixed> $bardContent
     * @return array{elements: array<int, array<string, mixed>>, fileIds: array<int, string>}
     */
    protected function bardToElements( array $bardContent ): array
    {
        $elements = [];
        $fileIds = [];
        $textBuffer = '';

        foreach( $bardContent as $node )
        {
            $type = $node['type'] ?? '';

            if( $type === 'heading' ) {
                $this->flushTextBuffer( $textBuffer, $elements );
                $elements[] = [
                    'id' => Utils::uid(),
                    'type' => 'heading',
                    'group' => 'main',
                    'data' => [
                        'level' => $node['attrs']['level'] ?? 2,
                        'title' => $this->bardTextContent( $node ),
                    ],
                ];
                continue;
            }

            if( $type === 'codeBlock' ) {
                $this->flushTextBuffer( $textBuffer, $elements );
                $elements[] = [
                    'id' => Utils::uid(),
                    'type' => 'code',
                    'group' => 'main',
                    'data' => [
                        'language' => $node['attrs']['language'] ?? '',
                        'text' => $this->bardTextContent( $node ),
                    ],
                ];
                continue;
            }

            if( $type === 'set' ) {
                $this->flushTextBuffer( $textBuffer, $elements );
                $result = $this->convertBardSet( $node );
                if( $result ) {
                    $elements = array_merge( $elements, $result['elements'] );
                    $fileIds = array_merge( $fileIds, $result['fileIds'] );
                }
                continue;
            }

            if( $type === 'image' ) {
                $this->flushTextBuffer( $textBuffer, $elements );
                $result = $this->convertBardImage( $node );
                if( $result ) {
                    $elements = array_merge( $elements, $result['elements'] );
                    $fileIds = array_merge( $fileIds, $result['fileIds'] );
                }
                continue;
            }

            if( $type === 'horizontalRule' ) {
                $this->flushTextBuffer( $textBuffer, $elements );
                $textBuffer = '';
                continue;
            }

            $textBuffer .= $this->bardNodeToMarkdown( $node );
        }

        $this->flushTextBuffer( $textBuffer, $elements );

        return ['elements' => $elements, 'fileIds' => array_unique( $fileIds )];
    }


    /**
     * Extracts plain text content from a Bard node.
     *
     * @param array<string, mixed> $node
     */
    protected function bardTextContent( array $node ): string
    {
        $text = '';
        foreach( $node['content'] ?? [] as $child ) {
            if( ( $child['type'] ?? '' ) === 'text' ) {
                $text .= $child['text'] ?? '';
            } else {
                $text .= $this->bardTextContent( $child );
            }
        }
        return $text;
    }


    /**
     * Converts a single Bard ProseMirror node to markdown text.
     *
     * @param array<string, mixed> $node
     */
    protected function bardNodeToMarkdown( array $node ): string
    {
        $type = $node['type'] ?? '';

        if( $type === 'text' ) {
            return $this->bardTextWithMarks( $node );
        }

        if( $type === 'paragraph' ) {
            return $this->bardParagraphToMarkdown( $node );
        }

        if( $type === 'bulletList' ) {
            return $this->bardBulletListToMarkdown( $node );
        }

        if( $type === 'orderedList' ) {
            return $this->bardOrderedListToMarkdown( $node );
        }

        if( $type === 'blockquote' ) {
            return $this->bardBlockquoteToMarkdown( $node );
        }

        if( $type === 'hardBreak' ) {
            return "\n";
        }

        $inner = '';
        foreach( $node['content'] ?? [] as $child ) {
            $inner .= $this->bardNodeToMarkdown( $child );
        }
        return $inner;
    }


    /**
     * Converts a Bard ordered list node to markdown.
     *
     * @param array<string, mixed> $node
     */
    protected function bardOrderedListToMarkdown( array $node ): string
    {
        $md = '';
        $i = $node['attrs']['start'] ?? 1;
        foreach( $node['content'] ?? [] as $item ) {
            $inner = '';
            foreach( $item['content'] ?? [] as $child ) {
                $inner .= $this->bardNodeToMarkdown( $child );
            }
            $md .= $i . '. ' . trim( $inner ) . "\n";
            $i++;
        }
        return $md . "\n";
    }


    /**
     * Converts a Bard paragraph node to markdown.
     *
     * @param array<string, mixed> $node
     */
    protected function bardParagraphToMarkdown( array $node ): string
    {
        $inner = '';
        foreach( $node['content'] ?? [] as $child ) {
            $inner .= $this->bardNodeToMarkdown( $child );
        }
        return $inner . "\n\n";
    }


    /**
     * Applies ProseMirror marks to text.
     *
     * @param array<string, mixed> $node
     */
    protected function bardTextWithMarks( array $node ): string
    {
        $text = $node['text'] ?? '';
        $marks = $node['marks'] ?? [];

        foreach( $marks as $mark )
        {
            $markType = $mark['type'] ?? '';

            $text = match( $markType ) {
                'bold' => '**' . $text . '**',
                'italic' => '*' . $text . '*',
                'code' => '`' . $text . '`',
                'strike' => '~~' . $text . '~~',
                'link' => '[' . $text . '](' . ( $mark['attrs']['href'] ?? '' ) . ')',
                default => $text,
            };
        }

        return $text;
    }


    /**
     * Builds content elements from an entry's data fields.
     *
     * @param array<string, mixed> $data
     * @return array{elements: array<int, array<string, mixed>>, fileIds: array<int, string>}
     */
    protected function buildContent( array $data ): array
    {
        $elements = [];
        $fileIds = [];

        $imageFileId = $this->importEntryImage( $data );
        if( $imageFileId ) {
            $elements[] = [
                'id' => Utils::uid(),
                'type' => 'image',
                'group' => 'main',
                'data' => ['file' => ['id' => $imageFileId, 'type' => 'file']],
            ];
            $fileIds[] = $imageFileId;
        }

        foreach( $this->contentFieldNames() as $field )
        {
            if( empty( $data[$field] ) ) {
                continue;
            }

            $value = $data[$field];

            if( is_array( $value ) && $this->isBardContent( $value ) ) {
                $result = $this->bardToElements( $this->unwrapBardDoc( $value ) );
                $elements = array_merge( $elements, $result['elements'] );
                $fileIds = array_merge( $fileIds, $result['fileIds'] );
                continue;
            }

            if( is_array( $value ) && $this->isReplicatorContent( $value ) ) {
                $result = $this->replicatorToElements( $value );
                $elements = array_merge( $elements, $result['elements'] );
                $fileIds = array_merge( $fileIds, $result['fileIds'] );
                continue;
            }

            if( is_string( $value ) && !empty( trim( $value ) ) ) {
                $result = $this->markdownOrHtmlToElements( $value );
                $elements = array_merge( $elements, $result['elements'] );
                $fileIds = array_merge( $fileIds, $result['fileIds'] );
            }
        }

        return ['elements' => $elements, 'fileIds' => array_unique( $fileIds )];
    }


    /**
     * Builds the page data array.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    protected function buildPageData( object $entry, array $data, string $slug ): array
    {
        return [
            'name' => $data['title'] ?? $entry->slug ?? 'Untitled',
            'title' => $data['title'] ?? $entry->slug ?? 'Untitled',
            'path' => $slug,
            'tag' => 'page',
            'domain' => $this->domain,
            'lang' => $this->lang,
            'status' => $entry->published ? 1 : 0, /** @phpstan-ignore property.notFound */
            'editor' => $this->editor,
        ];
    }


    /**
     * Tests the Statamic database connection.
     */
    protected function check(): bool
    {
        try {
            DB::connection( $this->stConnection )->getPdo();
            return true;
        } catch( \Exception $e ) {
            $this->error( "Cannot connect to Statamic database using connection \"{$this->stConnection}\"." );
            $this->error( "Add a \"{$this->stConnection}\" connection to config/database.php, e.g.:" );
            $this->line( "  '{$this->stConnection}' => [" );
            $this->line( "      'driver' => 'mysql'," );
            $this->line( "      'host' => env('STATAMIC_DB_HOST', '127.0.0.1')," );
            $this->line( "      'database' => env('STATAMIC_DB_DATABASE', 'statamic')," );
            $this->line( "      'username' => env('STATAMIC_DB_USERNAME', 'root')," );
            $this->line( "      'password' => env('STATAMIC_DB_PASSWORD', '')," );
            $this->line( "  ]" );
            return false;
        }
    }


    /**
     * Returns the list of field names that may contain body content.
     *
     * @return string[]
     */
    protected function contentFieldNames(): array
    {
        return ['body', 'content', 'article', 'text', 'page_builder', 'page_sections', 'blocks'];
    }


    /**
     * Converts a Bard image node into a Pagible image element.
     *
     * @param array<string, mixed> $node
     * @return array{elements: array<int, array<string, mixed>>, fileIds: array<int, string>}|null
     */
    protected function convertBardImage( array $node ): ?array
    {
        $src = $node['attrs']['src'] ?? '';

        if( empty( $src ) ) {
            return null;
        }

        $alt = $node['attrs']['alt'] ?? '';
        $fileId = $this->importAssetByPath( $src, $alt );

        if( !$fileId ) {
            return null;
        }

        return [
            'elements' => [[
                'id' => Utils::uid(),
                'type' => 'image',
                'group' => 'main',
                'data' => ['file' => ['id' => $fileId, 'type' => 'file']],
            ]],
            'fileIds' => [$fileId],
        ];
    }


    /**
     * Converts a Bard set node into Pagible content elements.
     *
     * @param array<string, mixed> $node
     * @return array{elements: array<int, array<string, mixed>>, fileIds: array<int, string>}|null
     */
    protected function convertBardSet( array $node ): ?array
    {
        $values = $node['attrs']['values'] ?? [];
        $setType = $values['type'] ?? '';

        return $this->convertSetByType( $setType, $values );
    }


    /**
     * Converts a Replicator block into Pagible content elements.
     *
     * @param array<string, mixed> $block
     * @return array{elements: array<int, array<string, mixed>>, fileIds: array<int, string>}|null
     */
    protected function convertReplicatorBlock( array $block ): ?array
    {
        $enabled = $block['enabled'] ?? true;

        if( !$enabled ) {
            return null;
        }

        $setType = $block['type'] ?? '';

        return $this->convertSetByType( $setType, $block );
    }


    /**
     * Converts a named set/block type into Pagible elements.
     *
     * @param array<string, mixed> $values
     * @return array{elements: array<int, array<string, mixed>>, fileIds: array<int, string>}|null
     */
    protected function convertSetByType( string $setType, array $values ): ?array
    {
        if( in_array( $setType, ['image', 'image_block', 'photo'] ) ) {
            return $this->convertSetImage( $values );
        }

        if( in_array( $setType, ['text', 'text_block', 'content', 'rich_text'] ) ) {
            return $this->convertSetText( $values );
        }

        if( in_array( $setType, ['code', 'code_block'] ) ) {
            return $this->convertSetCode( $values );
        }

        if( in_array( $setType, ['quote', 'blockquote', 'pullquote'] ) ) {
            return $this->convertSetQuote( $values );
        }

        if( in_array( $setType, ['video', 'video_block'] ) ) {
            return $this->convertSetVideo( $values );
        }

        if( in_array( $setType, ['gallery', 'image_gallery', 'slideshow'] ) ) {
            return $this->convertSetGallery( $values );
        }

        if( in_array( $setType, ['callout', 'notice', 'alert'] ) ) {
            return $this->convertSetCallout( $values );
        }

        if( in_array( $setType, ['heading', 'header'] ) ) {
            return $this->convertSetHeading( $values );
        }

        return $this->convertSetFallback( $values );
    }


    /**
     * Converts a callout/notice set into a text element.
     *
     * @param array<string, mixed> $values
     * @return array{elements: array<int, array<string, mixed>>, fileIds: array<int, string>}|null
     */
    protected function convertSetCallout( array $values ): ?array
    {
        $text = $values['callout_text'] ?? $values['text'] ?? $values['body'] ?? '';

        if( empty( $text ) ) {
            return null;
        }

        $label = ucfirst( $values['callout_type'] ?? $values['type'] ?? 'Note' );

        return ['elements' => [[
            'id' => Utils::uid(),
            'type' => 'text',
            'group' => 'main',
            'data' => ['text' => "**{$label}:** {$text}"],
        ]], 'fileIds' => []];
    }


    /**
     * Converts a code set into a code element.
     *
     * @param array<string, mixed> $values
     * @return array{elements: array<int, array<string, mixed>>, fileIds: array<int, string>}|null
     */
    protected function convertSetCode( array $values ): ?array
    {
        $code = $values['code'] ?? $values['text'] ?? $values['snippet'] ?? '';

        if( empty( $code ) ) {
            return null;
        }

        return ['elements' => [[
            'id' => Utils::uid(),
            'type' => 'code',
            'group' => 'main',
            'data' => [
                'language' => $values['language'] ?? $values['lang'] ?? '',
                'text' => $code,
            ],
        ]], 'fileIds' => []];
    }


    /**
     * Converts an unknown set into a text element as fallback.
     *
     * @param array<string, mixed> $values
     * @return array{elements: array<int, array<string, mixed>>, fileIds: array<int, string>}|null
     */
    protected function convertSetFallback( array $values ): ?array
    {
        $text = $values['text'] ?? $values['body'] ?? $values['content'] ?? '';

        if( is_array( $text ) && $this->isBardContent( $text ) ) {
            return $this->bardToElements( $this->unwrapBardDoc( $text ) );
        }

        if( is_string( $text ) && !empty( trim( $text ) ) ) {
            return $this->markdownOrHtmlToElements( $text );
        }

        return null;
    }


    /**
     * Converts a gallery/slideshow set into multiple image elements.
     *
     * @param array<string, mixed> $values
     * @return array{elements: array<int, array<string, mixed>>, fileIds: array<int, string>}|null
     */
    protected function convertSetGallery( array $values ): ?array
    {
        $images = $values['images'] ?? $values['photos'] ?? $values['gallery'] ?? [];
        $elements = [];
        $fileIds = [];

        foreach( (array) $images as $assetPath )
        {
            if( !is_string( $assetPath ) || empty( $assetPath ) ) {
                continue;
            }

            $fileId = $this->importAssetByPath( $assetPath );

            if( $fileId ) {
                $elements[] = [
                    'id' => Utils::uid(),
                    'type' => 'image',
                    'group' => 'main',
                    'data' => ['file' => ['id' => $fileId, 'type' => 'file']],
                ];
                $fileIds[] = $fileId;
            }
        }

        if( empty( $elements ) ) {
            return null;
        }

        return ['elements' => $elements, 'fileIds' => $fileIds];
    }


    /**
     * Converts a heading set into a heading element.
     *
     * @param array<string, mixed> $values
     * @return array{elements: array<int, array<string, mixed>>, fileIds: array<int, string>}|null
     */
    protected function convertSetHeading( array $values ): ?array
    {
        $title = $values['heading'] ?? $values['title'] ?? $values['text'] ?? '';

        if( empty( $title ) ) {
            return null;
        }

        return ['elements' => [[
            'id' => Utils::uid(),
            'type' => 'heading',
            'group' => 'main',
            'data' => [
                'level' => (int) ( $values['level'] ?? 2 ),
                'title' => $title,
            ],
        ]], 'fileIds' => []];
    }


    /**
     * Converts an image set into a Pagible image or image-text element.
     *
     * @param array<string, mixed> $values
     * @return array{elements: array<int, array<string, mixed>>, fileIds: array<int, string>}|null
     */
    protected function convertSetImage( array $values ): ?array
    {
        $assetPath = $values['image'] ?? $values['photo'] ?? $values['file'] ?? '';

        if( is_array( $assetPath ) ) {
            $assetPath = $assetPath[0] ?? '';
        }

        if( empty( $assetPath ) ) {
            return null;
        }

        $alt = $values['alt'] ?? $values['caption'] ?? '';
        $fileId = $this->importAssetByPath( $assetPath, $alt );

        if( !$fileId ) {
            return null;
        }

        $caption = $values['caption'] ?? '';

        if( !empty( $caption ) ) {
            return [
                'elements' => [[
                    'id' => Utils::uid(),
                    'type' => 'image-text',
                    'group' => 'main',
                    'data' => [
                        'text' => $caption,
                        'file' => ['id' => $fileId, 'type' => 'file'],
                    ],
                ]],
                'fileIds' => [$fileId],
            ];
        }

        return [
            'elements' => [[
                'id' => Utils::uid(),
                'type' => 'image',
                'group' => 'main',
                'data' => ['file' => ['id' => $fileId, 'type' => 'file']],
            ]],
            'fileIds' => [$fileId],
        ];
    }


    /**
     * Converts a quote set into a text element.
     *
     * @param array<string, mixed> $values
     * @return array{elements: array<int, array<string, mixed>>, fileIds: array<int, string>}|null
     */
    protected function convertSetQuote( array $values ): ?array
    {
        $quote = $values['quote'] ?? $values['quote_text'] ?? $values['text'] ?? '';

        if( empty( $quote ) ) {
            return null;
        }

        $cite = $values['cite'] ?? $values['attribution'] ?? $values['source'] ?? $values['quote_source'] ?? '';
        $md = '> ' . $quote;

        if( !empty( $cite ) ) {
            $md .= "\n> -- *{$cite}*";
        }

        return ['elements' => [[
            'id' => Utils::uid(),
            'type' => 'text',
            'group' => 'main',
            'data' => ['text' => $md],
        ]], 'fileIds' => []];
    }


    /**
     * Converts a text set into Pagible elements.
     *
     * @param array<string, mixed> $values
     * @return array{elements: array<int, array<string, mixed>>, fileIds: array<int, string>}|null
     */
    protected function convertSetText( array $values ): ?array
    {
        $text = $values['text'] ?? $values['body'] ?? $values['content'] ?? '';

        if( is_array( $text ) && $this->isBardContent( $text ) ) {
            return $this->bardToElements( $this->unwrapBardDoc( $text ) );
        }

        if( is_string( $text ) && !empty( trim( $text ) ) ) {
            return $this->markdownOrHtmlToElements( $text );
        }

        return null;
    }


    /**
     * Converts a video set into an html element with an embed.
     *
     * @param array<string, mixed> $values
     * @return array{elements: array<int, array<string, mixed>>, fileIds: array<int, string>}|null
     */
    protected function convertSetVideo( array $values ): ?array
    {
        $url = $values['url'] ?? $values['video_url'] ?? $values['src'] ?? '';

        if( empty( $url ) ) {
            return null;
        }

        return ['elements' => [[
            'id' => Utils::uid(),
            'type' => 'html',
            'group' => 'main',
            'data' => ['text' => '<iframe src="' . htmlspecialchars( $url, ENT_QUOTES, 'UTF-8' ) . '" frameborder="0" allowfullscreen></iframe>'],
        ]], 'fileIds' => []];
    }


    /**
     * Creates a File record with a published version.
     */
    protected function createFile( string $mime, string $name, string $path ): string
    {
        if( $existing = $this->createdFiles->get( $path ) ) {
            return $existing;
        }

        $file = File::forceCreate( [
            'mime' => $mime,
            'name' => $name,
            'path' => $path,
            'editor' => $this->editor,
        ] );

        $version = $file->versions()->forceCreate( [
            'data' => ['mime' => $mime, 'name' => $name, 'path' => $path, 'previews' => []],
            'editor' => $this->editor,
        ] );

        $file->forceFill( ['latest_id' => $version->id] )->saveQuietly();
        $file->publish( $version );

        $this->createdFiles->put( $path, $file->id );

        return $file->id;
    }


    /**
     * Creates a Pagible page with content and attaches files.
     *
     * @param array<string, mixed> $pageData
     * @param array<int, array<string, mixed>> $contentElements
     */
    protected function createPage( array $pageData, array $contentElements, Page $parent ): Page
    {
        $page = Page::forceCreate( $pageData + ['content' => $contentElements] );
        $page->appendToNode( $parent )->save();

        return $page;
    }


    /**
     * Creates a version for a page and publishes it.
     *
     * @param array<string, mixed> $pageData
     * @param array<int, array<string, mixed>> $contentElements
     * @param array<int, string> $fileIds
     */
    protected function createVersion( Page $page, array $pageData, array $contentElements, array $fileIds ): void
    {
        $version = $page->versions()->forceCreate( [
            'lang' => $this->lang,
            'data' => $pageData + ['content' => $contentElements],
            'editor' => $this->editor,
        ] );

        if( !empty( $fileIds ) ) {
            $version->files()->attach( $fileIds );
        }

        $page->forceFill( ['latest_id' => $version->id] )->saveQuietly();
        $page->publish( $version );
    }


    /**
     * Decodes the entry data JSON column.
     *
     * @return array<string, mixed>
     */
    protected function decodeEntryData( object $entry ): array
    {
        $data = $entry->data ?? '{}';

        if( is_string( $data ) ) {
            return json_decode( $data, true ) ?: [];
        }

        return (array) $data;
    }


    /**
     * Fetches assets_meta records keyed by path.
     *
     * @return Collection<int|string, mixed>
     */
    protected function fetchAssetsMeta(): Collection
    {
        if( !$this->tableExists( 'assets_meta' ) ) {
            return Collection::make();
        }

        return DB::connection( $this->stConnection )
            ->table( 'assets_meta' )
            ->get()
            ->keyBy( 'path' );
    }


    /**
     * Fetches entries for the configured collections and site.
     *
     * @return Collection<int|string, mixed>
     */
    protected function fetchEntries(): Collection
    {
        $collections = array_map( 'trim', explode( ',', strval( $this->option( 'collections' ) ) ) ); // @phpstan-ignore argument.type

        /** @var Collection<int|string, mixed> */
        return DB::connection( $this->stConnection )
            ->table( 'entries' )
            ->where( 'site', $this->site )
            ->whereIn( 'collection', $collections )
            ->orderBy( 'order', 'asc' )
            ->orderBy( 'date', 'asc' )
            ->get();
    }


    /**
     * Fetches the collection tree for hierarchical import.
     *
     * @return array<int, array<string, mixed>>|null
     */
    protected function fetchTree(): ?array
    {
        if( !$this->tableExists( 'trees' ) ) {
            return null;
        }

        $collections = array_map( 'trim', explode( ',', strval( $this->option( 'collections' ) ) ) ); // @phpstan-ignore argument.type

        $tree = DB::connection( $this->stConnection )
            ->table( 'trees' )
            ->where( 'type', 'collection' )
            ->whereIn( 'handle', $collections )
            ->where( function( $q ) {
                $q->where( 'locale', $this->site )->orWhereNull( 'locale' );
            } )
            ->first();

        if( !$tree || empty( $tree->tree ) ) {
            return null;
        }

        $treeData = is_string( $tree->tree ) ? json_decode( $tree->tree, true ) : (array) $tree->tree;

        return $treeData ?: null;
    }


    /**
     * Flushes accumulated markdown text into a text element.
     *
     * @param array<int, array<string, mixed>> $elements
     */
    protected function flushTextBuffer( string &$buffer, array &$elements ): void
    {
        $text = trim( $buffer );

        if( !empty( $text ) ) {
            $elements[] = [
                'id' => Utils::uid(),
                'type' => 'text',
                'group' => 'main',
                'data' => ['text' => $text],
            ];
        }

        $buffer = '';
    }


    /**
     * Gets or creates the root page.
     */
    protected function getOrCreateRoot(): Page
    {
        $page = Page::where( 'tag', 'root' )->first();

        if( $page ) {
            $this->info( "Using existing root page: {$page->name}" );
            return $page;
        }

        $page = Page::forceCreate( [
            'name' => 'Home',
            'title' => 'Home',
            'path' => '',
            'tag' => 'root',
            'domain' => $this->domain,
            'lang' => $this->lang,
            'status' => 1,
            'editor' => $this->editor,
            'content' => [],
        ] );

        $version = $page->versions()->forceCreate( [
            'lang' => $this->lang,
            'data' => [
                'name' => 'Home',
                'title' => 'Home',
                'path' => '',
                'tag' => 'root',
                'domain' => $this->domain,
                'status' => 1,
                'editor' => $this->editor,
                'content' => [],
            ],
            'editor' => $this->editor,
        ] );

        $page->forceFill( ['latest_id' => $version->id] )->saveQuietly();
        $page->publish( $version );

        $this->info( 'Created root page.' );
        return $page;
    }


    /**
     * Guesses MIME type from file extension.
     */
    protected function guessMimeFromExtension( string $ext ): string
    {
        return match( strtolower( $ext ) ) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'svg' => 'image/svg+xml',
            'pdf' => 'application/pdf',
            'mp4' => 'video/mp4',
            'mp3' => 'audio/mpeg',
            default => 'application/octet-stream',
        };
    }


    /**
     * Converts basic HTML to Markdown.
     */
    protected function htmlToMarkdown( string $html ): string
    {
        $text = $html;

        $text = (string) preg_replace( '/<strong>(.*?)<\/strong>/is', '**$1**', $text );
        $text = (string) preg_replace( '/<b>(.*?)<\/b>/is', '**$1**', $text );
        $text = (string) preg_replace( '/<em>(.*?)<\/em>/is', '*$1*', $text );
        $text = (string) preg_replace( '/<i>(.*?)<\/i>/is', '*$1*', $text );
        $text = (string) preg_replace( '/<a[^>]+href=["\']([^"\']+)["\'][^>]*>(.*?)<\/a>/is', '[$2]($1)', $text );
        $text = (string) preg_replace( '/<code>(.*?)<\/code>/is', '`$1`', $text );
        $text = (string) preg_replace( '/<li[^>]*>(.*?)<\/li>/is', "- $1\n", $text );
        $text = (string) preg_replace( '/<\/?(ul|ol)[^>]*>/is', "\n", $text );
        $text = (string) preg_replace( '/<p[^>]*>(.*?)<\/p>/is', "$1\n\n", $text );
        $text = (string) preg_replace( '/<br\s*\/?>/', "\n", $text );
        $text = strip_tags( $text );
        $text = html_entity_decode( $text, ENT_QUOTES, 'UTF-8' );
        $text = (string) preg_replace( '/\n{3,}/', "\n\n", $text );

        return trim( $text );
    }


    /**
     * Imports an asset by its Statamic path.
     */
    protected function importAssetByPath( string $assetPath, string $alt = '' ): ?string
    {
        if( empty( $assetPath ) ) {
            return null;
        }

        $meta = $this->assetsMeta->get( $assetPath );
        $name = $alt ?: ( $meta->filename ?? basename( $assetPath ) );
        $ext = $meta->extension ?? pathinfo( $assetPath, PATHINFO_EXTENSION );
        $mime = $this->guessMimeFromExtension( $ext );
        $path = $this->resolveAssetPath( $assetPath );

        return $this->createFile( $mime, $name, $path );
    }


    /**
     * Imports entries following the tree hierarchy or flat order.
     *
     * @param Collection<int|string, mixed> $entries
     * @param array<int, array<string, mixed>>|null $tree
     */
    protected function importEntries( Collection $entries, ?array $tree, Page $root ): void
    {
        $entriesById = $entries->keyBy( 'id' );
        $createdPages = Collection::make();
        $imported = 0;

        if( $tree ) {
            $this->importTreeNodes( $tree, $root, $entriesById, $createdPages, $imported );
        }

        foreach( $entriesById as $id => $entry )
        {
            if( $createdPages->has( $id ) ) {
                continue;
            }

            $this->importSingleEntry( $entry, $root, $createdPages, $imported );
        }

        $this->info( "Import complete. {$imported}/{$entries->count()} entries imported." );
    }


    /**
     * Imports the featured image from an entry's data.
     *
     * @param array<string, mixed> $data
     */
    protected function importEntryImage( array $data ): ?string
    {
        $imageField = $data['featured_image'] ?? $data['hero_image'] ?? $data['image'] ?? $data['cover'] ?? null;

        if( empty( $imageField ) ) {
            return null;
        }

        if( is_array( $imageField ) ) {
            $imageField = $imageField[0] ?? '';
        }

        if( !is_string( $imageField ) || empty( $imageField ) ) {
            return null;
        }

        return $this->importAssetByPath( $imageField );
    }


    /**
     * Imports a single entry as a Pagible page.
     *
     * @param Collection<int|string, mixed> $createdPages
     */
    protected function importSingleEntry( object $entry, Page $parent, Collection &$createdPages, int &$imported ): void
    {
        try {
            DB::connection( config( 'cms.db', 'sqlite' ) )->transaction( function() use ( $entry, $parent, &$createdPages, &$imported )
            {
                $data = $this->decodeEntryData( $entry );
                $slug = $entry->slug ?: Utils::slugify( $data['title'] ?? 'untitled' ); /** @phpstan-ignore property.notFound */
                $pageData = $this->buildPageData( $entry, $data, $slug );
                $content = $this->buildContent( $data );

                $page = $this->createPage( $pageData, $content['elements'], $parent );
                $this->createVersion( $page, $pageData, $content['elements'], $content['fileIds'] );

                if( $entry->created_at ) { /** @phpstan-ignore property.notFound */
                    $page->update( ['created_at' => $entry->created_at] );
                }

                $createdPages->put( $entry->id, $page ); /** @phpstan-ignore property.notFound */
                $imported++;
                $this->info( "  Imported: {$pageData['name']} (/{$slug})" );
            } );
        } catch( \Exception $e ) {
            $title = $this->decodeEntryData( $entry )['title'] ?? $entry->slug ?? $entry->id; /** @phpstan-ignore property.notFound */
            $this->error( "  Failed to import [{$entry->id}] {$title}: " . $e->getMessage() ); /** @phpstan-ignore property.notFound */
        }
    }


    /**
     * Recursively imports tree nodes following hierarchy.
     *
     * @param array<int, array<string, mixed>> $treeNodes
     * @param Collection<int|string, mixed> $entriesById
     * @param Collection<int|string, mixed> $createdPages
     */
    protected function importTreeNodes( array $treeNodes, Page $parent, Collection $entriesById, Collection &$createdPages, int &$imported ): void
    {
        foreach( $treeNodes as $node )
        {
            $entryId = $node['entry'] ?? null;

            if( !$entryId || !$entriesById->has( $entryId ) ) {
                if( !empty( $node['children'] ) ) {
                    $this->importTreeNodes( $node['children'], $parent, $entriesById, $createdPages, $imported );
                }
                continue;
            }

            $entry = $entriesById->get( $entryId );
            $this->importSingleEntry( $entry, $parent, $createdPages, $imported );

            if( !empty( $node['children'] ) && $createdPages->has( $entryId ) ) {
                $this->importTreeNodes( $node['children'], $createdPages->get( $entryId ), $entriesById, $createdPages, $imported );
            }
        }
    }


    /**
     * Checks if an array looks like Bard ProseMirror content.
     *
     * @param array<int|string, mixed> $value
     */
    protected function isBardContent( array $value ): bool
    {
        if( isset( $value['type'] ) && $value['type'] === 'doc' ) {
            return true;
        }

        $first = $value[0] ?? null;

        if( !is_array( $first ) ) {
            return false;
        }

        $type = $first['type'] ?? '';

        return in_array( $type, ['paragraph', 'heading', 'bulletList', 'orderedList', 'blockquote', 'codeBlock', 'set', 'image', 'horizontalRule', 'doc'] );
    }


    /**
     * Checks if an array looks like Replicator content.
     *
     * @param array<int|string, mixed> $value
     */
    protected function isReplicatorContent( array $value ): bool
    {
        $first = $value[0] ?? null;

        if( !is_array( $first ) ) {
            return false;
        }

        return isset( $first['type'] ) && isset( $first['enabled'] );
    }


    /**
     * Converts a markdown or HTML string into Pagible content elements.
     *
     * @return array{elements: array<int, array<string, mixed>>, fileIds: array<int, string>}
     */
    protected function markdownOrHtmlToElements( string $text ): array
    {
        $text = trim( $text );

        if( empty( $text ) ) {
            return ['elements' => [], 'fileIds' => []];
        }

        if( preg_match( '/<[a-z][\s\S]*>/i', $text ) ) {
            return ['elements' => [[
                'id' => Utils::uid(),
                'type' => 'html',
                'group' => 'main',
                'data' => ['text' => $text],
            ]], 'fileIds' => []];
        }

        return ['elements' => [[
            'id' => Utils::uid(),
            'type' => 'text',
            'group' => 'main',
            'data' => ['text' => $text],
        ]], 'fileIds' => []];
    }


    /**
     * Prints a dry run summary.
     *
     * @param Collection<int|string, mixed> $entries
     */
    protected function printDryRun( Collection $entries ): void
    {
        foreach( $entries as $entry ) {
            $data = $this->decodeEntryData( $entry );
            $title = $data['title'] ?? $entry->slug ?? 'Untitled';
            $published = $entry->published ? '' : ' [draft]';
            $this->line( "  [{$entry->id}] ({$entry->collection}) {$title}{$published}" );
        }
        $this->info( 'Dry run complete. No changes were made.' );
    }


    /**
     * Converts Replicator blocks into Pagible content elements.
     *
     * @param array<int, mixed> $blocks
     * @return array{elements: array<int, array<string, mixed>>, fileIds: array<int, string>}
     */
    protected function replicatorToElements( array $blocks ): array
    {
        $elements = [];
        $fileIds = [];

        foreach( $blocks as $block )
        {
            if( !is_array( $block ) ) {
                continue;
            }

            $result = $this->convertReplicatorBlock( $block );

            if( $result ) {
                $elements = array_merge( $elements, $result['elements'] );
                $fileIds = array_merge( $fileIds, $result['fileIds'] );
            }
        }

        return ['elements' => $elements, 'fileIds' => array_unique( $fileIds )];
    }


    /**
     * Resolves a Statamic asset path to a full URL.
     */
    protected function resolveAssetPath( string $assetPath ): string
    {
        if( filter_var( $assetPath, FILTER_VALIDATE_URL ) ) {
            return $assetPath;
        }

        if( $this->fileBase ) {
            return $this->fileBase . '/' . ltrim( $assetPath, '/' );
        }

        return $assetPath;
    }


    /**
     * Sets up multi-tenancy if a tenant option is provided.
     */
    protected function setupTenant(): void
    {
        if( $tenant = $this->option( 'tenant' ) )
        {
            \Aimeos\Cms\Tenancy::$callback = function() use ( $tenant ) {
                return $tenant;
            };
        }
    }


    /**
     * Checks if a table exists in the Statamic database.
     */
    protected function tableExists( string $table ): bool
    {
        return DB::connection( $this->stConnection )
            ->getSchemaBuilder()
            ->hasTable( $table );
    }


    /**
     * Unwraps a Bard doc node to get its content array.
     *
     * @param array<int|string, mixed> $value
     * @return array<int|string, mixed>
     */
    protected function unwrapBardDoc( array $value ): array
    {
        if( isset( $value['type'] ) && $value['type'] === 'doc' ) {
            return $value['content'] ?? [];
        }

        return $value;
    }
}
