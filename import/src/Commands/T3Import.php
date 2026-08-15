<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */

namespace Aimeos\Cms\Commands;

use Aimeos\Cms\Models\Element;
use Aimeos\Cms\Models\File;
use Aimeos\Cms\Models\Page;
use Aimeos\Cms\Tenancy;
use Aimeos\Cms\Utils;
use Illuminate\Console\Command;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class T3Import extends Command
{
    /**
     * Command name
     */
    protected $signature = 'cms:t3-import
        {--connection=typo3 : Database connection name for the TYPO3 database}
        {--domain=* : Override the detected domain, optionally for a root page UID (e.g. --domain=example.com or --domain=example.com:1 --domain=example.de:2)}
        {--lang=en : Language code for the imported pages}
        {--tenant= : Tenant ID for multi-tenant setups}
        {--editor=t3-import : Editor name for imported records}
        {--theme= : Pagible theme name applied to imported pages}
        {--file-base= : Base URL for TYPO3 files (e.g. https://example.com/fileadmin)}
        {--page=* : Import or update only the TYPO3 page with this UID (repeatable)}
        {--dry-run : Show what would be imported without making changes}';

    /**
     * Command description
     */
    protected $description = 'Imports TYPO3 pages into Pagible CMS pages';

    protected string $t3Connection;

    protected string $domain;

    protected string $lang;

    protected string $editor;

    protected string $theme = '';

    protected string $fileBase;

    /** @var Collection<int, mixed> */
    protected Collection $sysFiles;

    /** @var Collection<string, mixed> */
    protected Collection $sysFilesByIdentifier;

    /** @var Collection<int|string, mixed> */
    protected Collection $fileRefs;

    /** @var Collection<int|string, mixed>|null */
    protected ?Collection $accordionItems = null;

    /** @var Collection<int|string, mixed>|null */
    protected ?Collection $carouselItems = null;

    /** @var Collection<int|string, mixed>|null */
    protected ?Collection $carouselFileRefs = null;

    /** @var Collection<int|string, mixed>|null */
    protected ?Collection $contentRecords = null;

    /** @var Collection<string, string> */
    protected Collection $createdFiles;

    /** @var Collection<string, string> */
    protected Collection $createdFileUrls;

    /** @var array<int, string> */
    protected array $domainMap = [];

    /** @var Collection<int, mixed>|null */
    protected ?Collection $t3Pages = null;

    /** @var Collection<int|string, mixed>|null */
    protected ?Collection $backendLayouts = null;

    /** @var Collection<string, array{id: string, latest: string}>|null */
    protected ?Collection $sharedElements = null;

    protected string $contentUid = '?';

    /**
     * Execute command
     */
    public function handle(): void
    {
        $this->t3Connection = (string) $this->option('connection'); // @phpstan-ignore cast.string
        $this->domain = $this->parseDomainOption();
        $this->lang = (string) $this->option('lang'); // @phpstan-ignore cast.string
        $this->editor = (string) $this->option('editor'); // @phpstan-ignore cast.string
        $this->theme = (string) ($this->option('theme') ?: ''); // @phpstan-ignore cast.string
        $this->fileBase = rtrim((string) ($this->option('file-base') ?: ''), '/'); // @phpstan-ignore cast.string
        $this->createdFiles = Collection::make();
        $this->createdFileUrls = Collection::make();
        $this->sharedElements = Collection::make();

        $this->setupTenant();

        if (! $this->check()) {
            return;
        }

        if ($this->domain === '') {
            $this->domainMap += $this->fetchDomains();
        }

        if ($this->fileBase === '') {
            $this->fileBase = $this->defaultFileBase();
        }

        $pages = $this->fetchPages();

        if ($pages->isEmpty()) {
            $this->warn('No TYPO3 pages found.');

            return;
        }

        $pageIds = $this->pageIds();
        $selectedPages = $pageIds === null ? $pages : $this->selectPages($pages, $pageIds);

        $this->info($pageIds === null
            ? "Found {$pages->count()} TYPO3 pages."
            : "Selected {$selectedPages->count()} of {$pages->count()} TYPO3 pages."
        );

        if ($this->option('dry-run')) {
            $pageIds === null
                ? $this->printDryRun($pages)
                : $this->printSelectedDryRun($selectedPages);

            return;
        }

        $this->sysFiles = $this->fetchSysFiles();
        $this->sysFilesByIdentifier = $this->sysFiles->keyBy(
            fn ($file) => '/'.ltrim((string) $file->identifier, '/')
        );
        $this->fileRefs = $this->fetchFileReferences();
        $this->t3Pages = $pages->keyBy(fn ($page) => (int) $page->uid);
        $this->accordionItems = $this->fetchAccordionItems();
        $this->carouselItems = $this->fetchCarouselItems();
        $this->carouselFileRefs = $this->fetchCarouselFileReferences();
        $this->backendLayouts = $this->fetchBackendLayouts();
        $contentElements = $this->fetchContentElements();

        if ($pageIds === null) {
            $this->importPages($pages, $contentElements);
        } else {
            $this->importSelectedPages($selectedPages, $pages, $contentElements);
        }
    }

    /**
     * Returns explicitly selected TYPO3 page UIDs or null for a full import.
     *
     * @return list<int>|null
     */
    protected function pageIds(): ?array
    {
        $values = (array) $this->option('page');

        if ($values === []) {
            return null;
        }

        $ids = [];

        foreach ($values as $value) {
            $value = (string) $value;

            if (! ctype_digit($value) || (int) $value < 1) {
                throw new \InvalidArgumentException("Invalid TYPO3 page UID: {$value}");
            }

            $ids[] = (int) $value;
        }

        return array_values(array_unique($ids));
    }

    /**
     * Selects requested pages while keeping the full source collection available
     * for parent, domain, shortcut and link resolution.
     *
     * @param  Collection<int, mixed>  $pages
     * @param  list<int>  $ids
     * @return Collection<int, mixed>
     */
    protected function selectPages(Collection $pages, array $ids): Collection
    {
        $available = $pages->keyBy(fn ($page) => (int) $page->uid);
        $missing = array_values(array_diff($ids, $available->keys()->map(fn ($uid) => (int) $uid)->all()));

        if ($missing !== []) {
            throw new \InvalidArgumentException('TYPO3 page UID not found: '.implode(', ', $missing));
        }

        return Collection::make($ids)->map(fn ($id) => $available->get($id))->values();
    }

    /**
     * Builds content elements array from TYPO3 tt_content records.
     *
     * @param  Collection<int, mixed>  $records
     * @return array{elements: array<int, array<string, mixed>>, fileIds: string[], elementIds: string[]}
     */
    protected function buildContent(Collection $records): array
    {
        $records = $this->expandReferencedRecords($records);
        $elements = [];
        $fileIds = [];
        $elementIds = [];

        foreach ($records as $record) {
            $filesBefore = clone $this->createdFiles;
            $urlsBefore = clone $this->createdFileUrls;
            $this->contentUid = (string) ($record->uid ?? '?');

            try {
                $result = DB::connection(config('cms.db', 'sqlite'))->transaction(function () use ($record, $filesBefore) {
                    try {
                        $result = $this->convertContentElement($record);

                        if (! $result) {
                            return null;
                        }

                        foreach ($result['elements'] as &$element) {
                            $element['group'] = 'main';
                        }
                        unset($element);

                        if (! isset($record->_pagible_shared)) {
                            return $result + ['elementIds' => []];
                        }

                        $references = [];
                        $elementIds = [];

                        foreach ($result['elements'] as $position => $element) {
                            $id = $this->storeSharedElement(
                                $record,
                                $element,
                                $result['fileIds'] ?? [],
                                $position,
                            );
                            $references[] = ['type' => 'reference', 'refid' => $id, 'group' => 'main'];
                            $elementIds[] = $id;
                        }

                        return ['elements' => $references, 'fileIds' => [], 'elementIds' => $elementIds];
                    } catch (\Throwable $e) {
                        $this->removeFilesCreatedAfter($filesBefore);

                        throw $e;
                    }
                });
            } catch (\Throwable $e) {
                $this->createdFiles = $filesBefore;
                $this->createdFileUrls = $urlsBefore;
                $uid = (string) ($record->uid ?? '?');
                $this->warn("  Skipped content element [{$uid}]: {$e->getMessage()}");

                continue;
            } finally {
                $this->contentUid = '?';
            }

            if ($result) {
                $elements = array_merge($elements, $result['elements']);
                $fileIds = array_merge($fileIds, $result['fileIds'] ?? []);
                $elementIds = array_merge($elementIds, $result['elementIds'] ?? []);
            }
        }

        return [
            'elements' => $elements,
            'fileIds' => array_values(array_unique($fileIds)),
            'elementIds' => array_values(array_unique($elementIds)),
        ];
    }

    /**
     * Creates or updates one reusable Pagible element for a referenced TYPO3 item.
     *
     * @param  array<string, mixed>  $element
     * @param  string[]  $fileIds
     */
    protected function storeSharedElement(object $record, array $element, array $fileIds, int $position): string
    {
        $key = $this->sharedElementKey($record, $position);
        $cached = $this->sharedElements?->get($key);
        $lang = isset($this->lang) ? $this->lang : 'en';
        $editor = isset($this->editor) ? $this->editor : 't3-import';

        if ($cached && Element::whereKey($cached['id'])->where('latest_id', $cached['latest'])->exists()) {
            return $cached['id'];
        }

        $this->sharedElements ??= Collection::make();
        $name = $this->sharedElementName($record, $position);
        $shared = Element::withTrashed()
            ->where('lang', $lang)
            ->where('name', $name)
            ->first();

        if ($shared) {
            if ($shared->trashed()) {
                $shared->restore();
            }
        } else {
            $shared = Element::forceCreate([
                'lang' => $lang,
                'type' => (string) $element['type'],
                'name' => $name,
                'data' => (array) ($element['data'] ?? []),
                'editor' => $editor,
            ]);
        }

        $version = $shared->versions()->forceCreate([
            'lang' => $lang,
            'data' => [
                'lang' => $lang,
                'type' => (string) $element['type'],
                'name' => $name,
                'data' => (array) ($element['data'] ?? []),
            ],
            'editor' => $editor,
        ]);

        if ($fileIds !== []) {
            $version->files()->attach(array_values(array_unique($fileIds)));
        }

        $shared->forceFill(['latest_id' => $version->id])->saveQuietly();
        $shared->publish($version);

        $value = ['id' => (string) $shared->id, 'latest' => (string) $version->id];
        $this->sharedElements->put($key, $value);

        return $value['id'];
    }

    /**
     * Returns the stable importer identity of a reusable TYPO3 element.
     */
    protected function sharedElementKey(object $record, int $position): string
    {
        $connection = isset($this->t3Connection) ? $this->t3Connection : 'typo3';
        $lang = isset($this->lang) ? $this->lang : 'en';

        return implode(':', [
            sha1($connection),
            (int) ($record->_pagible_shared_page ?? $record->pid ?? 0),
            (int) ($record->_pagible_shared_uid ?? $record->uid ?? 0),
            $position,
            $lang,
        ]);
    }

    /**
     * Returns the persistent, source-specific name used to find shared elements on re-import.
     */
    protected function sharedElementName(object $record, int $position): string
    {
        $parts = explode(':', $this->sharedElementKey($record, $position));

        return sprintf(
            'TYPO3 element %s/%d/%d/%d',
            substr($parts[0], 0, 12),
            (int) $parts[1],
            (int) $parts[2],
            (int) $parts[3],
        );
    }

    /**
     * Removes managed storage files created while converting one content element.
     *
     * The database rows are removed by the nested transaction rollback, but
     * filesystem writes need explicit cleanup before that rollback happens.
     *
     * @param  Collection<string, string>  $filesBefore
     */
    protected function removeFilesCreatedAfter(Collection $filesBefore): void
    {
        $ids = $this->createdFiles
            ->filter(fn ($id, $path) => $filesBefore->get($path) !== $id)
            ->values()
            ->filter()
            ->unique();

        if ($ids->isEmpty()) {
            return;
        }

        File::withoutTenancy()->whereIn('id', $ids)->get()->each(function (File $file): void {
            $tenant = (string) $file->tenant_id;
            $paths = Collection::make([
                $file->path,
                ...(array) $file->previews,
            ])->map(
                fn ($path) => Utils::normalizePath($path, $tenant)
            )->filter()->values()->all();

            if ($paths) {
                Storage::disk(File::diskName((string) $file->disk))->delete($paths);
            }
        });
    }

    /**
     * Imports one file without aborting conversion of its content element.
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T|null
     */
    protected function importFileSafely(callable $callback): mixed
    {
        $filesBefore = clone $this->createdFiles;
        $urlsBefore = clone $this->createdFileUrls;

        try {
            return DB::connection(config('cms.db', 'sqlite'))->transaction(function () use ($callback, $filesBefore) {
                try {
                    return $callback();
                } catch (\Throwable $e) {
                    $this->removeFilesCreatedAfter($filesBefore);

                    throw $e;
                }
            });
        } catch (\Throwable $e) {
            $this->createdFiles = $filesBefore;
            $this->createdFileUrls = $urlsBefore;
            $this->warn("  Skipped file in content element [{$this->contentUid}]: {$e->getMessage()}");

            return null;
        }
    }

    /**
     * Builds the page data array.
     *
     * @return array<string, mixed>
     */
    protected function buildPageData(object $t3Page, string $slug, string $domain, string $to = ''): array
    {
        return [
            'name' => $t3Page->nav_title ?: $t3Page->title, // @phpstan-ignore property.notFound, property.notFound
            /** @phpstan-ignore property.notFound, property.notFound */
            'title' => $t3Page->seo_title ?: $t3Page->title,
            'path' => $slug,
            'tag' => $t3Page->is_siteroot ? 'root' : 'page', // @phpstan-ignore property.notFound
            'domain' => $domain,
            'lang' => $this->lang,
            'to' => $to,
            'theme' => $this->theme,
            'status' => $t3Page->hidden ? 0 : ($t3Page->nav_hide ? 2 : 1), // @phpstan-ignore property.notFound, property.notFound
            'editor' => $this->editor,
        ];
    }

    /**
     * Tests the TYPO3 database connection.
     */
    protected function check(): bool
    {
        try {
            DB::connection($this->t3Connection)->getPdo();

            return true;
        } catch (\Exception $e) {
            $this->error("Cannot connect to TYPO3 database using connection \"{$this->t3Connection}\".");
            $this->error("Add a \"{$this->t3Connection}\" connection to config/database.php, e.g.:");
            $this->line("  '{$this->t3Connection}' => [");
            $this->line("      'driver' => 'mysql',");
            $this->line("      'host' => env('T3_DB_HOST', '127.0.0.1'),");
            $this->line("      'database' => env('T3_DB_DATABASE', 'typo3'),");
            $this->line("      'username' => env('T3_DB_USERNAME', 'root'),");
            $this->line("      'password' => env('T3_DB_PASSWORD', ''),");
            $this->line('  ]');

            return false;
        }
    }

    /**
     * Converts a TYPO3 tt_content record into Pagible content elements.
     *
     * @return array{elements: array<int, array<string, mixed>>, fileIds?: string[]}|null
     */
    protected function convertContentElement(object $record): ?array
    {
        return match ((string) ($record->CType ?? '')) {
            'header' => $this->convertHeader($record),
            'text' => $this->convertText($record),
            'textpic', 'textmedia' => $this->convertTextpic($record),
            'image' => $this->convertImage($record),
            'html' => $this->convertHtml($record),
            'accordion' => $this->convertAccordion($record),
            'carousel' => $this->convertCarousel($record),
            'shortcut' => $this->convertShortcut($record),
            default => $this->convertDefault($record),
        };
    }

    /**
     * Converts a TYPO3 content shortcut by importing its referenced records.
     *
     * @return array{elements: array<int, array<string, mixed>>, fileIds: string[]}|null
     */
    protected function convertShortcut(object $record): ?array
    {
        preg_match_all('/\d+/', (string) ($record->records ?? ''), $matches);
        $elements = [];
        $fileIds = [];

        foreach (array_unique(array_map('intval', $matches[0])) as $uid) {
            $target = $this->contentRecords?->get($uid);

            if (! $target || (int) ($target->uid ?? 0) === (int) ($record->uid ?? 0)) {
                continue;
            }

            $result = $this->convertContentElement($target);

            if ($result) {
                $elements = array_merge($elements, $result['elements']);
                $fileIds = array_merge($fileIds, $result['fileIds'] ?? []);
            }
        }

        return empty($elements) ? null : [
            'elements' => $elements,
            'fileIds' => array_values(array_unique($fileIds)),
        ];
    }

    /**
     * Replaces TYPO3 content shortcuts with their canonical target records and
     * marks every referenced source record for Pagible shared-element storage.
     *
     * @param  Collection<int, mixed>  $records
     * @return Collection<int, mixed>
     */
    protected function expandReferencedRecords(Collection $records): Collection
    {
        $referenced = [];
        $sourcePages = [];

        foreach ($this->contentRecords ?? Collection::make() as $record) {
            if ((string) ($record->CType ?? '') !== 'shortcut') {
                continue;
            }

            foreach ($this->referencedContentUids($record) as $uid) {
                $referenced[$uid] = true;
            }
        }

        foreach ($this->t3Pages ?? Collection::make() as $page) {
            if (($uid = (int) ($page->content_from_pid ?? 0)) > 0) {
                $sourcePages[$uid] = true;
            }
        }

        return $records->flatMap(
            fn ($record) => $this->expandReferencedRecord($record, $referenced, $sourcePages),
        )->values();
    }

    /**
     * Expands one TYPO3 shortcut recursively while retaining its placement.
     *
     * @param  array<int, bool>  $referenced
     * @param  array<int, bool>  $sourcePages
     * @param  array<int, bool>  $seen
     * @return list<object>
     */
    protected function expandReferencedRecord(object $record, array $referenced, array $sourcePages,
        ?object $placement = null, ?string $kind = null, array $seen = []): array
    {
        $uid = (int) ($record->uid ?? 0);

        if ((string) ($record->CType ?? '') === 'shortcut') {
            if (isset($seen[$uid])) {
                return [];
            }

            $seen[$uid] = true;
            $placement ??= $record;
            $kind ??= (string) ($record->_pagible_shared ?? 'reference');
            $result = [];

            foreach ($this->referencedContentUids($record) as $targetUid) {
                $target = $this->contentRecords?->get($targetUid);

                if (! $target || $targetUid === $uid) {
                    continue;
                }

                array_push(
                    $result,
                    ...$this->expandReferencedRecord($target, $referenced, $sourcePages, $placement, $kind, $seen),
                );
            }

            return $result;
        }

        $kind ??= (string) ($record->_pagible_shared ?? '');

        if ($kind === '' && (isset($referenced[$uid]) || isset($sourcePages[(int) ($record->pid ?? 0)]))) {
            $kind = 'reference';
        }

        if (! $placement && $kind === '') {
            return [$record];
        }

        $data = get_object_vars($record);

        if ($placement) {
            $placement = get_object_vars($placement);
            $data['colPos'] = (int) ($placement['colPos'] ?? $data['colPos'] ?? 0);
            $data['sorting'] = (int) ($placement['sorting'] ?? $data['sorting'] ?? 0);
        }

        if ($kind !== '') {
            $data['_pagible_shared'] = $kind;
            $data['_pagible_shared_page'] = (int) ($record->_pagible_shared_page ?? $record->pid ?? 0);
            $data['_pagible_shared_uid'] = $uid;
        }

        return [(object) $data];
    }

    /**
     * Returns canonical tt_content UIDs referenced by a TYPO3 shortcut.
     *
     * @return list<int>
     */
    protected function referencedContentUids(object $record): array
    {
        $uids = [];

        foreach (preg_split('/\s*,\s*/', trim((string) ($record->records ?? ''))) ?: [] as $value) {
            if (preg_match('/^(?:tt_content[_:]?)?(\d+)$/', $value, $match)) {
                $uids[] = (int) $match[1];
            }
        }

        return array_values(array_unique($uids));
    }

    /**
     * Converts a Bootstrap Package accordion into a questions element.
     *
     * @return array{elements: array<int, array<string, mixed>>, fileIds: string[]}|null
     */
    protected function convertAccordion(object $record): ?array
    {
        $items = [];
        $fileIds = [];
        $children = $this->accordionItems?->get((int) ($record->uid ?? 0), Collection::make())
            ?? Collection::make();

        foreach ($children as $child) {
            if (empty($child->header) || empty($child->bodytext)) {
                continue;
            }

            $result = $this->rewriteHtmlFiles((string) $child->bodytext);
            $items[] = [
                'title' => $child->header,
                'text' => $this->htmlToMarkdown($result['html']),
            ];
            $fileIds = array_merge($fileIds, $result['fileIds']);
        }

        if (empty($items) && ! empty($record->bodytext)) {
            $result = $this->rewriteHtmlFiles((string) $record->bodytext);
            $items[] = [
                'title' => $record->header ?: 'Item', // @phpstan-ignore property.notFound
                'text' => $this->htmlToMarkdown($result['html']),
            ];
            $fileIds = array_merge($fileIds, $result['fileIds']);
        }

        if (empty($items)) {
            return null;
        }

        return ['elements' => [[
            'id' => Utils::uid(),
            'type' => 'questions',
            'group' => 'main',
            'data' => [
                'title' => (string) ($record->header ?? ''),
                'items' => $items,
            ],
        ]], 'fileIds' => array_values(array_unique($fileIds))];
    }

    /**
     * Converts a Bootstrap Package carousel into a slideshow.
     *
     * @return array{elements: array<int, array<string, mixed>>, fileIds: string[]}|null
     */
    protected function convertCarousel(object $record): ?array
    {
        $files = [];
        $fileIds = [];
        $children = $this->carouselItems?->get((int) ($record->uid ?? 0), Collection::make())
            ?? Collection::make();

        foreach ($children as $child) {
            foreach ($this->carouselFileRefs?->get((int) ($child->uid ?? 0), Collection::make()) ?? [] as $ref) {
                $id = $this->importFileSafely(fn () => $this->importFileReference($ref));

                if ($id) {
                    $files[] = ['id' => $id, 'type' => 'file'];
                    $fileIds[] = $id;
                }
            }
        }

        if ($files === []) {
            return $this->convertDefault($record);
        }

        $type = count($files) > 1 ? 'slideshow' : 'image';
        $data = $type === 'slideshow'
            ? ['title' => (string) ($record->header ?? ''), 'files' => $files]
            : ['file' => $files[0]];

        return ['elements' => [[
            'id' => Utils::uid(),
            'type' => $type,
            'group' => 'main',
            'data' => $data,
        ]], 'fileIds' => array_values(array_unique($fileIds))];
    }

    /**
     * Converts a default/unknown CType with bodytext into an html element.
     *
     * @return array{elements: array<int, array<string, mixed>>, fileIds: string[]}|null
     */
    protected function convertDefault(object $record): ?array
    {
        $elements = [];
        $fileIds = [];

        if (! empty($record->header) && ($record->header_layout ?? '0') !== '100') {
            $elements[] = [
                'id' => Utils::uid(),
                'type' => 'heading',
                'group' => 'main',
                'data' => [
                    'level' => $this->headerLevel($record->header_layout), // @phpstan-ignore property.notFound
                    'title' => $record->header,
                ],
            ];
        }

        if (! empty($record->bodytext)) {
            $text = trim($record->bodytext);
            if (! empty($text)) {
                $result = $this->rewriteHtmlFiles($text);
                $elements[] = [
                    'id' => Utils::uid(),
                    'type' => 'html',
                    'group' => 'main',
                    'data' => ['text' => Utils::html($result['html'])],
                ];
                $fileIds = array_merge($fileIds, $result['fileIds']);
            }
        }

        if (empty($elements)) {
            return null;
        }

        return ['elements' => $elements, 'fileIds' => array_values(array_unique($fileIds))];
    }

    /**
     * Converts a header content element into a heading element.
     *
     * @return array{elements: array<int, array<string, mixed>>}|null
     */
    protected function convertHeader(object $record): ?array
    {
        if (empty($record->header)) {
            return null;
        }

        return ['elements' => [[
            'id' => Utils::uid(),
            'type' => 'heading',
            'group' => 'main',
            'data' => [
                'level' => $this->headerLevel($record->header_layout), // @phpstan-ignore property.notFound
                'title' => $record->header,
            ],
        ]]];
    }

    /**
     * Converts an html content element into a Pagible html element.
     *
     * @return array{elements: array<int, array<string, mixed>>, fileIds: string[]}|null
     */
    protected function convertHtml(object $record): ?array
    {
        if (empty($record->bodytext)) {
            return null;
        }

        $result = $this->rewriteHtmlFiles((string) $record->bodytext);

        return ['elements' => [[
            'id' => Utils::uid(),
            'type' => 'html',
            'group' => 'main',
            'data' => ['text' => Utils::html($result['html'])],
        ]], 'fileIds' => $result['fileIds']];
    }

    /**
     * Converts an image content element into a Pagible image element.
     *
     * @return array{elements: array<int, array<string, mixed>>, fileIds: string[]}|null
     */
    protected function convertImage(object $record): ?array
    {
        $fileId = $this->importFileForContent($record->uid); // @phpstan-ignore property.notFound

        if (! $fileId) {
            return null;
        }

        $elements = [];

        if (! empty($record->header) && ($record->header_layout ?? '0') !== '100') {
            $elements[] = [
                'id' => Utils::uid(),
                'type' => 'heading',
                'group' => 'main',
                'data' => [
                    'level' => $this->headerLevel($record->header_layout), // @phpstan-ignore property.notFound
                    'title' => $record->header,
                ],
            ];
        }

        $elements[] = [
            'id' => Utils::uid(),
            'type' => 'image',
            'group' => 'main',
            'data' => ['file' => ['id' => $fileId, 'type' => 'file']],
        ];

        return ['elements' => $elements, 'fileIds' => [$fileId]];
    }

    /**
     * Converts a text content element into heading + html elements.
     *
     * @return array{elements: array<int, array<string, mixed>>, fileIds: string[]}|null
     */
    protected function convertText(object $record): ?array
    {
        $elements = [];
        $fileIds = [];

        if (! empty($record->header) && ($record->header_layout ?? '0') !== '100') {
            $elements[] = [
                'id' => Utils::uid(),
                'type' => 'heading',
                'group' => 'main',
                'data' => [
                    'level' => $this->headerLevel($record->header_layout), // @phpstan-ignore property.notFound
                    'title' => $record->header,
                ],
            ];
        }

        if (! empty($record->bodytext)) {
            $text = trim($record->bodytext);
            if (! empty($text)) {
                $result = $this->rewriteHtmlFiles($text);
                $elements[] = [
                    'id' => Utils::uid(),
                    'type' => 'html',
                    'group' => 'main',
                    'data' => ['text' => Utils::html($result['html'])],
                ];
                $fileIds = array_merge($fileIds, $result['fileIds']);
            }
        }

        if (empty($elements)) {
            return null;
        }

        return ['elements' => $elements, 'fileIds' => array_values(array_unique($fileIds))];
    }

    /**
     * Converts a textpic/textmedia content element into an image-text element.
     *
     * @return array{elements: array<int, array<string, mixed>>, fileIds: string[]}|null
     */
    protected function convertTextpic(object $record): ?array
    {
        $elements = [];
        $fileIds = [];

        if (! empty($record->header) && ($record->header_layout ?? '0') !== '100') {
            $elements[] = [
                'id' => Utils::uid(),
                'type' => 'heading',
                'group' => 'main',
                'data' => [
                    'level' => $this->headerLevel($record->header_layout), // @phpstan-ignore property.notFound
                    'title' => $record->header,
                ],
            ];
        }

        $fileId = $this->importFileForContent($record->uid); // @phpstan-ignore property.notFound
        $text = ! empty($record->bodytext) ? trim($record->bodytext) : '';

        if ($text !== '') {
            $result = $this->rewriteHtmlFiles($text);
            $text = $result['html'];
            $fileIds = array_merge($fileIds, $result['fileIds']);
        }

        if ($fileId && ! empty($text)) {
            $position = $this->imagePosition($record->imageorient ?? 0);
            $elements[] = [
                'id' => Utils::uid(),
                'type' => 'image-text',
                'group' => 'main',
                'data' => [
                    'text' => $text,
                    'file' => ['id' => $fileId, 'type' => 'file'],
                    'position' => $position,
                ],
            ];
            $fileIds[] = $fileId;
        } elseif ($fileId) {
            $elements[] = [
                'id' => Utils::uid(),
                'type' => 'image',
                'group' => 'main',
                'data' => ['file' => ['id' => $fileId, 'type' => 'file']],
            ];
            $fileIds[] = $fileId;
        } elseif (! empty($text)) {
            $elements[] = [
                'id' => Utils::uid(),
                'type' => 'html',
                'group' => 'main',
                'data' => ['text' => Utils::html($text)],
            ];
        }

        if (empty($elements)) {
            return null;
        }

        return ['elements' => $elements, 'fileIds' => array_values(array_unique($fileIds))];
    }

    /**
     * Promotes TYPO3 lazy-loading attributes before HTMLPurifier removes them.
     */
    protected function promoteLazyAttributes(string $html): string
    {
        return (string) preg_replace_callback(
            '/<(img|source|video)\b[^>]*>/is',
            function (array $matches): string {
                $tag = $matches[0];

                if (preg_match('/\sdata-srcset\s*=\s*(["\'])(.*?)\1/is', $tag)) {
                    if (preg_match('/\ssrcset\s*=/i', $tag)) {
                        $tag = (string) preg_replace('/\sdata-srcset\s*=\s*(["\']).*?\1/is', '', $tag);
                    } else {
                        $tag = (string) preg_replace('/\sdata-srcset(\s*=)/i', ' srcset$1', $tag, 1);
                    }
                }

                if (preg_match('/\sdata-src\s*=\s*(["\'])(.*?)\1/is', $tag)) {
                    if (preg_match('/\ssrc\s*=/i', $tag)) {
                        $tag = (string) preg_replace('/\sdata-src\s*=\s*(["\']).*?\1/is', '', $tag);
                    } else {
                        $tag = (string) preg_replace('/\sdata-src(\s*=)/i', ' src$1', $tag, 1);
                    }
                }

                return $tag;
            },
            $html
        );
    }

    /**
     * Converts legacy TYPO3 link tags and t3:// page URLs to portable links.
     */
    protected function rewriteTypo3Links(string $html): string
    {
        $html = (string) preg_replace_callback(
            '/<link\s+([^>]+)>(.*?)<\/link>/is',
            function (array $matches): string {
                $tokens = array_values(array_filter(
                    str_getcsv(trim($matches[1]), ' ', '"', '\\'),
                    fn ($value) => $value !== ''
                ));
                $url = isset($tokens[0]) ? $this->resolveTypo3Target((string) $tokens[0]) : null;

                if (! $url) {
                    return $matches[2];
                }

                $attributes = ' href="'.htmlspecialchars($url, ENT_QUOTES | ENT_HTML5, 'UTF-8').'"';

                if (in_array($tokens[1] ?? '', ['_blank', '_self'], true)) {
                    $attributes .= ' target="'.$tokens[1].'"';
                }

                if (! empty($tokens[2]) && $tokens[2] !== '-') {
                    $attributes .= ' class="'.htmlspecialchars((string) $tokens[2], ENT_QUOTES | ENT_HTML5, 'UTF-8').'"';
                }

                if (! empty($tokens[3]) && $tokens[3] !== '-') {
                    $attributes .= ' title="'.htmlspecialchars((string) $tokens[3], ENT_QUOTES | ENT_HTML5, 'UTF-8').'"';
                }

                return '<a'.$attributes.'>'.$matches[2].'</a>';
            },
            $html
        );

        return (string) preg_replace_callback(
            '/\bhref\s*=\s*(["\'])(t3:\/\/page\?.*?)\1/is',
            function (array $matches): string {
                $url = $this->rewriteTypo3Url(html_entity_decode($matches[2], ENT_QUOTES | ENT_HTML5, 'UTF-8'));

                return $url
                    ? 'href='.$matches[1].htmlspecialchars($url, ENT_QUOTES | ENT_HTML5, 'UTF-8').$matches[1]
                    : $matches[0];
            },
            $html
        );
    }

    /**
     * Resolves a legacy TYPO3 link target such as "61#c381".
     */
    protected function resolveTypo3Target(string $target): ?string
    {
        if (preg_match('#^(?:https?://|mailto:|tel:|/)#i', $target)) {
            return $target;
        }

        if (! preg_match('/^(\d+)(?:#(.+))?$/', $target, $matches)) {
            return null;
        }

        return $this->resolveTypo3Page((int) $matches[1], $matches[2] ?? '');
    }

    /**
     * Rewrites one t3://page URL when its target page is part of the import.
     */
    protected function rewriteTypo3Url(string $url): ?string
    {
        if (! preg_match('~^t3://page\?([^#]+)(?:#(.*))?$~i', trim($url), $matches)) {
            return null;
        }

        parse_str(html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'), $query);
        $uid = (int) ($query['uid'] ?? 0);
        $fragment = $query['fragment'] ?? $matches[2] ?? '';
        $fragment = is_string($fragment) ? $fragment : '';

        return $uid > 0 ? $this->resolveTypo3Page($uid, $fragment) : null;
    }

    /**
     * Builds a Pagible path for an imported TYPO3 page UID.
     */
    protected function resolveTypo3Page(int $uid, string $fragment = ''): ?string
    {
        $page = $this->t3Pages?->get($uid);

        if (! $page) {
            return null;
        }

        $url = in_array((int) $page->doktype, [3, 4], true)
            ? $this->redirectDestination($page, $this->t3Pages, [])
            : '/'.$this->slugFromPath($page->slug);

        if ($url === '') {
            return null;
        }

        return $url.($fragment !== '' ? '#'.ltrim($fragment, '#') : '');
    }

    /**
     * Imports TYPO3 files referenced directly by HTML attributes and rewrites their URLs.
     *
     * @return array{html: string, fileIds: string[]}
     */
    protected function rewriteHtmlFiles(string $html): array
    {
        $html = $this->promoteLazyAttributes($this->rewriteTypo3Links($html));
        $fileIds = [];
        $replace = function (string $url) use (&$fileIds): array {
            $path = parse_url(html_entity_decode(trim($url), ENT_QUOTES | ENT_HTML5, 'UTF-8'), PHP_URL_PATH);

            if (! is_string($path) || ! preg_match('#(?:^|/)fileadmin/.+#i', $path)) {
                return ['url' => null, 'skipped' => false];
            }

            $file = $this->importFileSafely(fn () => $this->importHtmlFile($url));

            if (! $file) {
                return ['url' => null, 'skipped' => true];
            }

            $fileIds[] = $file['id'];

            return [
                'url' => htmlspecialchars($file['url'], ENT_QUOTES | ENT_HTML5, 'UTF-8'),
                'skipped' => false,
            ];
        };

        $html = (string) preg_replace_callback(
            '/\b(srcset)\s*=\s*(["\'])(.*?)\2/is',
            function (array $matches) use ($replace): string {
                $candidates = explode(',', $matches[3]);

                foreach ($candidates as &$candidate) {
                    $parts = preg_split('/\s+/', trim($candidate), 2);
                    $replacement = $replace($parts[0] ?? '');

                    if ($replacement['url'] !== null) {
                        $candidate = $replacement['url'].(isset($parts[1]) ? ' '.$parts[1] : '');
                    } elseif ($replacement['skipped']) {
                        $candidate = '';
                    }
                }

                $candidates = array_values(array_filter(array_map('trim', $candidates)));

                return $candidates
                    ? $matches[1].'='.$matches[2].implode(', ', $candidates).$matches[2]
                    : '';
            },
            $html
        );

        $html = (string) preg_replace_callback(
            '/\b(src|href|poster|data-src)\s*=\s*(["\'])(.*?)\2/is',
            function (array $matches) use ($replace): string {
                $replacement = $replace($matches[3]);

                if ($replacement['skipped']) {
                    return '';
                }

                return $matches[1].'='.$matches[2].($replacement['url'] ?? $matches[3]).$matches[2];
            },
            $html
        );

        $html = (string) preg_replace_callback(
            '/\b(src|href|poster|data-src)\s*=\s*([^\s"\'=<>`]+)/is',
            function (array $matches) use ($replace): string {
                $replacement = $replace($matches[2]);

                if ($replacement['skipped']) {
                    return '';
                }

                return $matches[1].'='.($replacement['url'] ?? $matches[2]);
            },
            $html
        );

        // Do not retain empty media placeholders after their only source was
        // rejected. Linked text and videos with nested <source> elements stay.
        $html = (string) preg_replace(
            '/<(?:img|source)\b(?![^>]*\b(?:src|srcset)\s*=)[^>]*>/is',
            '',
            $html
        );

        return ['html' => $html, 'fileIds' => array_values(array_unique($fileIds))];
    }

    /**
     * Imports one fileadmin URL found in HTML.
     *
     * @return array{id: string, url: string}|null
     */
    protected function importHtmlFile(string $url): ?array
    {
        $url = html_entity_decode(trim($url), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        if ($scheme !== '' && ! in_array($scheme, ['http', 'https'], true)) {
            return null;
        }

        $urlPath = parse_url($url, PHP_URL_PATH);

        if (! is_string($urlPath) || ! preg_match('#(?:^|/)fileadmin/(.+)$#i', $urlPath, $matches)) {
            return null;
        }

        $identifier = '/'.ltrim($matches[1], '/');
        $decodedIdentifier = '/'.ltrim(rawurldecode($matches[1]), '/');
        $sysFile = $this->sysFilesByIdentifier->get($identifier)
            ?? $this->sysFilesByIdentifier->get($decodedIdentifier);
        $extension = trim((string) ($sysFile->extension ?? ''))
            ?: pathinfo($decodedIdentifier, PATHINFO_EXTENSION);
        $name = $sysFile->name ?? basename($decodedIdentifier);
        $mime = $this->normalizeMime(
            (string) ($sysFile->mime_type ?? ''),
            (string) $extension,
        );
        $path = $this->resolveFilePath(ltrim($identifier, '/'));

        if ($this->fileBase === '' && in_array($scheme, ['http', 'https'], true)) {
            $path = preg_replace('/[?#].*$/', '', $url) ?: $url;
        }

        $id = $this->createFile((string) $mime, (string) $name, $path);
        $newUrl = $this->createdFileUrls->get($path);

        return $id !== '' && $newUrl ? ['id' => $id, 'url' => $newUrl] : null;
    }

    /**
     * Creates a File record with a published version.
     */
    protected function createFile(string $mime, string $name, string $path): string
    {
        if ($existing = $this->createdFiles->get($path)) {
            if (File::withoutTenancy()->whereKey($existing)->exists()) {
                return $existing;
            }

            // Files are created inside the transaction of the page that first
            // references them. If that page fails, the database rows are
            // rolled back while these command-level caches survive for the
            // remaining pages. Never reuse those stale IDs or URLs.
            $this->createdFiles->forget($path);
            $this->createdFileUrls->forget($path);
        }

        $file = new File;
        $resource = null;

        try {
            $file->name = $name;
            $file->mime = $mime;
            $file->editor = $this->editor;

            if (str_starts_with($path, 'http')) {
                $resource = $this->downloadFile($path);

                if ($mime === 'image/svg+xml') {
                    $this->prepareSvgResource($resource);
                }

                $tmp = stream_get_meta_data($resource)['uri'] ?? null;
                $filename = basename((string) parse_url($path, PHP_URL_PATH)) ?: $name;

                if (! is_string($tmp)) {
                    throw new \Aimeos\Cms\Exception('Unable to create temporary file');
                }

                $file->ingest(new UploadedFile($tmp, $filename, $mime, null, true));
            } else {
                $file->path = $path;
                $file->previews = [];
            }

            $file->save();

            $snapshot = File::snapshot($file->toArray());
            $version = $file->versions()->forceCreate([
                'lang' => $file->lang,
                'data' => $snapshot['data'],
                'aux' => $snapshot['aux'],
                'editor' => $this->editor,
            ]);

            $file->forceFill(['latest_id' => $version->id])->saveQuietly();
            $file->publish($version);

            $id = $file->id ?? '';
            $this->createdFiles->put($path, $id);
            $disk = File::diskName((string) $file->disk);
            $url = Storage::disk($disk)->url((string) $file->path);

            // Local managed files must remain portable across hosts and preview ports.
            // Remote disks keep their absolute provider URL.
            if (config('filesystems.disks.'.$disk.'.driver') === 'local') {
                $url = parse_url($url, PHP_URL_PATH) ?: $url;
            }

            $this->createdFileUrls->put($path, $url);

            return $id;
        } catch (\Throwable $e) {
            $file->removePreviews()->removeFile();
            throw $e;
        } finally {
            if (is_resource($resource)) {
                fclose($resource);
            }
        }
    }

    /**
     * Downloads a remote file into a bounded temporary stream.
     *
     * @return resource
     */
    protected function downloadFile(string $url)
    {
        $response = Utils::http($url, ['stream' => true]);

        if (! $response->successful()) {
            throw new \Aimeos\Cms\Exception(sprintf('Failed to download "%s"', $url));
        }

        $limit = max(0, (float) config('cms.upload.filesize', 50));
        $max = (int) ($limit * 1024 * 1024);
        $body = $response->toPsrResponse()->getBody();
        $length = trim($response->header('Content-Length'));

        if ($length !== '' && ctype_digit($length) && (int) $length > $max) {
            $body->close();
            throw new \Aimeos\Cms\Exception('Remote file exceeds the maximum upload size');
        }

        if (! ($tmp = tmpfile())) {
            $body->close();
            throw new \Aimeos\Cms\Exception('Unable to create temporary file');
        }

        $size = 0;

        while (! $body->eof()) {
            $chunk = $body->read(min(1048576, $max - $size + 1));
            $size += strlen($chunk);

            if ($size > $max) {
                $body->close();
                fclose($tmp);
                throw new \Aimeos\Cms\Exception('Remote file exceeds the maximum upload size');
            }

            fwrite($tmp, $chunk);
        }

        $body->close();
        fseek($tmp, 0);

        return $tmp;
    }

    /**
     * Adds the XML declaration needed by fileinfo to recognize plain SVG markup.
     *
     * @param  resource  $resource
     */
    protected function prepareSvgResource($resource): void
    {
        rewind($resource);
        $content = stream_get_contents($resource);

        if (! is_string($content)) {
            throw new \Aimeos\Cms\Exception('Unable to read SVG file');
        }

        $normalized = (string) preg_replace('/^\xEF\xBB\xBF/', '', $content);
        $normalized = strtr($normalized, [
            '&ns_extend;' => 'http://ns.adobe.com/Extensibility/1.0/',
            '&ns_ai;' => 'http://ns.adobe.com/AdobeIllustrator/10.0/',
            '&ns_graphs;' => 'http://ns.adobe.com/Graphs/1.0/',
            '&ns_vars;' => 'http://ns.adobe.com/Variables/1.0/',
            '&ns_imrep;' => 'http://ns.adobe.com/ImageReplacement/1.0/',
            '&ns_sfw;' => 'http://ns.adobe.com/SaveForWeb/1.0/',
            '&ns_custom;' => 'http://ns.adobe.com/GenericCustomNamespace/1.0/',
            '&ns_adobe_xpath;' => 'http://ns.adobe.com/AdobeXPath/1.0/',
        ]);

        if (preg_match('/^\s*<\?xml\b/i', $normalized) !== 1) {
            $normalized = '<?xml version="1.0" encoding="UTF-8"?>'."\n".$normalized;
        }

        if ($normalized !== $content) {
            rewind($resource);

            if (! ftruncate($resource, 0) || fwrite($resource, $normalized) !== strlen($normalized)) {
                throw new \Aimeos\Cms\Exception('Unable to normalize SVG file');
            }
        }

        rewind($resource);
    }

    /**
     * Creates a Pagible page with content, version, and search index.
     *
     * @param  array<string, mixed>  $pageData
     * @param  array<int, array<string, mixed>>  $contentElements
     */
    protected function createPage(array $pageData, array $contentElements, Page $parent): Page
    {
        $page = Page::forceCreate($pageData + ['content' => $contentElements]);
        $page->appendToNode($parent)->save();

        return $page;
    }

    /**
     * Builds the Pagible page version payload.
     *
     * @param  array<string, mixed>  $pageData
     * @param  array<int, array<string, mixed>>  $contentElements
     * @return array<string, mixed>
     */
    protected function buildVersionData(array $pageData, array $contentElements): array
    {
        return [
            'lang' => $this->lang,
            'data' => $pageData,
            'aux' => ['content' => $contentElements],
            'editor' => $this->editor,
        ];
    }

    /**
     * Creates a version for a page and publishes it.
     *
     * @param  array<string, mixed>  $pageData
     * @param  array<int, array<string, mixed>>  $contentElements
     * @param  string[]  $fileIds
     * @param  string[]  $elementIds
     */
    protected function createVersion(Page $page, array $pageData, array $contentElements, array $fileIds, array $elementIds = []): void
    {
        $version = $page->versions()->forceCreate($this->buildVersionData($pageData, $contentElements));

        if (! empty($fileIds)) {
            $version->files()->attach($fileIds);
        }

        if (! empty($elementIds)) {
            $version->elements()->attach($elementIds);
        }

        $page->forceFill(['latest_id' => $version->id])->saveQuietly();
        $page->publish($version);
    }

    /**
     * Fetches active tt_content records grouped by page ID.
     *
     * @return Collection<int|string, mixed>
     */
    protected function fetchContentElements(): Collection
    {
        $this->contentRecords = DB::connection($this->t3Connection)
            ->table('tt_content')
            ->where('deleted', 0)
            ->where('hidden', 0)
            ->whereIn('sys_language_uid', [0, -1])
            ->orderBy('sorting', 'asc')
            ->get()
            ->keyBy('uid');

        return $this->contentRecords->values()->groupBy('pid');
    }

    /**
     * Fetches database-backed TYPO3 backend layouts keyed by UID.
     *
     * @return Collection<int|string, mixed>
     */
    protected function fetchBackendLayouts(): Collection
    {
        if (! Schema::connection($this->t3Connection)->hasTable('backend_layout')) {
            return Collection::make();
        }

        return DB::connection($this->t3Connection)
            ->table('backend_layout')
            ->where('deleted', 0)
            ->where('hidden', 0)
            ->get()
            ->keyBy('uid');
    }

    /**
     * Fetches visible Bootstrap Package accordion items grouped by content UID.
     *
     * @return Collection<int|string, mixed>
     */
    protected function fetchAccordionItems(): Collection
    {
        if (! Schema::connection($this->t3Connection)->hasTable('tx_bootstrappackage_accordion_item')) {
            return Collection::make();
        }

        return DB::connection($this->t3Connection)
            ->table('tx_bootstrappackage_accordion_item')
            ->where('deleted', 0)
            ->where('hidden', 0)
            ->whereIn('sys_language_uid', [0, -1])
            ->orderBy('sorting', 'asc')
            ->get()
            ->groupBy('tt_content');
    }

    /**
     * Fetches visible Bootstrap Package carousel items grouped by content UID.
     *
     * @return Collection<int|string, mixed>
     */
    protected function fetchCarouselItems(): Collection
    {
        if (! Schema::connection($this->t3Connection)->hasTable('tx_bootstrappackage_carousel_item')) {
            return Collection::make();
        }

        return DB::connection($this->t3Connection)
            ->table('tx_bootstrappackage_carousel_item')
            ->where('deleted', 0)
            ->where('hidden', 0)
            ->whereIn('sys_language_uid', [0, -1])
            ->orderBy('sorting', 'asc')
            ->get()
            ->groupBy('tt_content');
    }

    /**
     * Fetches the primary TYPO3 domain keyed by its root page UID.
     *
     * Legacy TYPO3 installations can assign multiple domains to one page tree.
     * The first visible record by TYPO3 sorting is the primary domain.
     *
     * @return array<int, string>
     */
    protected function fetchDomains(): array
    {
        if (! Schema::connection($this->t3Connection)->hasTable('sys_domain')) {
            return [];
        }

        $domains = [];
        $records = DB::connection($this->t3Connection)
            ->table('sys_domain')
            ->where('hidden', 0)
            ->where('domainName', '<>', '')
            ->orderBy('sorting', 'asc')
            ->orderBy('uid', 'asc')
            ->get(['pid', 'domainName']);

        foreach ($records as $record) {
            $domains[(int) $record->pid] ??= (string) $record->domainName;
        }

        return $domains;
    }

    /**
     * Fetches sys_file_reference records grouped by content element UID.
     *
     * @return Collection<int|string, mixed>
     */
    protected function fetchFileReferences(): Collection
    {
        return DB::connection($this->t3Connection)
            ->table('sys_file_reference')
            ->where('deleted', 0)
            ->where('hidden', 0)
            ->where('tablenames', 'tt_content')
            ->whereIn('fieldname', ['image', 'media', 'assets'])
            ->orderBy('sorting_foreign', 'asc')
            ->get()
            ->groupBy('uid_foreign');
    }

    /**
     * Fetches carousel background-image references grouped by carousel item UID.
     *
     * @return Collection<int|string, mixed>
     */
    protected function fetchCarouselFileReferences(): Collection
    {
        if (! Schema::connection($this->t3Connection)->hasTable('tx_bootstrappackage_carousel_item')) {
            return Collection::make();
        }

        return DB::connection($this->t3Connection)
            ->table('sys_file_reference')
            ->where('deleted', 0)
            ->where('hidden', 0)
            ->where('tablenames', 'tx_bootstrappackage_carousel_item')
            ->where('fieldname', 'background_image')
            ->orderBy('sorting_foreign', 'asc')
            ->get()
            ->groupBy('uid_foreign');
    }

    /**
     * Fetches non-deleted TYPO3 pages in default language.
     *
     * @return Collection<int, mixed>
     */
    protected function fetchPages(): Collection
    {
        return DB::connection($this->t3Connection)
            ->table('pages')
            ->where('deleted', 0)
            ->where('sys_language_uid', 0)
            ->where('t3ver_wsid', 0)
            ->whereIn('doktype', [1, 3, 4])
            ->orderBy('sorting', 'asc')
            ->get();
    }

    /**
     * Fetches sys_file records keyed by UID.
     *
     * @return Collection<int, mixed>
     */
    protected function fetchSysFiles(): Collection
    {
        return DB::connection($this->t3Connection)
            ->table('sys_file')
            ->get()
            ->keyBy(fn ($file) => (int) $file->uid);
    }

    /**
     * Gets or creates the root page for a domain.
     *
     * @param  Collection<int|string, mixed>  $contentElements
     */
    protected function getRootPage(object $t3Root, string $domain, Collection $contentElements): Page
    {
        $page = $this->findRootPage($domain);

        if ($page) {
            $this->info("Using existing root page: {$page->name} ({$domain})");

            return $page;
        }

        $slug = $this->slugFromPath($t3Root->slug); // @phpstan-ignore property.notFound
        $pageData = $this->buildPageData($t3Root, $slug, $domain);
        $pageData['tag'] = 'root';
        $records = $this->recordsForPage($t3Root, $contentElements);
        $pageData['theme'] = $this->theme;
        $content = $this->buildContent($records);
        $page = $this->createRootPage($pageData, $content['elements'], $content['fileIds'], $content['elementIds']);

        $this->info("Created root page: {$t3Root->title} ({$domain})"); // @phpstan-ignore property.notFound

        return $page;
    }

    /**
     * Finds an imported root page for a domain.
     */
    protected function findRootPage(string $domain): ?Page
    {
        $page = Page::withTrashed()->where('tag', 'root')->where('domain', $domain)->first();

        if ($page?->trashed()) {
            $page->restore();
        }

        return $page;
    }

    /**
     * Finds an imported non-root page by its unique destination route.
     */
    protected function findPage(string $domain, string $path): ?Page
    {
        $page = Page::withTrashed()->where('domain', $domain)->where('path', $path)->first();

        if ($page?->trashed()) {
            $page->restore();
        }

        return $page;
    }

    /**
     * Creates and publishes a root page.
     *
     * @param  array<string, mixed>  $pageData
     * @param  array<int, array<string, mixed>>  $contentElements
     * @param  string[]  $fileIds
     * @param  string[]  $elementIds
     */
    protected function createRootPage(array $pageData, array $contentElements, array $fileIds, array $elementIds = []): Page
    {
        $page = Page::forceCreate($pageData + ['content' => $contentElements]);

        $this->createVersion($page, $pageData, $contentElements, $fileIds, $elementIds);

        return $page;
    }

    /**
     * Returns the source records used by a TYPO3 page.
     *
     * @param  Collection<int|string, mixed>  $contentElements
     * @return Collection<int, mixed>
     */
    protected function recordsForPage(object $t3Page, Collection $contentElements): Collection
    {
        $pageUid = (int) ($t3Page->uid ?? 0);
        $contentPageUid = $this->contentSourcePageUid($t3Page);
        $records = $this->sortRecordsBySourceLayout(
            $contentElements->get($contentPageUid, Collection::make()),
            $t3Page,
        );

        return $contentPageUid === $pageUid
            ? $records
            : $this->markSharedRecords($records, 'reference', $contentPageUid);
    }

    /**
     * Marks cloned TYPO3 records for conversion into reusable Pagible elements.
     *
     * @param  Collection<int, mixed>  $records
     * @return Collection<int, mixed>
     */
    protected function markSharedRecords(Collection $records, string $kind, int $sourcePage): Collection
    {
        return $records->map(function ($record) use ($kind, $sourcePage) {
            $data = get_object_vars($record);
            $data['_pagible_shared'] = $kind;
            $data['_pagible_shared_page'] = $sourcePage;
            $data['_pagible_shared_uid'] = (int) ($data['uid'] ?? 0);

            return (object) $data;
        })->values();
    }

    /**
     * Resolves chained TYPO3 page-level content references.
     */
    protected function contentSourcePageUid(object $t3Page): int
    {
        $page = $t3Page;
        $uid = (int) ($page->uid ?? 0);
        $seen = [$uid => true];

        while (($sourceUid = (int) ($page->content_from_pid ?? 0)) > 0) {
            if (isset($seen[$sourceUid])) {
                throw new \RuntimeException("Cycle detected in TYPO3 page content references at page [{$sourceUid}].");
            }

            $seen[$sourceUid] = true;
            $uid = $sourceUid;

            if (! ($source = $this->t3Pages?->get($sourceUid))) {
                break;
            }

            $page = $source;
        }

        return $uid;
    }

    /**
     * Orders records like the TYPO3 frontend: backend-layout row/column order,
     * followed by the record sorting value inside each content column.
     *
     * @param  Collection<int, mixed>  $records
     * @return Collection<int, mixed>
     */
    protected function sortRecordsBySourceLayout(Collection $records, object $t3Page): Collection
    {
        $columns = $this->sourceLayoutColumns($t3Page);

        if ($columns === []) {
            return $records->values();
        }

        $positions = array_flip($columns);
        $fallback = count($positions);

        return $records->sort(function ($left, $right) use ($positions, $fallback): int {
            $leftColumn = (int) ($left->colPos ?? 0);
            $rightColumn = (int) ($right->colPos ?? 0);
            $leftPosition = $positions[$leftColumn] ?? $fallback;
            $rightPosition = $positions[$rightColumn] ?? $fallback;

            return [$leftPosition, (int) ($left->sorting ?? 0), (int) ($left->uid ?? 0)]
                <=> [$rightPosition, (int) ($right->sorting ?? 0), (int) ($right->uid ?? 0)];
        })->values();
    }

    /**
     * Returns the ordered TYPO3 content columns for the page's backend layout.
     *
     * @return list<int>
     */
    protected function sourceLayoutColumns(object $t3Page): array
    {
        $layout = trim((string) ($t3Page->backend_layout ?? ''));

        if ($layout === '') {
            $page = $t3Page;
            $seen = [];

            while ((int) ($page->pid ?? 0) > 0) {
                $uid = (int) ($page->uid ?? 0);

                if (isset($seen[$uid]) || ! ($page = $this->t3Pages?->get((int) $page->pid))) {
                    break;
                }

                $seen[$uid] = true;
                $layout = trim((string) ($page->backend_layout_next_level ?? ''));

                if ($layout !== '') {
                    break;
                }
            }
        }

        $config = match (true) {
            str_starts_with($layout, 'pagets__') => $this->pageTsBackendLayout(
                $t3Page,
                substr($layout, strlen('pagets__'))
            ),
            str_starts_with($layout, 'db__') => (string) data_get(
                $this->backendLayouts?->get((int) substr($layout, strlen('db__'))),
                'config',
                '',
            ),
            default => '',
        };

        preg_match_all('/\bcolPos\s*=\s*(-?\d+)/i', $config, $matches);

        return array_values(array_unique(array_map('intval', $matches[1])));
    }

    /**
     * Returns one inherited PageTSconfig backend-layout block.
     */
    protected function pageTsBackendLayout(object $t3Page, string $name): string
    {
        $pages = [];
        $page = $t3Page;
        $seen = [];

        while ($page) {
            $uid = (int) ($page->uid ?? 0);

            if (isset($seen[$uid])) {
                break;
            }

            $seen[$uid] = true;
            $pages[] = $page;
            $pid = (int) ($page->pid ?? 0);
            $page = $pid > 0 ? $this->t3Pages?->get($pid) : null;
        }

        $source = Collection::make(array_reverse($pages))
            ->map(fn ($item) => (string) ($item->TSconfig ?? ''))
            ->filter()
            ->implode("\n");
        $pattern = '/(?:^|\R)\s*mod\.web_layout\.BackendLayouts\.'.preg_quote($name, '/').'\s*\{/m';

        if (! preg_match_all($pattern, $source, $matches, PREG_OFFSET_CAPTURE) || empty($matches[0])) {
            return '';
        }

        $match = end($matches[0]);
        $start = strpos($source, '{', (int) $match[1]);

        if ($start === false) {
            return '';
        }

        $depth = 0;
        $length = strlen($source);

        for ($offset = $start; $offset < $length; $offset++) {
            if ($source[$offset] === '{') {
                $depth++;
            } elseif ($source[$offset] === '}' && --$depth === 0) {
                return substr($source, $start + 1, $offset - $start - 1);
            }
        }

        return '';
    }

    /**
     * Resolves the redirect target of TYPO3 external URL and selected-page shortcut records.
     *
     * @param  Collection<int, mixed>  $pages  TYPO3 pages keyed by UID
     */
    protected function redirectTarget(object $t3Page, Collection $pages): string
    {
        if ($t3Page->doktype == 3) { // @phpstan-ignore property.notFound
            return trim((string) $t3Page->url); // @phpstan-ignore property.notFound
        }

        if ($t3Page->doktype != 4 || $t3Page->shortcut_mode != 0) { // @phpstan-ignore property.notFound, property.notFound
            return '';
        }

        $target = $pages->get((int) $t3Page->shortcut); // @phpstan-ignore property.notFound

        return $target ? $this->redirectDestination($target, $pages, [(int) $t3Page->uid => true]) : ''; // @phpstan-ignore property.notFound
    }

    /**
     * Resolves a TYPO3 shortcut destination to an external URL or Pagible path.
     *
     * @param  Collection<int, mixed>  $pages  TYPO3 pages keyed by UID
     * @param  array<int, bool>  $seen
     */
    protected function redirectDestination(object $t3Page, Collection $pages, array $seen): string
    {
        $uid = (int) $t3Page->uid; // @phpstan-ignore property.notFound

        if (isset($seen[$uid])) {
            return '';
        }

        $seen[$uid] = true;

        if ($t3Page->doktype == 3) { // @phpstan-ignore property.notFound
            return trim((string) $t3Page->url); // @phpstan-ignore property.notFound
        }

        if ($t3Page->doktype == 4) { // @phpstan-ignore property.notFound
            if ($t3Page->shortcut_mode != 0) { // @phpstan-ignore property.notFound
                return '';
            }

            $target = $pages->get((int) $t3Page->shortcut); // @phpstan-ignore property.notFound

            return $target ? $this->redirectDestination($target, $pages, $seen) : '';
        }

        return '/'.$this->slugFromPath($t3Page->slug); // @phpstan-ignore property.notFound
    }

    /**
     * Maps TYPO3 header_layout to heading level.
     */
    protected function headerLevel(?string $layout): int
    {
        return match ($layout) {
            '1' => 1,
            '2' => 2,
            '3' => 3,
            '4' => 4,
            '5' => 5,
            default => 2,
        };
    }

    /**
     * Converts basic HTML to Markdown.
     */
    protected function htmlToMarkdown(string $html): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $html);

        $text = (string) preg_replace('/<h([1-6])[^>]*>(.*?)<\/h[1-6]>/is', "\n".'$2'."\n", $text);
        $text = (string) preg_replace('/<strong>(.*?)<\/strong>/is', '**$1**', $text);
        $text = (string) preg_replace('/<b>(.*?)<\/b>/is', '**$1**', $text);
        $text = (string) preg_replace('/<em>(.*?)<\/em>/is', '*$1*', $text);
        $text = (string) preg_replace('/<i>(.*?)<\/i>/is', '*$1*', $text);
        $text = (string) preg_replace('/<a[^>]+href=["\']([^"\']+)["\'][^>]*>(.*?)<\/a>/is', '[$2]($1)', $text);
        $text = (string) preg_replace('/<code>(.*?)<\/code>/is', '`$1`', $text);
        $text = (string) preg_replace('/<\/li>\s*<li\b/is', '</li><li', $text);
        $text = (string) preg_replace('/<li[^>]*>(.*?)<\/li>/is', "- $1\n", $text);
        $text = (string) preg_replace('/<\/?(ul|ol)[^>]*>/is', "\n", $text);
        $text = (string) preg_replace('/<p[^>]*>(.*?)<\/p>/is', "$1\n\n", $text);
        $text = (string) preg_replace('/<br\s*\/?>/', "\n", $text);
        $text = strip_tags($text);
        $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
        // Indentation between TYPO3 <li> tags is formatting whitespace, not
        // list nesting. Keeping it would turn sibling items into nested
        // Markdown lists when the imported feature accordions are rendered.
        $text = (string) preg_replace('/^[\t ]+(?=-\s)/m', '', $text);
        $text = (string) preg_replace('/\n{3,}/', "\n\n", $text);

        return trim($text);
    }

    /**
     * Maps TYPO3 imageorient to Pagible position.
     */
    protected function imagePosition(int $orient): string
    {
        return match ($orient) {
            0, 1, 2 => 'auto',
            17, 18 => 'start',
            25, 26 => 'end',
            126 => 'start',
            125 => 'end',
            default => 'auto',
        };
    }

    /**
     * Imports the first file reference for a tt_content record.
     */
    protected function importFileForContent(int $contentUid): ?string
    {
        $refs = $this->fileRefs->get($contentUid);

        if (! $refs || $refs->isEmpty()) {
            return null;
        }

        return $this->importFileSafely(
            fn () => $this->importFileReference($refs->first())
        );
    }

    /**
     * Imports one TYPO3 file reference.
     */
    protected function importFileReference(object $ref): ?string
    {
        $reference = get_object_vars($ref);
        $sysFile = $this->sysFiles->get((int) ($reference['uid_local'] ?? 0));

        if (! $sysFile) {
            return null;
        }

        $name = ($reference['title'] ?? '') ?: ($reference['alternative'] ?? '') ?: $sysFile->name;
        $extension = trim((string) ($sysFile->extension ?? ''))
            ?: pathinfo((string) $sysFile->identifier, PATHINFO_EXTENSION);
        $mime = $this->normalizeMime((string) $sysFile->mime_type, $extension);
        $path = $this->resolveFilePath($sysFile->identifier);

        return $this->createFile($mime, $name, $path);
    }

    /**
     * Imports only explicitly selected pages, updating matching destination
     * routes with a new published version and creating missing pages when their
     * destination parent already exists.
     *
     * @param  Collection<int, mixed>  $selectedPages
     * @param  Collection<int, mixed>  $pages
     * @param  Collection<int|string, mixed>  $contentElements
     */
    protected function importSelectedPages(Collection $selectedPages, Collection $pages, Collection $contentElements): void
    {
        $pagesById = $pages->keyBy(fn ($page) => (int) $page->uid);
        $processed = Collection::make();
        $imported = 0;
        $updated = 0;

        $selectedPages = $selectedPages->sortBy(
            fn ($page) => $this->sourcePageDepth($page, $pagesById)
        )->values();

        foreach ($selectedPages as $t3Page) {
            $filesBefore = clone $this->createdFiles;
            $urlsBefore = clone $this->createdFileUrls;

            try {
                $result = DB::connection(config('cms.db', 'sqlite'))->transaction(function () use ($t3Page, $pagesById, $contentElements, $processed, $filesBefore) {
                    try {
                        return $this->importSelectedPage($t3Page, $pagesById, $contentElements, $processed);
                    } catch (\Throwable $e) {
                        $this->removeFilesCreatedAfter($filesBefore);

                        throw $e;
                    }
                });
            } catch (\Throwable $e) {
                $this->createdFiles = $filesBefore;
                $this->createdFileUrls = $urlsBefore;
                $this->error("  Failed to import [{$t3Page->uid}] {$t3Page->title}: ".$e->getMessage());

                continue;
            }

            $processed->put((int) $t3Page->uid, $result['page']);
            $result['updated'] ? $updated++ : $imported++;
            $action = $result['updated'] ? 'Updated' : 'Imported';
            $path = $result['path'] === '' ? '/' : '/'.$result['path'];
            $this->info("  {$action}: {$t3Page->title} ({$path}) [{$result['domain']}]");
        }

        $importedLabel = $imported === 1 ? 'page' : 'pages';
        $updatedLabel = $updated === 1 ? 'page' : 'pages';
        $this->info("Import complete. {$imported} {$importedLabel} imported, {$updated} {$updatedLabel} updated.");
    }

    /**
     * Imports or updates one selected TYPO3 page.
     *
     * @param  Collection<int, mixed>  $pagesById
     * @param  Collection<int|string, mixed>  $contentElements
     * @param  Collection<int|string, Page>  $processed
     * @return array{page: Page, updated: bool, path: string, domain: string}
     */
    protected function importSelectedPage(object $t3Page, Collection $pagesById, Collection $contentElements, Collection $processed): array
    {
        $rootRecord = $this->sourceRootPage($t3Page, $pagesById);
        $domain = $this->domainMap[(int) $rootRecord->uid] ?? $this->domain; // @phpstan-ignore property.notFound
        $path = $this->slugFromPath($t3Page->slug); // @phpstan-ignore property.notFound
        $to = $this->redirectTarget($t3Page, $pagesById);
        $pageData = $this->buildPageData($t3Page, $path, $domain, $to);
        $isRoot = (int) ($t3Page->pid ?? 0) === 0 || ! empty($t3Page->is_siteroot);
        $pageData['tag'] = $isRoot ? 'root' : $pageData['tag'];
        $page = $isRoot ? $this->findRootPage($domain) : $this->findPage($domain, $path);
        $updated = $page !== null;
        $parent = null;

        if (! $page && ! $isRoot) {
            $parentRecord = $pagesById->get((int) ($t3Page->pid ?? 0));

            if (! $parentRecord) {
                throw new \RuntimeException('TYPO3 parent page is missing from the source tree.');
            }

            $parent = $processed->get((int) $parentRecord->uid)
                ?: $this->findSourcePage($parentRecord, $domain);

            if (! $parent) {
                throw new \RuntimeException(sprintf(
                    'Destination parent page [%d] %s has not been imported.',
                    (int) $parentRecord->uid,
                    (string) $parentRecord->title,
                ));
            }
        }

        $records = $this->recordsForPage($t3Page, $contentElements);
        $pageData['theme'] = $this->theme;
        $content = $this->buildContent($records);

        if ($page) {
            $this->createVersion($page, $pageData, $content['elements'], $content['fileIds'], $content['elementIds']);
        } elseif ($isRoot) {
            $page = $this->createRootPage($pageData, $content['elements'], $content['fileIds'], $content['elementIds']);
        } else {
            /** @var Page $parent */
            $page = $this->createPage($pageData, $content['elements'], $parent);
            $this->createVersion($page, $pageData, $content['elements'], $content['fileIds'], $content['elementIds']);

            if ((int) ($t3Page->crdate ?? 0) > 0) {
                $page->update(['created_at' => date('Y-m-d H:i:s', (int) ($t3Page->crdate ?? 0))]);
            }
        }

        return ['page' => $page, 'updated' => $updated, 'path' => $path, 'domain' => $domain];
    }

    /**
     * Finds the destination counterpart of a TYPO3 source page.
     */
    protected function findSourcePage(object $t3Page, string $domain): ?Page
    {
        if ((int) ($t3Page->pid ?? 0) === 0 || ! empty($t3Page->is_siteroot)) {
            return $this->findRootPage($domain);
        }

        return $this->findPage($domain, $this->slugFromPath($t3Page->slug)); // @phpstan-ignore property.notFound
    }

    /**
     * Returns the root source record for a TYPO3 page.
     *
     * @param  Collection<int, mixed>  $pagesById
     */
    protected function sourceRootPage(object $t3Page, Collection $pagesById): object
    {
        $page = $t3Page;
        $seen = [];

        while ((int) ($page->pid ?? 0) > 0 && empty($page->is_siteroot)) {
            $uid = (int) ($page->uid ?? 0);

            if (isset($seen[$uid])) {
                throw new \RuntimeException("Cycle detected in TYPO3 page tree at page [{$uid}].");
            }

            $seen[$uid] = true;
            $parent = $pagesById->get((int) $page->pid);

            if (! $parent) {
                throw new \RuntimeException(sprintf(
                    'TYPO3 parent page [%d] is missing from the source tree.',
                    (int) $page->pid,
                ));
            }

            $page = $parent;
        }

        return $page;
    }

    /**
     * Returns a source page's depth for deterministic parent-first imports.
     *
     * @param  Collection<int, mixed>  $pagesById
     */
    protected function sourcePageDepth(object $t3Page, Collection $pagesById): int
    {
        $page = $t3Page;
        $seen = [];
        $depth = 0;

        while ((int) ($page->pid ?? 0) > 0) {
            $uid = (int) ($page->uid ?? 0);

            if (isset($seen[$uid])) {
                throw new \RuntimeException("Cycle detected in TYPO3 page tree at page [{$uid}].");
            }

            $seen[$uid] = true;
            $page = $pagesById->get((int) $page->pid)
                ?? throw new \RuntimeException(sprintf(
                    'TYPO3 parent page [%d] is missing from the source tree.',
                    (int) $page->pid,
                ));
            $depth++;
        }

        return $depth;
    }

    /**
     * Imports all pages recursively following the TYPO3 page hierarchy.
     *
     * @param  Collection<int, mixed>  $pages
     * @param  Collection<int|string, mixed>  $contentElements
     */
    protected function importPages(Collection $pages, Collection $contentElements): void
    {
        $pageMap = $pages->groupBy('pid');
        $pagesById = $pages->keyBy(fn ($page) => (int) $page->uid);
        $imported = 0;
        $reused = 0;

        $importChildren = function (int $parentUid, Page $parentPage, string $domain) use (&$importChildren, $pageMap, $pagesById, $contentElements, &$imported, &$reused) {
            $children = $pageMap->get($parentUid, Collection::make());

            foreach ($children as $t3Page) {
                $filesBefore = clone $this->createdFiles;
                $urlsBefore = clone $this->createdFileUrls;

                try {
                    $result = DB::connection(config('cms.db', 'sqlite'))->transaction(function () use ($t3Page, $parentPage, $domain, $pagesById, $contentElements, $filesBefore) {
                        try {
                            $slug = $this->slugFromPath($t3Page->slug);

                            if ($page = $this->findPage($domain, $slug)) {
                                return ['page' => $page, 'path' => $slug, 'reused' => true];
                            }

                            $to = $this->redirectTarget($t3Page, $pagesById);
                            $pageData = $this->buildPageData($t3Page, $slug, $domain, $to);
                            $records = $this->recordsForPage($t3Page, $contentElements);
                            $pageData['theme'] = $this->theme;
                            $content = $this->buildContent($records);

                            $page = $this->createPage($pageData, $content['elements'], $parentPage);
                            $this->createVersion($page, $pageData, $content['elements'], $content['fileIds'], $content['elementIds']);

                            if ($t3Page->crdate > 0) {
                                $page->update(['created_at' => date('Y-m-d H:i:s', $t3Page->crdate)]);
                            }

                            return ['page' => $page, 'path' => $slug, 'reused' => false];
                        } catch (\Throwable $e) {
                            $this->removeFilesCreatedAfter($filesBefore);

                            throw $e;
                        }
                    });
                } catch (\Throwable $e) {
                    $this->createdFiles = $filesBefore;
                    $this->createdFileUrls = $urlsBefore;
                    $this->error("  Failed to import [{$t3Page->uid}] {$t3Page->title}: ".$e->getMessage());

                    continue;
                }

                if ($result['reused']) {
                    $reused++;
                    $this->info("  Reused: {$t3Page->title} (/{$result['path']}) [{$domain}] (destination route already exists)");
                } else {
                    $imported++;
                    $this->info("  Imported: {$t3Page->title} (/{$result['path']}) [{$domain}]");
                }

                /** @var Page $childParent */
                $childParent = $result['page'];
                $importChildren($t3Page->uid, $childParent, $domain);
            }
        };

        $rootPages = $pageMap->get(0, Collection::make());

        foreach ($rootPages as $t3Root) {
            $domain = $this->domainMap[$t3Root->uid] ?? $this->domain;
            $root = $this->getRootPage($t3Root, $domain, $contentElements);
            $this->info("Importing tree: {$t3Root->title} ({$domain})");
            $importChildren($t3Root->uid, $root, $domain);
        }

        $rootCount = $rootPages->count();
        $total = $imported + $reused + $rootCount;
        $totalLabel = $total === 1 ? 'page' : 'pages';
        $pageLabel = $imported === 1 ? 'child page' : 'child pages';
        $rootLabel = $rootCount === 1 ? 'root page' : 'root pages';
        $reusedLabel = $reused === 1 ? 'existing child route reused' : 'existing child routes reused';
        $reusedSummary = $reused > 0 ? ", {$reused} {$reusedLabel}" : '';

        $this->info("Import complete. {$total} TYPO3 {$totalLabel} processed ({$rootCount} {$rootLabel}, {$imported} {$pageLabel} imported{$reusedSummary}).");
    }

    /**
     * Guesses MIME type from file extension.
     */
    protected function guessMimeFromExtension(string $ext): string
    {
        return match (strtolower($ext)) {
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
     * Corrects generic TYPO3 MIME values when the file extension is definitive.
     */
    protected function normalizeMime(string $mime, string $extension): string
    {
        $mime = strtolower(trim($mime));
        $guessed = $this->guessMimeFromExtension($extension);

        if ($mime === '' || $mime === 'application/octet-stream'
            || ($mime === 'text/plain' && $guessed === 'image/svg+xml')) {
            return $guessed;
        }

        return $mime;
    }

    /**
     * Prints a dry run summary of page hierarchy.
     *
     * @param  Collection<int, mixed>  $pages
     */
    protected function printDryRun(Collection $pages): void
    {
        $pageMap = $pages->groupBy('pid');

        $printTree = function (int $pid, int $depth) use (&$printTree, $pageMap) {
            $children = $pageMap->get($pid, Collection::make());

            foreach ($children as $page) {
                $indent = str_repeat('  ', $depth);
                $type = $page->doktype == 4 ? ' [shortcut]' : '';
                $hidden = $page->hidden ? ' [hidden]' : '';
                $this->line("{$indent}[{$page->uid}] {$page->title} ({$page->slug}){$type}{$hidden}");
                $printTree($page->uid, $depth + 1);
            }
        };

        $printTree(0, 0);
        $this->info('Dry run complete. No changes were made.');
    }

    /**
     * Prints explicitly selected pages for a dry run.
     *
     * @param  Collection<int, mixed>  $pages
     */
    protected function printSelectedDryRun(Collection $pages): void
    {
        foreach ($pages as $page) {
            $type = $page->doktype == 4 ? ' [shortcut]' : '';
            $hidden = $page->hidden ? ' [hidden]' : '';
            $this->line("[{$page->uid}] {$page->title} ({$page->slug}){$type}{$hidden}");
        }

        $this->info('Dry run complete. No changes were made.');
    }

    /**
     * Resolves a TYPO3 file identifier to a full URL or path.
     */
    protected function resolveFilePath(string $identifier): string
    {
        $identifier = ltrim($identifier, '/');

        if ($this->fileBase) {
            return $this->fileBase.'/'.$identifier;
        }

        return $identifier;
    }

    /**
     * Builds the conventional TYPO3 public file base from the first imported domain.
     */
    protected function defaultFileBase(): string
    {
        $host = $this->domain;

        if ($host === '') {
            $host = (string) reset($this->domainMap);
        }

        if ($host === '') {
            return '';
        }

        $base = str_starts_with($host, 'http') ? $host : 'https://'.$host;

        return rtrim($base, '/').'/fileadmin';
    }

    /**
     * Parses --domain options into a default domain and UID-to-domain map.
     *
     * Accepts plain domains (e.g. --domain=example.com) as default,
     * or domains with root page UID (e.g. --domain=example.com:1) for specific trees.
     */
    protected function parseDomainOption(): string
    {
        $default = '';

        foreach ((array) $this->option('domain') as $entry) {
            $parts = explode(':', (string) $entry, 2);

            if (! empty($parts[1])) {
                $this->domainMap[(int) $parts[1]] = $parts[0];
            } else {
                $default = $parts[0];
            }
        }

        return $default;
    }

    /**
     * Sets up multi-tenancy if a tenant option is provided.
     */
    protected function setupTenant(): void
    {
        if ($tenant = $this->option('tenant')) {
            Tenancy::$callback = function () use ($tenant) {
                return $tenant;
            };
        }
    }

    /**
     * Extracts a clean slug from a TYPO3 page slug/path.
     */
    protected function slugFromPath(?string $path): string
    {
        return trim($path ?? '', '/');
    }
}
