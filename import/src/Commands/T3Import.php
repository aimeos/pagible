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

    /** @var Collection<int|string, mixed> */
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

    /** @var Collection<int|string, mixed> */
    protected Collection $createdFiles;

    /** @var Collection<string, string> */
    protected Collection $createdFileUrls;

    /** @var array<int, string> */
    protected array $domainMap = [];

    /** @var Collection<int|string, mixed>|null */
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
        $this->t3Pages = $pages->keyBy('uid');
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
     * for parent, domain, shortcut, link and inherited-footer resolution.
     *
     * @param  Collection<int|string, mixed>  $pages
     * @param  list<int>  $ids
     * @return Collection<int|string, mixed>
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
     * @param  Collection<int|string, mixed>  $records
     * @return array{elements: array<int, array<string, mixed>>, fileIds: string[], elementIds: string[]}
     */
    protected function buildContent(Collection $records): array
    {
        $records = $this->expandReferencedRecords($records);
        $elements = [];
        $fileIds = [];
        $elementIds = [];
        $homepage = $this->isAimeosHomepage($records);
        $b2b = $this->isAimeosB2b($records);
        $marketplace = $this->isAimeosMarketplace($records);
        $saas = $this->isAimeosSaas($records);
        $typo3 = $this->isAimeosTypo3($records);
        $laravelPlatform = $this->isAimeosLaravelPlatform($records);
        $features = $this->isAimeosFeatures($records);
        $showcases = $this->isAimeosShowcases($records);
        $caseStudies = $this->isAimeosCaseStudies($records);
        $caseStudy = $this->isAimeosCaseStudy($records);
        $roadmap = $this->isAimeosRoadmap($records);
        $contact = $this->isAimeosContact($records);
        $structuredCaseStudy = $caseStudy && \Aimeos\Cms\Schema::get('aimeos');
        $structuredContact = $contact && \Aimeos\Cms\Schema::get('aimeos');
        $sharedContext = match (true) {
            $b2b => 'b2b',
            $marketplace => 'marketplace',
            $saas => 'saas',
            $typo3 => 'typo3',
            $laravelPlatform => 'laravel-platform',
            $features => 'features',
            $showcases => 'showcases',
            $caseStudies => 'case-studies',
            $caseStudy => 'case-study',
            $homepage => 'homepage',
            default => 'default',
        };

        foreach ($records as $record) {
            if ($features && $this->isFeaturesHead($record)) {
                continue;
            }

            if ($structuredCaseStudy
                && $this->isCaseStudyLayoutRecord($record)
                && ! $this->isCaseStudyStage($record)) {
                continue;
            }

            if ($structuredContact
                && $this->isContactLayoutRecord($record)
                && ! $this->isContactStage($record)) {
                continue;
            }

            $group = in_array((int) ($record->colPos ?? 0), [10, 11, 12], true)
                ? 'footer'
                : 'main';
            $filesBefore = clone $this->createdFiles;
            $urlsBefore = clone $this->createdFileUrls;
            $this->contentUid = (string) ($record->uid ?? '?');

            try {
                $result = DB::connection(config('cms.db', 'sqlite'))->transaction(function () use ($record, $records, $group, $homepage, $b2b, $marketplace, $saas, $typo3, $laravelPlatform, $features, $showcases, $structuredCaseStudy, $structuredContact, $roadmap, $sharedContext, $filesBefore) {
                    try {
                        $result = match (true) {
                            $group === 'footer' && $record->CType === 'text' => $this->convertFooterText($record), // @phpstan-ignore property.notFound
                            $group === 'footer' => $this->convertContentElement($record, false),
                            $structuredCaseStudy && $this->isCaseStudyStage($record) => $this->convertCaseStudy($records),
                            $structuredContact && $this->isContactStage($record) => $this->convertContactPage($records),
                            $b2b => $this->convertProductLandingContentElement($record, 'b2b'),
                            $marketplace => $this->convertProductLandingContentElement($record, 'marketplace'),
                            $saas => $this->convertProductLandingContentElement($record, 'saas'),
                            $typo3 => $this->convertProductLandingContentElement($record, 'typo3'),
                            $laravelPlatform && $record->CType === 'html' => $this->convertLaravelPlatformHtml($record), // @phpstan-ignore property.notFound
                            $laravelPlatform && \Aimeos\Cms\Schema::get('aimeos') && $record->CType === 'textpic' => $this->convertFeature($record), // @phpstan-ignore property.notFound
                            $features && \Aimeos\Cms\Schema::get('aimeos') && $record->CType === 'textpic' => $this->convertFeature($record), // @phpstan-ignore property.notFound
                            $features && \Aimeos\Cms\Schema::get('aimeos') && $record->CType === 'accordion' => $this->convertFeatureList($record), // @phpstan-ignore property.notFound
                            $showcases && \Aimeos\Cms\Schema::get('aimeos') && $record->CType === 'image' => $this->convertShowcases($record), // @phpstan-ignore property.notFound
                            $roadmap && $record->CType === 'html' => $this->convertRoadmapHtml($record), // @phpstan-ignore property.notFound
                            default => $this->convertContentElement($record, $homepage),
                        };

                        if (! $result) {
                            return null;
                        }

                        foreach ($result['elements'] as &$element) {
                            $element['group'] = $group;
                        }
                        unset($element);

                        if (! isset($record->_pagible_shared)) {
                            return $result + ['elementIds' => []];
                        }

                        $record->_pagible_shared_context = $sharedContext.'-'.$group;

                        $references = [];
                        $elementIds = [];

                        foreach ($result['elements'] as $position => $element) {
                            $id = $this->storeSharedElement(
                                $record,
                                $element,
                                $result['fileIds'] ?? [],
                                $position,
                            );
                            $references[] = ['type' => 'reference', 'refid' => $id, 'group' => $group];
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
        $parts = [
            sha1($connection),
            (int) ($record->_pagible_shared_page ?? $record->pid ?? 0),
            (int) ($record->_pagible_shared_uid ?? $record->uid ?? 0),
            $position,
            $lang,
        ];

        if (($record->_pagible_shared ?? null) !== 'footer') {
            $parts[] = (string) ($record->_pagible_shared_context ?? 'default-main');
        }

        return implode(':', $parts);
    }

    /**
     * Returns the persistent, source-specific name used to find shared elements on re-import.
     */
    protected function sharedElementName(object $record, int $position): string
    {
        $parts = explode(':', $this->sharedElementKey($record, $position));
        $kind = ($record->_pagible_shared ?? null) === 'footer' ? 'footer' : 'element';
        $context = $kind === 'footer' ? '' : '/'.preg_replace('/[^a-z0-9-]+/i', '-', (string) end($parts));

        return sprintf(
            'TYPO3 %s %s/%d/%d/%d%s',
            $kind,
            substr($parts[0], 0, 12),
            (int) $parts[1],
            (int) $parts[2],
            (int) $parts[3],
            $context,
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
    protected function convertContentElement(object $record, bool $convertStats = true): ?array
    {
        $html = (string) ($record->bodytext ?? '');

        if (\Aimeos\Cms\Schema::get('aimeos')) {
            if (str_contains($html, 'landing users') && ($result = $this->convertLogoCloud($record))) {
                return $result;
            }

            if ($convertStats && str_contains($html, 'landing qualities') && ($result = $this->convertStats($record))) {
                return $result;
            }
        }

        return match ($record->CType) { // @phpstan-ignore property.notFound
            'header' => $this->convertHeader($record),
            'text' => $this->convertText($record),
            'textpic', 'textmedia' => $this->convertTextpic($record),
            'image' => $this->convertImage($record),
            'html' => $this->convertHtml($record),
            'accordion' => $this->convertAccordion($record),
            'shortcut' => $this->convertShortcut($record, $convertStats),
            default => $this->convertDefault($record),
        };
    }

    /**
     * Converts a TYPO3 content shortcut by importing its referenced records.
     *
     * @return array{elements: array<int, array<string, mixed>>, fileIds: string[]}|null
     */
    protected function convertShortcut(object $record, bool $convertStats = true): ?array
    {
        preg_match_all('/\d+/', (string) ($record->records ?? ''), $matches);
        $elements = [];
        $fileIds = [];

        foreach (array_unique(array_map('intval', $matches[0] ?? [])) as $uid) {
            $target = $this->contentRecords?->get($uid);

            if (! $target || (int) ($target->uid ?? 0) === (int) ($record->uid ?? 0)) {
                continue;
            }

            $result = $this->convertContentElement($target, $convertStats);

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
     * @param  Collection<int|string, mixed>  $records
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

        $copy = clone $record;

        if ($placement) {
            $copy->colPos = (int) ($placement->colPos ?? $copy->colPos ?? 0);
            $copy->sorting = (int) ($placement->sorting ?? $copy->sorting ?? 0);
        }

        if ($kind !== '') {
            $copy->_pagible_shared = $kind;
            $copy->_pagible_shared_page = (int) ($record->_pagible_shared_page ?? $record->pid ?? 0);
            $copy->_pagible_shared_uid = $uid;
        }

        return [$copy];
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
     * Converts the Aimeos customer strip into an editable logo-cloud element.
     *
     * @return array{elements: array<int, array<string, mixed>>, fileIds: string[]}|null
     */
    protected function convertLogoCloud(object $record): ?array
    {
        $document = $this->htmlDocument((string) ($record->bodytext ?? ''));

        if (! $document) {
            return null;
        }

        $xpath = new \DOMXPath($document);
        $nodes = $xpath->query('//a[.//img]');
        $items = [];
        $fileIds = [];

        foreach ($nodes ?: [] as $node) {
            $image = $xpath->query('.//img', $node)?->item(0);
            $source = $image?->attributes?->getNamedItem('src')?->nodeValue
                ?: $image?->attributes?->getNamedItem('data-src')?->nodeValue;

            if (! $source || ! ($file = $this->importFileSafely(
                fn () => $this->importHtmlFile($source)
            ))) {
                continue;
            }

            $name = trim((string) ($image->attributes?->getNamedItem('alt')?->nodeValue
                ?: $node->attributes?->getNamedItem('title')?->nodeValue));
            $url = trim((string) $node->attributes?->getNamedItem('href')?->nodeValue);

            $items[] = [
                'name' => $name ?: 'Organization',
                'file' => ['id' => $file['id'], 'type' => 'file'],
                'url' => $this->rewriteTypo3Url($url) ?? $url,
            ];
            $fileIds[] = $file['id'];
        }

        if (count($items) < 2) {
            return null;
        }

        return ['elements' => [[
            'id' => Utils::uid(),
            'type' => 'aimeos::logo-cloud',
            'group' => 'main',
            'data' => ['items' => $items],
        ]], 'fileIds' => array_values(array_unique($fileIds))];
    }

    /**
     * Converts the Aimeos quality figures into an editable statistics element.
     *
     * @return array{elements: array<int, array<string, mixed>>, fileIds: string[]}|null
     */
    protected function convertStats(object $record): ?array
    {
        $document = $this->htmlDocument((string) ($record->bodytext ?? ''));

        if (! $document) {
            return null;
        }

        $xpath = new \DOMXPath($document);
        $nodes = $xpath->query(
            '//*[contains(concat(" ", normalize-space(@class), " "), " quality ")]'
        );
        $items = [];
        $fileIds = [];

        foreach ($nodes ?: [] as $node) {
            $labelNode = $xpath->query(
                './/*[contains(concat(" ", normalize-space(@class), " "), " header ")]',
                $node
            )?->item(0);
            $valueNode = $xpath->query(
                './/*[contains(concat(" ", normalize-space(@class), " "), " number ")]',
                $node
            )?->item(0);

            if (! $labelNode || ! $valueNode) {
                continue;
            }

            $link = $xpath->query('.//a[@href]', $node)?->item(0);
            $url = trim((string) $link?->attributes?->getNamedItem('href')?->nodeValue);
            $reviewNode = $xpath->query('.//*[@itemprop="reviewCount"]', $node)?->item(0);
            $text = $reviewNode ? $this->nodeText($reviewNode).' reviews' : '';
            $classes = preg_split('/\s+/', trim((string) $node->attributes?->getNamedItem('class')?->nodeValue)) ?: [];
            $kind = match (true) {
                in_array('github', $classes, true) => 'github',
                in_array('capterra', $classes, true) => 'capterra',
                in_array('downloads', $classes, true) => 'downloads',
                in_array('scrutinizer', $classes, true), in_array('code', $classes, true) => 'code',
                default => '',
            };
            $image = $xpath->query('.//a[@href]//img', $node)?->item(0);
            $source = $image?->attributes?->getNamedItem('src')?->nodeValue
                ?: $image?->attributes?->getNamedItem('data-src')?->nodeValue;
            $file = $source ? $this->importFileSafely(
                fn () => $this->importHtmlFile($source)
            ) : null;

            $item = [
                'value' => $this->nodeText($valueNode),
                'label' => $this->nodeText($labelNode),
                'text' => $text,
                'url' => $this->rewriteTypo3Url($url) ?? $url,
            ];

            if ($kind !== '') {
                $item['kind'] = $kind;
            }

            if ($file) {
                $item['file'] = ['id' => $file['id'], 'type' => 'file'];
                $fileIds[] = $file['id'];
            }

            $items[] = $item;
        }

        if (empty($items)) {
            return null;
        }

        return ['elements' => [[
            'id' => Utils::uid(),
            'type' => 'aimeos::stats',
            'group' => 'main',
            'data' => ['items' => $items],
        ]], 'fileIds' => array_values(array_unique($fileIds))];
    }

    /**
     * Keeps a TYPO3 footer heading and its body together as one grid item.
     *
     * @return array{elements: array<int, array<string, mixed>>, fileIds: string[]}|null
     */
    protected function convertFooterText(object $record): ?array
    {
        $header = trim((string) ($record->header ?? ''));
        $body = trim((string) ($record->bodytext ?? ''));

        // TYPO3 uses non-breaking-space list items as visual spacers. They
        // become empty bullets in Pagible and carry no footer content.
        $body = (string) preg_replace(
            '/<li\b[^>]*>(?:\s|&nbsp;|&#0*160;|&#x0*a0;)*<\/li>/i',
            '',
            $body
        );

        if ($header === '' && $body === '') {
            return null;
        }

        $slug = trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower($header)), '-');
        $html = '<div class="footer-links footer-'.$slug.'">';

        if ($header !== '') {
            $html .= '<h2>'.htmlspecialchars($header, ENT_QUOTES | ENT_HTML5, 'UTF-8').'</h2>';
        }

        $result = $this->rewriteHtmlFiles($html.$body.'</div>');

        return ['elements' => [[
            'id' => Utils::uid(),
            'type' => 'html',
            'group' => 'footer',
            'data' => ['text' => Utils::html($result['html'])],
        ]], 'fileIds' => $result['fileIds']];
    }

    /**
     * Parses an HTML fragment for semantic TYPO3 import conversions.
     */
    protected function htmlDocument(string $html): ?\DOMDocument
    {
        $document = new \DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);

        try {
            $loaded = $document->loadHTML(
                '<?xml encoding="UTF-8"><div id="pagible-import">'.$html.'</div>',
                LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
            );
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        return $loaded ? $document : null;
    }

    /**
     * Returns normalized text content from a DOM node.
     */
    protected function nodeText(\DOMNode $node): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $node->textContent));
    }

    /**
     * Converts an accordion content element into a questions element.
     *
     * @return array{elements: array<int, array<string, mixed>>}|null
     */
    protected function convertAccordion(object $record): ?array
    {
        $items = [];
        $children = $this->accordionItems?->get((int) ($record->uid ?? 0), Collection::make())
            ?? Collection::make();

        foreach ($children as $child) {
            if (empty($child->header) || empty($child->bodytext)) {
                continue;
            }

            $items[] = [
                'title' => $child->header,
                'text' => $this->htmlToMarkdown($this->rewriteTypo3Links($child->bodytext)),
            ];
        }

        if (empty($items) && ! empty($record->bodytext)) {
            $items[] = [
                'title' => $record->header ?: 'Item', // @phpstan-ignore property.notFound
                'text' => $this->htmlToMarkdown($this->rewriteTypo3Links($record->bodytext)),
            ];
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
        ]]];
    }

    /**
     * Converts one Aimeos feature highlight into an editable theme element.
     *
     * @return array{elements: array<int, array<string, mixed>>, fileIds: string[]}|null
     */
    protected function convertFeature(object $record): ?array
    {
        $fileId = $this->importFileForContent((int) ($record->uid ?? 0));
        $title = trim((string) ($record->header ?? ''));
        $body = trim((string) ($record->bodytext ?? ''));

        if (! $fileId || $title === '' || $body === '') {
            return $this->convertTextpic($record);
        }

        $result = $this->rewriteHtmlFiles($body);

        return ['elements' => [[
            'id' => Utils::uid(),
            'type' => 'aimeos::feature',
            'group' => 'main',
            'data' => [
                'title' => $title,
                'text' => $this->htmlToMarkdown($result['html']),
                'file' => ['id' => $fileId, 'type' => 'file'],
                'position' => $this->imagePosition((int) ($record->imageorient ?? 0)),
            ],
        ]], 'fileIds' => array_values(array_unique([$fileId, ...$result['fileIds']]))];
    }

    /**
     * Converts one Aimeos feature category and its TYPO3 accordion items.
     *
     * @return array{elements: array<int, array<string, mixed>>, fileIds: string[]}|null
     */
    protected function convertFeatureList(object $record): ?array
    {
        $children = $this->accordionItems?->get((int) ($record->uid ?? 0), Collection::make())
            ?? Collection::make();
        $items = [];
        $fileIds = [];

        foreach ($children as $child) {
            if (empty($child->header) || empty($child->bodytext)) {
                continue;
            }

            $result = $this->rewriteHtmlFiles($child->bodytext);
            $items[] = [
                'title' => $child->header,
                'text' => $this->htmlToMarkdown($result['html']),
            ];
            $fileIds = array_merge($fileIds, $result['fileIds']);
        }

        if (empty($items)) {
            return $this->convertAccordion($record);
        }

        return ['elements' => [[
            'id' => Utils::uid(),
            'type' => 'aimeos::feature-list',
            'group' => 'main',
            'data' => [
                'title' => (string) ($record->header ?? ''),
                'icon' => $this->featureIcon((string) ($record->header ?? '')),
                'items' => $items,
            ],
        ]], 'fileIds' => array_values(array_unique($fileIds))];
    }

    /**
     * Converts the column-based Aimeos contact page into one structured element.
     *
     * TYPO3 renders the records in each backend column inside a shared frontend
     * block. Keeping them as independent Pagible elements would split headings
     * from their link groups and expose the Form Framework configuration as raw
     * TypoScript instead of a working form.
     *
     * @param  Collection<int|string, mixed>  $records
     * @return array{elements: array<int, array<string, mixed>>, fileIds: string[]}|null
     */
    protected function convertContactPage(Collection $records): ?array
    {
        $record = fn (string $header) => $records->first(
            fn ($record) => trim((string) ($record->header ?? '')) === $header
        );
        $links = fn (int $column) => $records->first(
            fn ($record) => (int) ($record->colPos ?? 0) === $column
                && (string) ($record->CType ?? '') === 'html'
                && preg_match('/class=["\'][^"\']*\blinks\b/i', (string) ($record->bodytext ?? '')) === 1
        );

        $informed = $record('Stay informed');
        $support = $record('Help & Support');
        $contact = $record('Contact Us');
        $imprint = $record('Imprint');
        $privacy = $record('Privacy policy');
        $credits = $records->first(
            fn ($record) => trim((string) ($record->header ?? '')) === 'Credits'
                && trim((string) ($record->bodytext ?? '')) !== ''
        );

        if (! $informed || ! $support || ! $contact || ! $imprint || ! $privacy || ! $credits) {
            return null;
        }

        $fileIds = [];
        $informedLinks = $this->contactLinks($links(0), $fileIds);
        $supportLinks = $this->contactLinks($links(21), $fileIds);

        if ($informedLinks === [] || $supportLinks === []) {
            return null;
        }

        return ['elements' => [[
            'id' => Utils::uid(),
            'type' => 'aimeos::contact-page',
            'group' => 'main',
            'data' => [
                'informed_title' => (string) $informed->header,
                'informed_links' => $informedLinks,
                'support_title' => (string) $support->header,
                'support_links' => $supportLinks,
                'contact_title' => (string) $contact->header,
                'contact_text' => $this->contactMarkdown($contact, $fileIds),
                'form_title' => 'Your personal data',
                'mandatory_text' => '* Mandatory fields',
                'imprint_title' => (string) $imprint->header,
                'imprint_text' => $this->contactMarkdown($imprint, $fileIds),
                'privacy_title' => (string) $privacy->header,
                'privacy_text' => $this->contactMarkdown($privacy, $fileIds),
                'credits_title' => (string) $credits->header,
                'credits_text' => $this->contactMarkdown($credits, $fileIds),
            ],
        ]], 'fileIds' => array_values(array_unique($fileIds))];
    }

    /**
     * Returns editable link items from one TYPO3 `landing links` fragment.
     *
     * @param  string[]  $fileIds
     * @return list<array{label: string, url: string, icon: string}>
     */
    protected function contactLinks(?object $record, array &$fileIds): array
    {
        if (! $record || empty($record->bodytext)) {
            return [];
        }

        $result = $this->rewriteHtmlFiles((string) $record->bodytext);
        $fileIds = array_merge($fileIds, $result['fileIds']);
        $document = $this->htmlDocument($result['html']);

        if (! $document) {
            return [];
        }

        $xpath = new \DOMXPath($document);
        $nodes = $xpath->query(
            '//*[contains(concat(" ", normalize-space(@class), " "), " links ")]'
            .'//a[contains(concat(" ", normalize-space(@class), " "), " link ")]'
        );
        $items = [];

        foreach ($nodes ?: [] as $node) {
            $url = trim((string) $node->attributes?->getNamedItem('href')?->nodeValue);
            $label = $this->nodeText($node);
            $icon = '';
            $class = (string) $xpath->query('.//i', $node)?->item(0)?->attributes?->getNamedItem('class')?->nodeValue;

            if (preg_match('/(?:^|\s)fa-([a-z0-9-]+)(?:\s|$)/i', $class, $matches)) {
                $icon = strtolower($matches[1]);
            }

            if ($label !== '' && $url !== '') {
                $items[] = ['label' => $label, 'url' => $url, 'icon' => $icon];
            }
        }

        return $items;
    }

    /**
     * Converts contact-page rich text while keeping address line breaks.
     *
     * @param  string[]  $fileIds
     */
    protected function contactMarkdown(object $record, array &$fileIds): string
    {
        $result = $this->rewriteHtmlFiles((string) ($record->bodytext ?? ''));
        $fileIds = array_merge($fileIds, $result['fileIds']);
        $html = (string) preg_replace('/<br\s*\/?>/i', "  \n", $result['html']);

        if (preg_match('/<ul\b/i', $html)) {
            return $this->contactListMarkdown($html);
        }

        return $this->htmlToMarkdown($html);
    }

    /**
     * Converts nested contact-page credit lists without flattening child items.
     */
    protected function contactListMarkdown(string $html): string
    {
        $document = $this->htmlDocument($html);

        if (! $document) {
            return $this->htmlToMarkdown($html);
        }

        $xpath = new \DOMXPath($document);
        $list = $xpath->query('//*[@id="pagible-import"]/ul')?->item(0);

        if (! $list instanceof \DOMElement) {
            return $this->htmlToMarkdown($html);
        }

        return implode("\n", $this->contactListItems($list));
    }

    /**
     * @return list<string>
     */
    protected function contactListItems(\DOMElement $list, int $depth = 0): array
    {
        $lines = [];

        foreach ($list->childNodes as $item) {
            if (! $item instanceof \DOMElement || strtolower($item->tagName) !== 'li') {
                continue;
            }

            $inline = '';
            $nested = [];

            foreach ($item->childNodes as $child) {
                if ($child instanceof \DOMElement && strtolower($child->tagName) === 'ul') {
                    $nested[] = $child;
                } else {
                    $inline .= (string) $item->ownerDocument?->saveHTML($child);
                }
            }

            $text = $this->htmlToMarkdown($inline);

            if ($text !== '') {
                $lines[] = str_repeat('  ', $depth).'- '.$text;
            }

            foreach ($nested as $childList) {
                array_push($lines, ...$this->contactListItems($childList, $depth + 1));
            }
        }

        return $lines;
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

        $html = $this->normalizeLandingCardMarkup((string) $record->bodytext);
        $result = $this->rewriteHtmlFiles($html);

        return ['elements' => [[
            'id' => Utils::uid(),
            'type' => 'html',
            'group' => 'main',
            'data' => ['text' => Utils::html($result['html'])],
        ]], 'fileIds' => $result['fileIds']];
    }

    /**
     * Repairs the source roadmap legend before HTMLPurifier processes it.
     *
     * TYPO3 renders the two legend headings directly below `thead`. Browsers
     * implicitly add the missing row, but HTMLPurifier otherwise removes them.
     *
     * @return array{elements: array<int, array<string, mixed>>, fileIds: string[]}|null
     */
    protected function convertRoadmapHtml(object $record): ?array
    {
        $html = (string) ($record->bodytext ?? '');

        if (preg_match('/<table\b[^>]*\bclass=["\'][^"\']*\blegend\b/i', $html)) {
            $html = (string) preg_replace(
                '/(<thead\b[^>]*>)\s*((?:<th\b[^>]*>.*?<\/th>\s*)+)(<\/thead>)/is',
                '$1<tr>$2</tr>$3',
                $html
            );
        }

        $normalizedRecord = clone $record;
        $normalizedRecord->bodytext = $html;

        return $this->convertHtml($normalizedRecord);
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
     * Converts all file references of the Aimeos showcase gallery.
     *
     * @return array{elements: array<int, array<string, mixed>>, fileIds: string[]}|null
     */
    protected function convertShowcases(object $record): ?array
    {
        $refs = $this->fileRefs->get((int) ($record->uid ?? 0), Collection::make());
        $items = [];
        $fileIds = [];

        foreach ($refs as $ref) {
            if (! ($fileId = $this->importFileSafely(
                fn () => $this->importFileReference($ref)
            ))) {
                continue;
            }

            $url = $this->showcaseUrl((string) ($ref->link ?? ''));
            [$name, $text] = $this->showcaseCaption((string) ($ref->description ?? ''));
            $items[] = [
                'name' => $name,
                'text' => $text,
                'file' => ['id' => $fileId, 'type' => 'file'],
                'url' => $url,
            ];
            $fileIds[] = $fileId;
        }

        if (empty($items)) {
            return null;
        }

        return ['elements' => [[
            'id' => Utils::uid(),
            'type' => 'aimeos::showcases',
            'group' => 'main',
            'data' => ['items' => $items],
        ]], 'fileIds' => array_values(array_unique($fileIds))];
    }

    /**
     * Converts the column-based TYPO3 single case-study layout into one
     * structured and editable theme element.
     *
     * @param  Collection<int|string, mixed>  $records
     * @return array{elements: array<int, array<string, mixed>>, fileIds: string[]}|null
     */
    protected function convertCaseStudy(Collection $records): ?array
    {
        $stageRecord = $records->first(fn ($record) => $this->isCaseStudyStage($record));

        if (! $stageRecord || ! ($stage = $this->caseStudyStage($stageRecord))) {
            return null;
        }

        $sections = [];
        $fileIds = $stage['fileIds'];

        foreach ([[31, 30, 'start'], [32, 33, 'end'], [35, 34, 'start']] as [$textCol, $imageCol, $position]) {
            if ($section = $this->caseStudySection($records, $textCol, $imageCol, $position)) {
                $sections[] = $section['data'];
                $fileIds = array_merge($fileIds, $section['fileIds']);
            }
        }

        $screenshots = $this->caseStudyScreenshots($records);
        $implementer = $this->caseStudyImplementer($records);
        $back = $this->caseStudyBackLink($records);
        $fileIds = array_merge($fileIds, $screenshots['fileIds'], $implementer['fileIds']);

        return ['elements' => [[
            'id' => Utils::uid(),
            'type' => 'aimeos::case-study',
            'group' => 'main',
            'data' => [
                ...$stage['data'],
                'sections' => $sections,
                'gallery_title' => $screenshots['title'],
                'screenshots' => $screenshots['files'],
                ...$implementer['data'],
                ...$back,
            ],
        ]], 'fileIds' => array_values(array_unique($fileIds))];
    }

    /**
     * Extracts the title, tags, introduction and lead image from the stage.
     *
     * @return array{data: array<string, mixed>, fileIds: string[]}|null
     */
    protected function caseStudyStage(object $record): ?array
    {
        $document = $this->htmlDocument((string) ($record->bodytext ?? ''));

        if (! $document) {
            return null;
        }

        $xpath = new \DOMXPath($document);
        $stage = $xpath->query(
            '//*[contains(concat(" ", normalize-space(@class), " "), " stage ")]'
        )?->item(0);

        if (! $stage) {
            return null;
        }

        $title = $xpath->query('.//h1', $stage)?->item(0);
        $intro = $xpath->query(
            './/*[contains(concat(" ", normalize-space(@class), " "), " container ")]//p',
            $stage
        )?->item(0) ?? $xpath->query('.//p', $stage)?->item(0);
        $image = $xpath->query('.//img', $stage)?->item(0);
        $source = trim((string) ($image?->attributes?->getNamedItem('src')?->nodeValue
            ?: $image?->attributes?->getNamedItem('data-src')?->nodeValue));
        $file = null;
        $fileIds = [];

        if ($source !== '') {
            $file = $this->forContentRecord(
                $record,
                fn () => $this->importFileSafely(fn () => $this->importHtmlFile($source))
            );

            if ($file) {
                $fileIds[] = $file['id'];
            }
        }

        $tags = [];

        foreach ($xpath->query(
            './/*[contains(concat(" ", normalize-space(@class), " "), " stage-tags ")]//li',
            $stage
        ) ?: [] as $tag) {
            if ($label = $this->nodeText($tag)) {
                $tags[] = ['label' => $label];
            }
        }

        return ['data' => [
            'title' => $title ? $this->nodeText($title) : '',
            'tags' => $tags,
            'intro' => $intro ? $this->htmlToMarkdown($document->saveHTML($intro) ?: '') : '',
            'stage_file' => $file ? ['id' => $file['id'], 'type' => 'file'] : null,
        ], 'fileIds' => $fileIds];
    }

    /**
     * Builds one alternating text/image row from its TYPO3 layout columns.
     *
     * @param  Collection<int|string, mixed>  $records
     * @return array{data: array<string, mixed>, fileIds: string[]}|null
     */
    protected function caseStudySection(Collection $records, int $textCol, int $imageCol, string $position): ?array
    {
        $textRecord = $records->first(
            fn ($record) => (int) ($record->colPos ?? 0) === $textCol
                && in_array((string) ($record->CType ?? ''), ['text', 'textpic', 'textmedia'], true)
        );
        $imageRecords = $records->filter(
            fn ($record) => (int) ($record->colPos ?? 0) === $imageCol
                && (string) ($record->CType ?? '') === 'image'
        );
        $images = $this->caseStudyImages($imageRecords);
        $title = trim((string) ($textRecord->header ?? ''));
        $text = '';
        $fileIds = $images['fileIds'];

        if ($textRecord && trim((string) ($textRecord->bodytext ?? '')) !== '') {
            $result = $this->forContentRecord(
                $textRecord,
                fn () => $this->rewriteHtmlFiles((string) $textRecord->bodytext)
            );
            $text = $this->htmlToMarkdown($result['html']);
            $fileIds = array_merge($fileIds, $result['fileIds']);
        }

        if ($title === '' && $text === '' && empty($images['items'])) {
            return null;
        }

        return ['data' => [
            'title' => $title,
            'text' => $text,
            'files' => array_column($images['items'], 'file'),
            'url' => $images['items'][0]['url'] ?? '',
            'position' => $position,
        ], 'fileIds' => array_values(array_unique($fileIds))];
    }

    /**
     * Imports the screenshots belonging to a case-study carousel.
     *
     * @param  Collection<int|string, mixed>  $records
     * @return array{title: string, files: array<int, array{id: string, type: string}>, fileIds: string[]}
     */
    protected function caseStudyScreenshots(Collection $records): array
    {
        $record = $records->first(
            fn ($record) => (int) ($record->colPos ?? 0) === 36
                && (string) ($record->CType ?? '') === 'carousel'
        );
        $files = [];
        $fileIds = [];

        if (! $record) {
            return ['title' => '', 'files' => [], 'fileIds' => []];
        }

        foreach ($this->carouselItems?->get((int) ($record->uid ?? 0), Collection::make()) ?? [] as $item) {
            $ref = $this->carouselFileRefs?->get((int) ($item->uid ?? 0))?->first();

            if (! $ref || ! ($fileId = $this->forContentRecord(
                $record,
                fn () => $this->importFileSafely(fn () => $this->importFileReference($ref))
            ))) {
                continue;
            }

            $files[] = ['id' => $fileId, 'type' => 'file'];
            $fileIds[] = $fileId;
        }

        return [
            'title' => trim((string) ($record->header ?? '')),
            'files' => $files,
            'fileIds' => array_values(array_unique($fileIds)),
        ];
    }

    /**
     * Extracts the implementer logo, name, link and description.
     *
     * @param  Collection<int|string, mixed>  $records
     * @return array{data: array<string, mixed>, fileIds: string[]}
     */
    protected function caseStudyImplementer(Collection $records): array
    {
        $record = $records->first(
            fn ($record) => (int) ($record->colPos ?? 0) === 37
                && trim((string) ($record->header ?? '')) !== ''
        );

        if (! $record) {
            return ['data' => [], 'fileIds' => []];
        }

        $result = $this->forContentRecord(
            $record,
            fn () => $this->rewriteHtmlFiles((string) ($record->bodytext ?? ''))
        );
        $document = $this->htmlDocument($result['html']);
        $images = $this->caseStudyImages(Collection::make([$record]));
        $name = '';
        $url = $images['items'][0]['url'] ?? '';
        $text = '';

        if ($document) {
            $xpath = new \DOMXPath($document);
            $heading = $xpath->query('//h3')?->item(0);
            $link = $heading ? $xpath->query('.//a[@href]', $heading)?->item(0) : null;
            $name = $heading ? $this->nodeText($heading) : '';
            $href = trim((string) $link?->attributes?->getNamedItem('href')?->nodeValue);
            $url = $href !== '' ? ($this->rewriteTypo3Url($href) ?? $href) : $url;
            $paragraphs = '';

            foreach ($xpath->query('//p') ?: [] as $paragraph) {
                $paragraphs .= $document->saveHTML($paragraph) ?: '';
            }

            $text = $this->htmlToMarkdown($paragraphs);
        }

        return ['data' => [
            'implementer_title' => trim((string) ($record->header ?? '')),
            'implementer_name' => $name,
            'implementer_text' => $text,
            'implementer_file' => $images['items'][0]['file'] ?? null,
            'implementer_url' => $url,
        ], 'fileIds' => array_values(array_unique(array_merge($result['fileIds'], $images['fileIds'])))];
    }

    /**
     * Extracts the link back to the case-study overview.
     *
     * @param  Collection<int|string, mixed>  $records
     * @return array{back_label: string, back_url: string}
     */
    protected function caseStudyBackLink(Collection $records): array
    {
        $record = $records->first(
            fn ($record) => (int) ($record->colPos ?? 0) === 37
                && trim((string) ($record->header ?? '')) === ''
                && str_contains((string) ($record->bodytext ?? ''), 'href=')
        );
        $document = $record ? $this->htmlDocument((string) ($record->bodytext ?? '')) : null;
        $link = $document ? (new \DOMXPath($document))->query('//a[@href]')?->item(0) : null;
        $url = trim((string) $link?->attributes?->getNamedItem('href')?->nodeValue);

        return [
            'back_label' => $link ? $this->nodeText($link) : '',
            'back_url' => $this->rewriteTypo3Url($url) ?? $url,
        ];
    }

    /**
     * Imports all image references for a set of TYPO3 content records.
     *
     * @param  Collection<int|string, mixed>  $records
     * @return array{items: array<int, array{file: array{id: string, type: string}, url: string}>, fileIds: string[]}
     */
    protected function caseStudyImages(Collection $records): array
    {
        $items = [];
        $fileIds = [];

        foreach ($records->sortBy('sorting') as $record) {
            foreach ($this->fileRefs->get((int) ($record->uid ?? 0), Collection::make()) as $ref) {
                $fileId = $this->forContentRecord(
                    $record,
                    fn () => $this->importFileSafely(fn () => $this->importFileReference($ref))
                );

                if (! $fileId) {
                    continue;
                }

                $items[] = [
                    'file' => ['id' => $fileId, 'type' => 'file'],
                    'url' => $this->showcaseUrl((string) ($ref->link ?? '')),
                ];
                $fileIds[] = $fileId;
            }
        }

        return ['items' => $items, 'fileIds' => array_values(array_unique($fileIds))];
    }

    /**
     * Runs conversion work with warnings attributed to its source content UID.
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    protected function forContentRecord(object $record, callable $callback): mixed
    {
        $previous = $this->contentUid;
        $this->contentUid = (string) ($record->uid ?? '?');

        try {
            return $callback();
        } finally {
            $this->contentUid = $previous;
        }
    }

    /**
     * Splits a TYPO3 image description into the visible site name and segment.
     *
     * @return array{string, string}
     */
    protected function showcaseCaption(string $description): array
    {
        $caption = $this->htmlToMarkdown($description);
        $lines = preg_split('/\n+/', $caption) ?: [];
        $lines = array_values(array_filter(array_map('trim', $lines), fn ($line) => $line !== ''));
        $name = (string) array_shift($lines);

        return [$name, implode(' ', $lines)];
    }

    /**
     * Extracts the URL from a TYPO3 file-reference link value.
     */
    protected function showcaseUrl(string $link): string
    {
        $tokens = array_values(array_filter(
            str_getcsv(trim($link), ' ', '"', '\\'),
            fn ($value) => $value !== ''
        ));
        $target = (string) ($tokens[0] ?? '');

        return $this->resolveTypo3Target($target) ?? $target;
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
     * Detects the source-specific Aimeos landing page layout.
     *
     * @param  Collection<int|string, mixed>  $records
     */
    protected function isAimeosHomepage(Collection $records): bool
    {
        return $records->contains(
            fn ($record) => str_contains((string) ($record->bodytext ?? ''), 'landing welcome')
        );
    }

    /**
     * Detects the Aimeos B2B commerce landing page.
     *
     * @param  Collection<int|string, mixed>  $records
     */
    protected function isAimeosB2b(Collection $records): bool
    {
        $hasStage = $records->contains(function ($record): bool {
            $html = $this->linkedRecordHtml($record);

            return str_contains($html, 'landing welcome')
                && str_contains($html, 'b2b ecommerce');
        });
        $hasHighlights = $records->contains(function ($record): bool {
            $html = $this->linkedRecordHtml($record);

            return str_contains($html, 'landing highlights-image')
                && (str_contains($html, 'b2b_multi')
                    || str_contains($html, 'multi portal, multi channel'));
        });

        return $hasStage && $hasHighlights;
    }

    /**
     * Detects the Aimeos Laravel marketplace landing page.
     *
     * Its TYPO3 backend columns do not follow their frontend order. Detecting
     * it separately also keeps the branded quality logos instead of replacing
     * them with the generic homepage statistics element.
     *
     * @param  Collection<int|string, mixed>  $records
     */
    protected function isAimeosMarketplace(Collection $records): bool
    {
        $hasStage = $records->contains(function ($record): bool {
            $html = $this->linkedRecordHtml($record);

            return str_contains($html, 'landing welcome')
                && str_contains($html, 'laravel marketplace');
        });
        $hasHighlights = $records->contains(function ($record): bool {
            $html = $this->linkedRecordHtml($record);

            return str_contains($html, 'landing highlights-image')
                && str_contains($html, 'marketplace');
        });

        return $hasStage && $hasHighlights;
    }

    /**
     * Detects the Aimeos Laravel multi-vendor SaaS landing page.
     *
     * @param  Collection<int|string, mixed>  $records
     */
    protected function isAimeosSaas(Collection $records): bool
    {
        $hasStage = $records->contains(function ($record): bool {
            $html = $this->linkedRecordHtml($record);

            return str_contains($html, 'landing welcome')
                && str_contains($html, 'laravel ecommerce saas');
        });
        $hasHighlights = $records->contains(function ($record): bool {
            $html = $this->linkedRecordHtml($record);

            return str_contains($html, 'landing highlights-image')
                && str_contains($html, 'saas');
        });

        return $hasStage && $hasHighlights;
    }

    /**
     * Detects the Aimeos TYPO3 shop-extension landing page.
     *
     * The TYPO3 page uses the same `landing welcome` marker as the homepage,
     * but its branded trust cards and direct hero screen must stay intact.
     *
     * @param  Collection<int|string, mixed>  $records
     */
    protected function isAimeosTypo3(Collection $records): bool
    {
        return $records->contains(function ($record): bool {
            $html = $this->linkedRecordHtml($record);

            return str_contains($html, 'landing welcome')
                && str_contains($html, 'typo3 shop')
                && (str_contains($html, 'screen-typo3') || str_contains($html, 'typo3.png'));
        });
    }

    /**
     * Detects the column-based Aimeos Laravel platform landing page.
     *
     * @param  Collection<int|string, mixed>  $records
     */
    protected function isAimeosLaravelPlatform(Collection $records): bool
    {
        $hasLaravelStage = $records->contains(function ($record): bool {
            $html = strtolower((string) ($record->bodytext ?? ''));

            return str_contains($html, 'landing welcome')
                && (str_contains($html, 'laravel-gold')
                    || str_contains($html, 'aimeos-screen-laravel-ecommerce'));
        });
        $hasDistributions = $records->contains(
            fn ($record) => str_contains(
                strtolower((string) ($record->bodytext ?? '')),
                'landing dists'
            )
        );

        return $hasLaravelStage && $hasDistributions;
    }

    /**
     * Detects the Aimeos feature overview page.
     *
     * @param  Collection<int|string, mixed>  $records
     */
    protected function isAimeosFeatures(Collection $records): bool
    {
        $hasTitle = $records->contains(
            fn ($record) => trim((string) ($record->header ?? '')) === 'Aimeos Features'
        );
        $accordions = $records->filter(
            fn ($record) => (string) ($record->CType ?? '') === 'accordion'
        )->count();

        return $hasTitle && $accordions >= 3;
    }

    /**
     * Detects the Aimeos showcase gallery page.
     *
     * @param  Collection<int|string, mixed>  $records
     */
    protected function isAimeosShowcases(Collection $records): bool
    {
        if ($this->isAimeosHomepage($records)) {
            return false;
        }

        $hasTitle = $records->contains(
            fn ($record) => trim((string) ($record->header ?? '')) === 'Aimeos Showcases'
        );
        $hasGallery = $records->contains(
            fn ($record) => (string) ($record->CType ?? '') === 'image'
        );

        return $hasTitle && $hasGallery;
    }

    /**
     * Detects the Aimeos case-study overview page.
     *
     * @param  Collection<int|string, mixed>  $records
     */
    protected function isAimeosCaseStudies(Collection $records): bool
    {
        return $records->contains(
            fn ($record) => (string) ($record->CType ?? '') === 'header'
                && trim((string) ($record->header ?? '')) === 'Aimeos Case Studies'
        );
    }

    /**
     * Detects an Aimeos single case-study page.
     *
     * @param  Collection<int|string, mixed>  $records
     */
    protected function isAimeosCaseStudy(Collection $records): bool
    {
        $hasStage = $records->contains(fn ($record) => $this->isCaseStudyStage($record));
        $hasAbout = $records->contains(
            fn ($record) => (int) ($record->colPos ?? 0) === 31
                && trim((string) ($record->header ?? '')) === 'About'
        );

        return $hasStage && $hasAbout;
    }

    /**
     * Detects the Aimeos release roadmap and support-period chart.
     *
     * @param  Collection<int|string, mixed>  $records
     */
    protected function isAimeosRoadmap(Collection $records): bool
    {
        $hasTitle = $records->contains(
            fn ($record) => trim((string) ($record->header ?? '')) === 'Aimeos Roadmap'
        );
        $hasChart = $records->contains(
            fn ($record) => preg_match(
                '/<table\b[^>]*\bclass=["\'][^"\']*\broadmap\b/i',
                (string) ($record->bodytext ?? '')
            ) === 1
        );

        return $hasTitle && $hasChart;
    }

    /**
     * Detects the Aimeos contact, support and legal-information page.
     *
     * @param  Collection<int|string, mixed>  $records
     */
    protected function isAimeosContact(Collection $records): bool
    {
        $headers = $records
            ->map(fn ($record) => trim((string) ($record->header ?? '')))
            ->filter()
            ->all();
        $hasForm = $records->contains(
            fn ($record) => (string) ($record->CType ?? '') === 'form_formframework'
        );
        $linkGroups = $records->filter(
            fn ($record) => preg_match(
                '/class=["\'][^"\']*\blanding\b[^"\']*\blinks\b/i',
                (string) ($record->bodytext ?? '')
            ) === 1
        )->count();

        return $hasForm
            && $linkGroups >= 2
            && count(array_intersect(
                ['Stay informed', 'Help & Support', 'Contact Us', 'Imprint', 'Privacy policy', 'Credits'],
                $headers
            )) === 6;
    }

    /**
     * Detects the first record used to create the structured contact element.
     */
    protected function isContactStage(object $record): bool
    {
        return trim((string) ($record->header ?? '')) === 'Stay informed';
    }

    /**
     * Detects source records consumed by the structured contact element.
     */
    protected function isContactLayoutRecord(object $record): bool
    {
        return ! in_array((int) ($record->colPos ?? 0), [10, 11, 12], true);
    }

    /**
     * Detects the Aimeos partner directory.
     *
     * @param  Collection<int|string, mixed>  $records
     */
    protected function isAimeosPartners(Collection $records): bool
    {
        $hasTitle = $records->contains(
            fn ($record) => trim((string) ($record->header ?? '')) === 'Recommended Partners'
        );
        $hasDirectory = $records->contains(
            fn ($record) => preg_match(
                '/class=["\'][^"\']*\bpartner\b/i',
                (string) ($record->bodytext ?? '')
            ) === 1
        );

        return $hasTitle && $hasDirectory;
    }

    /**
     * Detects the stage record of an Aimeos single case study.
     */
    protected function isCaseStudyStage(object $record): bool
    {
        return (int) ($record->colPos ?? 0) === 3
            && (string) ($record->CType ?? '') === 'html'
            && preg_match('/class=["\'][^"\']*\bstage\b/i', (string) ($record->bodytext ?? '')) === 1;
    }

    /**
     * Detects records consumed by the structured single case-study element.
     */
    protected function isCaseStudyLayoutRecord(object $record): bool
    {
        $colPos = (int) ($record->colPos ?? 0);

        return $this->isCaseStudyStage($record) || ($colPos >= 30 && $colPos <= 37);
    }

    /**
     * Detects a decorative Features heading superseded by feature-list data.
     */
    protected function isFeaturesHead(object $record): bool
    {
        return (string) ($record->CType ?? '') === 'html'
            && preg_match('/class=["\'][^"\']*\bhead\b/i', (string) ($record->bodytext ?? '')) === 1;
    }

    /**
     * Restores the frontend order of the column-based TYPO3 Features layout.
     *
     * @param  Collection<int|string, mixed>  $records
     * @return Collection<int|string, mixed>
     */
    protected function sortFeaturesRecords(Collection $records): Collection
    {
        return $records->sortBy(function ($record): int {
            $type = (string) ($record->CType ?? '');
            $colPos = (int) ($record->colPos ?? 0);

            if ($type === 'header' && trim((string) ($record->header ?? '')) === 'Aimeos Features') {
                return 1000000;
            }

            if ($type === 'textpic' && $colPos === 0) {
                return 2000000 + (int) ($record->sorting ?? 0);
            }

            if (in_array($type, ['html', 'accordion'], true) && $colPos >= 20 && $colPos <= 28) {
                return (100 + $colPos) * 100000 + ($type === 'html' ? 0 : 1);
            }

            if ($type === 'shortcut' && $colPos === 29) {
                return 13000000;
            }

            if (in_array($colPos, [10, 11, 12], true)) {
                return 20000000 + $this->homepageWeight($record) * 1000;
            }

            return 14000000 + (int) ($record->sorting ?? 0);
        })->values();
    }

    /**
     * Restores the title, gallery, partner block and footer order of the showcase page.
     *
     * @param  Collection<int|string, mixed>  $records
     * @return Collection<int|string, mixed>
     */
    protected function sortShowcaseRecords(Collection $records): Collection
    {
        return $records->sortBy(function ($record): int {
            $type = (string) ($record->CType ?? '');
            $html = strtolower((string) ($record->bodytext ?? ''));
            $colPos = (int) ($record->colPos ?? 0);

            if (trim((string) ($record->header ?? '')) === 'Aimeos Showcases') {
                return 1000000;
            }

            if ($type === 'image') {
                return 2000000;
            }

            if (str_contains($html, 'class="partnering')) {
                return 3000000;
            }

            if (in_array($colPos, [10, 11, 12], true)) {
                return 10000000 + $this->homepageWeight($record) * 1000;
            }

            return 4000000 + (int) ($record->sorting ?? 0);
        })->values();
    }

    /**
     * Restores the frontend order of the column-based TYPO3 case-study layout.
     *
     * @param  Collection<int|string, mixed>  $records
     * @return Collection<int|string, mixed>
     */
    protected function sortCaseStudyOverviewRecords(Collection $records): Collection
    {
        return $records->sortBy(function ($record): int {
            $type = (string) ($record->CType ?? '');
            $colPos = (int) ($record->colPos ?? 0);

            if ($type === 'header' && trim((string) ($record->header ?? '')) === 'Aimeos Case Studies') {
                return 1000000;
            }

            if ($colPos >= 20 && $colPos <= 27) {
                return 2000000 + ($colPos - 20) * 100000 + (int) ($record->sorting ?? 0);
            }

            return 10000000 + (int) ($record->sorting ?? 0);
        })->values();
    }

    /**
     * Restores the stage-to-implementer order of a TYPO3 single case study.
     *
     * @param  Collection<int|string, mixed>  $records
     * @return Collection<int|string, mixed>
     */
    protected function sortSingleCaseStudyRecords(Collection $records): Collection
    {
        return $records->sortBy(function ($record): int {
            $colPos = (int) ($record->colPos ?? 0);

            if ($this->isCaseStudyStage($record)) {
                return 1000000;
            }

            if ($colPos >= 30 && $colPos <= 37) {
                return 2000000 + ($colPos - 30) * 100000 + (int) ($record->sorting ?? 0);
            }

            if (in_array($colPos, [10, 11, 12], true)) {
                return 20000000 + $this->homepageWeight($record) * 1000;
            }

            return 10000000 + (int) ($record->sorting ?? 0);
        })->values();
    }

    /**
     * Maps Aimeos feature section titles to theme icon names.
     */
    protected function featureIcon(string $title): string
    {
        return match (strtolower(trim($title))) {
            'advantages' => 'check',
            'for developers' => 'code',
            'catalog' => 'list',
            'products' => 'cube',
            'basket' => 'basket',
            'checkout' => 'payment',
            'customer related' => 'customer',
            'shop administration' => 'settings',
            'asynchronous tasks' => 'tasks',
            default => 'check',
        };
    }

    /**
     * Restores the visual column order of the Aimeos TYPO3 homepage.
     *
     * @param  Collection<int|string, mixed>  $records
     * @return Collection<int|string, mixed>
     */
    protected function sortHomepageRecords(Collection $records): Collection
    {
        return $records->sortBy(function ($record): int {
            return $this->homepageWeight($record) * 100000 + (int) ($record->sorting ?? 0);
        })->values();
    }

    /**
     * Restores the B2B page order rendered by the TYPO3 frontend.
     *
     * The hero and both call-to-action sections consist of independent or
     * shared TYPO3 records whose backend order differs from their page order.
     *
     * @param  Collection<int|string, mixed>  $records
     * @return Collection<int|string, mixed>
     */
    protected function sortB2bRecords(Collection $records): Collection
    {
        return $records->sortBy(function ($record): int {
            $html = $this->linkedRecordHtml($record);
            $sorting = (int) ($record->sorting ?? 0);
            $weight = match (true) {
                str_contains($html, 'landing welcome') => 10,
                str_contains($html, 'b2b.png') && str_contains($html, 'class="logo') => 20,
                str_contains($html, 'build complex b2b ecommerce platforms') => 30,
                str_contains($html, 'landing qualities') => 40,
                str_contains($html, 'landing highlights-image') => 50,
                str_contains($html, 'landing showcases') => 60,
                str_contains($html, 'want to know more') => 70,
                str_contains($html, 'landing casestudies') => 80,
                str_contains($html, 'get free consulting now') => 90,
                str_contains($html, 'class="landing') && str_contains($html, '>contact<') => 100,
                in_array((int) ($record->colPos ?? 0), [10, 11, 12], true) => $this->homepageWeight($record),
                default => 110,
            };

            return $weight * 100000 + $sorting;
        })->values();
    }

    /**
     * Restores the marketplace page order rendered by the TYPO3 frontend.
     *
     * Several call-to-action blocks are shortcuts to shared content records,
     * so their target HTML must participate in the semantic sort as well.
     *
     * @param  Collection<int|string, mixed>  $records
     * @return Collection<int|string, mixed>
     */
    protected function sortMarketplaceRecords(Collection $records): Collection
    {
        return $records->sortBy(function ($record): int {
            $html = $this->linkedRecordHtml($record);
            $type = (string) ($record->CType ?? '');
            $sorting = (int) ($record->sorting ?? 0);
            $weight = match (true) {
                str_contains($html, 'landing welcome') => 10,
                str_contains($html, 'marketplace.png') && str_contains($html, 'class="logo') => 20,
                str_contains($html, 'create your own feature rich') => 30,
                str_contains($html, 'landing qualities') => 40,
                str_contains($html, 'landing explain') => 50,
                str_contains($html, 'landing highlights-image') => 60,
                str_contains($html, 'landing showcases') => 70,
                str_contains($html, 'want to see a demo') => 80,
                str_contains($html, 'landing casestudies') => 90,
                str_contains($html, 'landing faq') => 100,
                $type === 'accordion' => 110,
                str_contains($html, 'want to know more') => 120,
                in_array((int) ($record->colPos ?? 0), [10, 11, 12], true) => $this->homepageWeight($record),
                default => 130,
            };

            return $weight * 100000 + $sorting;
        })->values();
    }

    /**
     * Returns the body HTML of a record or the records referenced by a shortcut.
     */
    protected function linkedRecordHtml(object $record): string
    {
        $html = (string) ($record->header ?? '').' '.(string) ($record->bodytext ?? '');

        if ((string) ($record->CType ?? '') === 'shortcut') {
            preg_match_all('/\d+/', (string) ($record->records ?? ''), $matches);

            foreach (array_unique(array_map('intval', $matches[0] ?? [])) as $uid) {
                $target = $this->contentRecords?->get($uid);

                if ($target && (int) ($target->uid ?? 0) !== (int) ($record->uid ?? 0)) {
                    $html .= ' '.(string) ($target->header ?? '').' '.(string) ($target->bodytext ?? '');
                }
            }
        }

        return strtolower($html);
    }

    /**
     * Converts product landing-page records and follows shared TYPO3 content
     * shortcuts without falling back to the generic homepage statistics.
     *
     * @return array{elements: array<int, array<string, mixed>>, fileIds?: string[]}|null
     */
    protected function convertProductLandingContentElement(object $record, string $page): ?array
    {
        if ((string) ($record->CType ?? '') === 'shortcut') {
            preg_match_all('/\d+/', (string) ($record->records ?? ''), $matches);
            $elements = [];
            $fileIds = [];

            foreach (array_unique(array_map('intval', $matches[0] ?? [])) as $uid) {
                $target = $this->contentRecords?->get($uid);

                if (! $target || (int) ($target->uid ?? 0) === (int) ($record->uid ?? 0)) {
                    continue;
                }

                if ($result = $this->convertProductLandingContentElement($target, $page)) {
                    $elements = array_merge($elements, $result['elements']);
                    $fileIds = array_merge($fileIds, $result['fileIds'] ?? []);
                }
            }

            return $elements === [] ? null : [
                'elements' => $elements,
                'fileIds' => array_values(array_unique($fileIds)),
            ];
        }

        if ($page === 'marketplace' && (string) ($record->CType ?? '') === 'accordion') {
            $result = $this->convertAccordion($record);

            if ($result) {
                $result['elements'][0]['data']['title'] = '';
            }

            return $result;
        }

        if ($page === 'b2b'
            && (string) ($record->CType ?? '') === 'header'
            && str_contains(strtolower((string) ($record->header ?? '')), 'get free consulting now')) {
            $title = htmlspecialchars((string) $record->header, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

            return ['elements' => [[
                'id' => Utils::uid(),
                'type' => 'html',
                'group' => 'main',
                'data' => ['text' => Utils::html('<div class="landing b2b-consulting-heading"><h2>'.$title.'</h2></div>')],
            ]], 'fileIds' => []];
        }

        if ($page === 'b2b' && in_array((string) ($record->CType ?? ''), ['html', 'text'], true)) {
            return $this->convertB2bHtml($record);
        }

        if ($page === 'saas' && in_array((string) ($record->CType ?? ''), ['html', 'text'], true)) {
            return $this->convertSaasHtml($record);
        }

        if ($page === 'typo3' && (string) ($record->CType ?? '') === 'html') {
            if (str_contains(strtolower((string) ($record->bodytext ?? '')), 'landing users')) {
                return $this->convertContentElement($record, false);
            }

            return $this->convertTypo3Html($record);
        }

        if ((string) ($record->CType ?? '') !== 'html') {
            return $this->convertContentElement($record);
        }

        return $this->convertMarketplaceHtml($record);
    }

    /**
     * Adds a stable page hook while retaining the TYPO3-specific trust cards.
     *
     * @return array{elements: array<int, array<string, mixed>>, fileIds: string[]}|null
     */
    protected function convertTypo3Html(object $record): ?array
    {
        $html = (string) ($record->bodytext ?? '');

        if ($html === '') {
            return null;
        }

        $lower = strtolower($html);

        if (str_contains($lower, 'landing welcome') && ! str_contains($lower, 'typo3-welcome')) {
            $html = (string) preg_replace('/\blanding\s+welcome\b/i', 'landing welcome typo3-welcome', $html, 1);
        }

        $html = $this->normalizeLandingCardMarkup($html);
        $result = $this->rewriteHtmlFiles($html);

        return ['elements' => [[
            'id' => Utils::uid(),
            'type' => 'html',
            'group' => 'main',
            'data' => ['text' => Utils::html($result['html'])],
        ]], 'fileIds' => $result['fileIds']];
    }

    /**
     * Adds stable semantic hooks to the source SaaS landing-page HTML.
     *
     * @return array{elements: array<int, array<string, mixed>>, fileIds: string[]}|null
     */
    protected function convertSaasHtml(object $record): ?array
    {
        $html = (string) ($record->bodytext ?? '');

        if ($html === '') {
            return null;
        }

        $lower = strtolower($html);

        if (str_contains($lower, 'landing welcome') && ! str_contains($lower, 'saas-welcome')) {
            $html = (string) preg_replace('/\blanding\s+welcome\b/i', 'landing welcome saas-welcome', $html, 1);
        } elseif (str_contains($lower, 'marketplace.png') && str_contains($lower, 'class="logo')) {
            $html = '<div class="landing saas-logo">'.$html.'</div>';
        } elseif (str_contains($lower, 'create your own feature rich') && str_contains($lower, 'saas')) {
            $html = '<div class="landing saas-intro">'.$html.'</div>';
        } elseif (str_contains($lower, 'want to start')) {
            $html = '<div class="landing saas-start">'.$html.'</div>';
        }

        $html = $this->normalizeLandingCardMarkup($html);
        $result = $this->rewriteHtmlFiles($html);

        return ['elements' => [[
            'id' => Utils::uid(),
            'type' => 'html',
            'group' => 'main',
            'data' => ['text' => Utils::html($result['html'])],
        ]], 'fileIds' => $result['fileIds']];
    }

    /**
     * Adds stable semantic hooks to the source B2B landing-page HTML.
     *
     * @return array{elements: array<int, array<string, mixed>>, fileIds: string[]}|null
     */
    protected function convertB2bHtml(object $record): ?array
    {
        $html = (string) ($record->bodytext ?? '');
        $header = trim((string) ($record->header ?? ''));

        if ($html === '') {
            return null;
        }

        $lower = strtolower($html);
        $headerLower = strtolower($header);

        if (str_contains($headerLower, 'get free consulting now')) {
            $title = htmlspecialchars($header, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $html = '<div class="landing b2b-consulting"><h2>'.$title.'</h2>'.$html.'</div>';
        } elseif (str_contains($lower, 'landing welcome') && ! str_contains($lower, 'b2b-welcome')) {
            $html = (string) preg_replace('/\blanding\s+welcome\b/i', 'landing welcome b2b-welcome', $html, 1);
        } elseif (str_contains($lower, 'b2b.png') && str_contains($lower, 'class="logo')) {
            $html = '<div class="landing b2b-logo">'.$html.'</div>';
        } elseif (str_contains($lower, 'build complex b2b ecommerce platforms')) {
            $html = '<div class="landing b2b-intro">'.$html.'</div>';
        } elseif (str_contains($lower, 'want to know more')) {
            $html = '<div class="landing b2b-more">'.$html.'</div>';
        } elseif (str_contains($lower, 'class="landing') && str_contains($lower, '>contact<')) {
            $html = '<div class="landing b2b-consulting-action">'.$html.'</div>';
        }

        $html = $this->normalizeLandingCardMarkup($html);
        $result = $this->rewriteHtmlFiles($html);
        $html = $result['html'];

        if (str_contains($html, 'b2b-more') && ! str_contains($html, '<a ')) {
            $html = (string) preg_replace(
                '/\bGet in touch\b/i',
                '<a href="https://aimeos.com/aimeos-gmbh/contact" class="btn">Get in touch</a>',
                $html,
                1
            );
        }

        if (str_contains($html, 'b2b-consulting-action') || str_contains($html, 'b2b-consulting')) {
            $html = (string) preg_replace(
                '/href=(["\'])\/(?:aimeos-gmbh\/)?contact\1/i',
                'href=$1https://aimeos.com/aimeos-gmbh/contact$1',
                $html
            );

            if (! str_contains($html, '<a ')) {
                $html = (string) preg_replace(
                    '/(?<![-\w])Contact(?![-\w])/i',
                    '<a href="https://aimeos.com/aimeos-gmbh/contact" class="btn">Contact</a>',
                    $html,
                    1
                );
            }
        }

        return ['elements' => [[
            'id' => Utils::uid(),
            'type' => 'html',
            'group' => 'main',
            'data' => ['text' => Utils::html($html)],
        ]], 'fileIds' => $result['fileIds']];
    }

    /**
     * Adds stable semantic hooks to the source marketplace HTML.
     *
     * @return array{elements: array<int, array<string, mixed>>, fileIds: string[]}|null
     */
    protected function convertMarketplaceHtml(object $record): ?array
    {
        $html = (string) ($record->bodytext ?? '');

        if ($html === '') {
            return null;
        }

        $lower = strtolower($html);

        if (str_contains($lower, 'landing welcome') && ! str_contains($lower, 'marketplace-welcome')) {
            $html = (string) preg_replace('/\blanding\s+welcome\b/i', 'landing welcome marketplace-welcome', $html, 1);
        } elseif (str_contains($lower, 'marketplace.png') && str_contains($lower, 'class="logo')) {
            $html = '<div class="landing marketplace-logo">'.$html.'</div>';
        } elseif (str_contains($lower, 'create your own feature rich')) {
            $html = '<div class="landing marketplace-intro">'.$html.'</div>';
        } elseif (str_contains($lower, 'want to see a demo')) {
            $html = '<div class="landing marketplace-demo">'.$html.'</div>';
        } elseif (str_contains($lower, 'want to know more')) {
            $html = '<div class="landing marketplace-contact">'.$html.'</div>';
        }

        $html = $this->normalizeLandingCardMarkup($html);
        $result = $this->rewriteHtmlFiles($html);
        $html = $result['html'];

        if (str_contains($html, 'marketplace-demo') && ! str_contains($html, '<a ')) {
            $html = (string) preg_replace(
                '/\bRequest access\b/i',
                '<a href="https://aimeos.com/aimeos-gmbh/contact" class="btn">Request access</a>',
                $html,
                1
            );
        }

        if (str_contains($html, 'marketplace-contact') && ! str_contains($html, '<a ')) {
            $html = (string) preg_replace(
                '/(?<![-\w])Contact(?![-\w])/i',
                '<a href="https://aimeos.com/aimeos-gmbh/contact" class="btn">Contact</a>',
                $html,
                1
            );
            $html = (string) preg_replace(
                '/(?<![-\w])Pricing(?![-\w])/i',
                '<a href="https://aimeos.com/extensions#c436" class="btn">Pricing</a>',
                $html,
                1
            );
        }

        return ['elements' => [[
            'id' => Utils::uid(),
            'type' => 'html',
            'group' => 'main',
            'data' => ['text' => Utils::html($html)],
        ]], 'fileIds' => $result['fileIds']];
    }

    /**
     * Restores the Laravel platform page's TYPO3 backend-column sequence.
     *
     * The live page renders column 20 as one long feature section and then
     * columns 21-27 in ascending order. Their local sorting values are only
     * meaningful inside each column.
     *
     * @param  Collection<int|string, mixed>  $records
     * @return Collection<int|string, mixed>
     */
    protected function sortLaravelPlatformRecords(Collection $records): Collection
    {
        return $records->sortBy(function ($record): int {
            $html = strtolower((string) ($record->bodytext ?? ''));
            $colPos = (int) ($record->colPos ?? 0);
            $sorting = (int) ($record->sorting ?? 0);

            if (str_contains($html, 'landing welcome')) {
                return 1000000 + $sorting;
            }

            if ($colPos >= 20 && $colPos <= 27) {
                return 2000000 + ($colPos - 20) * 1000000 + $sorting;
            }

            if (in_array($colPos, [10, 11, 12], true)) {
                return 20000000 + $this->homepageWeight($record) * 1000 + $sorting;
            }

            return 19000000 + $sorting;
        })->values();
    }

    /**
     * Keeps linked quality and distribution cards intact during purification.
     *
     * @return array{elements: array<int, array<string, mixed>>, fileIds: string[]}|null
     */
    protected function convertLaravelPlatformHtml(object $record): ?array
    {
        $html = (string) ($record->bodytext ?? '');

        $normalized = $this->normalizeLandingCardMarkup($html);

        if ($normalized === $html) {
            return $this->convertHtml($record);
        }

        $normalizedRecord = clone $record;
        $normalizedRecord->bodytext = $normalized;

        return $this->convertHtml($normalizedRecord);
    }

    /**
     * Replaces block headings and containers inside linked TYPO3 cards with spans.
     *
     * HTML Purifier uses an HTML4 content model and otherwise closes the link
     * before block-level headings, stars and button nodes. Keeping only inline
     * children preserves one accessible link and the source layout.
     */
    protected function normalizeLandingCardMarkup(string $html): string
    {
        $lower = strtolower($html);

        if (! str_contains($lower, 'landing qualities')
            && ! str_contains($lower, 'landing dists')
            && ! str_contains($lower, 'welcome-box')
        ) {
            return $html;
        }

        $document = $this->htmlDocument($html);

        if (! $document) {
            return $html;
        }

        $xpath = new \DOMXPath($document);
        $anchors = $xpath->query(
            '//*[contains(concat(" ", normalize-space(@class), " "), " landing ")'
            .' and (contains(concat(" ", normalize-space(@class), " "), " qualities ")'
            .' or contains(concat(" ", normalize-space(@class), " "), " dists "))]'
            .'//*[contains(concat(" ", normalize-space(@class), " "), " quality ")'
            .' or contains(concat(" ", normalize-space(@class), " "), " dist ")]/a'
            .' | //*[contains(concat(" ", normalize-space(@class), " "), " welcome-box ")]'
            .'/a[contains(concat(" ", normalize-space(@class), " "), " box-detail ")]'
        );
        $changed = false;

        foreach ($anchors ?: [] as $anchor) {
            foreach (iterator_to_array($anchor->childNodes) as $child) {
                if (! $child instanceof \DOMElement) {
                    continue;
                }

                $tag = strtolower($child->tagName);
                $classes = preg_split('/\s+/', trim($child->getAttribute('class'))) ?: [];
                $replacementClass = in_array($tag, ['h2', 'h3'], true) ? 'header' : '';

                if ($tag !== 'div' && $replacementClass === '') {
                    continue;
                }

                if ($replacementClass === '' && ! array_intersect($classes, ['header', 'stars', 'button'])) {
                    continue;
                }

                $span = $document->createElement('span');

                foreach (iterator_to_array($child->attributes) as $attribute) {
                    $span->setAttribute($attribute->nodeName, $attribute->nodeValue);
                }

                if ($replacementClass !== '' && ! in_array($replacementClass, $classes, true)) {
                    $span->setAttribute('class', trim($child->getAttribute('class').' '.$replacementClass));
                }

                while ($child->firstChild) {
                    $span->appendChild($child->firstChild);
                }

                $anchor->replaceChild($span, $child);
                $changed = true;
            }
        }

        if (! $changed) {
            return $html;
        }

        $root = $document->getElementById('pagible-import');
        $normalized = '';

        foreach ($root?->childNodes ?? [] as $child) {
            $normalized .= $document->saveHTML($child) ?: '';
        }

        return $normalized !== '' ? $normalized : $html;
    }

    /**
     * Returns the semantic order for one Aimeos homepage record.
     */
    protected function homepageWeight(object $record): int
    {
        $html = strtolower((string) ($record->bodytext ?? ''));
        $header = strtolower(trim((string) ($record->header ?? '')));
        $colPos = (int) ($record->colPos ?? 0);

        if (str_contains($html, 'landing welcome')) {
            return 10;
        }
        if (str_contains($html, 'landing users')) {
            return 20;
        }
        if (str_contains($html, 'landing qualities')) {
            return 30;
        }
        if (str_contains($html, 'landing highlights') && ! str_contains($html, 'class="highlight"')) {
            return 40;
        }
        if (str_contains($html, 'landing integrations')) {
            return 50;
        }
        if (str_contains($html, 'landing explain')) {
            return 60;
        }
        if (str_contains($html, 'landing highlights')) {
            return 70;
        }
        if (str_contains($html, 'landing showcases')) {
            return 80;
        }
        if (str_contains($html, 'landing demo')) {
            return 90;
        }
        if (str_contains($html, 'landing casestudies')) {
            return 100;
        }
        if (str_contains($html, 'class="partnering')) {
            return 110;
        }
        if (str_contains($html, 'landing support')) {
            return 120;
        }
        if (str_contains($html, 'landing links')) {
            return 130;
        }

        if ($colPos === 10) {
            return match ($header) {
                'available for' => 200,
                'developers' => 210,
                'softwareworld' => 220,
                default => 225,
            };
        }

        if ($colPos === 11) {
            return match ($header) {
                'extensions' => 230,
                'partners' => 240,
                'capterra rating' => 250,
                default => 255,
            };
        }

        if ($colPos === 12) {
            return 260;
        }

        return 140;
    }

    /**
     * Selects the explicit theme or recognizes the bundled Aimeos landing page.
     *
     * @param  Collection<int|string, mixed>  $records
     */
    protected function themeForRecords(Collection $records): string
    {
        if ($this->theme !== '') {
            return $this->theme;
        }

        return ($this->isAimeosHomepage($records)
            || $this->isAimeosB2b($records)
            || $this->isAimeosMarketplace($records)
            || $this->isAimeosSaas($records)
            || $this->isAimeosTypo3($records)
            || $this->isAimeosFeatures($records)
            || $this->isAimeosShowcases($records)
            || $this->isAimeosCaseStudies($records)
            || $this->isAimeosCaseStudy($records)
            || $this->isAimeosRoadmap($records)
            || $this->isAimeosPartners($records))
            && \Aimeos\Cms\Schema::get('aimeos')
            ? 'aimeos'
            : '';
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
        $fragment = (string) ($query['fragment'] ?? $matches[2] ?? '');

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

        $url = in_array((int) $page->doktype, [3, 4], true) // @phpstan-ignore property.notFound
            ? $this->redirectDestination($page, $this->t3Pages, [])
            : '/'.$this->slugFromPath($page->slug); // @phpstan-ignore property.notFound

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
     * @return Collection<int|string, mixed>
     */
    protected function fetchPages(): Collection
    {
        /** @var Collection<int|string, mixed> */
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
     * @return Collection<int|string, mixed>
     */
    protected function fetchSysFiles(): Collection
    {
        return DB::connection($this->t3Connection)
            ->table('sys_file')
            ->get()
            ->keyBy('uid');
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
        $pageData['theme'] = $this->themeForRecords($records);
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
        return Page::where('tag', 'root')->where('domain', $domain)->first();
    }

    /**
     * Finds an imported non-root page by its unique destination route.
     */
    protected function findPage(string $domain, string $path): ?Page
    {
        return Page::where('domain', $domain)->where('path', $path)->first();
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
     * Returns page records and inherited site fragments required by source layouts.
     *
     * @param  Collection<int|string, mixed>  $contentElements
     * @return Collection<int|string, mixed>
     */
    protected function recordsForPage(object $t3Page, Collection $contentElements): Collection
    {
        $pageUid = (int) ($t3Page->uid ?? 0);
        $contentPageUid = $this->contentSourcePageUid($t3Page);
        $records = $this->sortRecordsBySourceLayout(
            $contentElements->get($contentPageUid, Collection::make()),
            $t3Page
        );

        if ($contentPageUid !== $pageUid) {
            $records = $this->markSharedRecords($records, 'reference', $contentPageUid);
        }

        if ($records->contains(
            fn ($record) => in_array((int) ($record->colPos ?? 0), [10, 11, 12], true)
        )) {
            return $this->markSharedFooterRecords($records, $contentPageUid);
        }

        $root = $t3Page;
        $seen = [];

        while (empty($root->is_siteroot) && (int) ($root->pid ?? 0) > 0) {
            $uid = (int) ($root->uid ?? 0);

            if (isset($seen[$uid]) || ! ($parent = $this->t3Pages?->get((int) $root->pid))) {
                break;
            }

            $seen[$uid] = true;
            $root = $parent;
        }

        $rootUid = (int) ($root->uid ?? 0);
        $footer = $this->markSharedFooterRecords(
            $contentElements->get($rootUid, Collection::make())
                ->filter(fn ($record) => in_array((int) ($record->colPos ?? 0), [10, 11, 12], true)),
            $rootUid,
        );

        return $this->sortRecordsBySourceLayout($records->concat($footer), $t3Page);
    }

    /**
     * Marks cloned footer records for conversion into reusable Pagible elements.
     *
     * @param  Collection<int|string, mixed>  $records
     * @return Collection<int, mixed>
     */
    protected function markSharedFooterRecords(Collection $records, int $sourcePage): Collection
    {
        return $records->map(function ($record) use ($sourcePage) {
            if (! in_array((int) ($record->colPos ?? 0), [10, 11, 12], true)) {
                return $record;
            }

            $record = clone $record;
            $record->_pagible_shared = 'footer';
            $record->_pagible_shared_page = $sourcePage;

            return $record;
        })->values();
    }

    /**
     * Marks cloned TYPO3 records for conversion into reusable Pagible elements.
     *
     * @param  Collection<int|string, mixed>  $records
     * @return Collection<int, mixed>
     */
    protected function markSharedRecords(Collection $records, string $kind, int $sourcePage): Collection
    {
        return $records->map(function ($record) use ($kind, $sourcePage) {
            $record = clone $record;
            $record->_pagible_shared = $kind;
            $record->_pagible_shared_page = $sourcePage;
            $record->_pagible_shared_uid = (int) ($record->uid ?? 0);

            return $record;
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
     * @param  Collection<int|string, mixed>  $records
     * @return Collection<int|string, mixed>
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
            str_starts_with($layout, 'db__') => (string) ($this->backendLayouts?->get(
                (int) substr($layout, strlen('db__'))
            )?->config ?? ''),
            default => '',
        };

        preg_match_all('/\bcolPos\s*=\s*(-?\d+)/i', $config, $matches);

        return array_values(array_unique(array_map('intval', $matches[1] ?? [])));
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
     * @param  Collection<int|string, mixed>  $pages  TYPO3 pages keyed by UID
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
     * @param  Collection<int|string, mixed>  $pages  TYPO3 pages keyed by UID
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
        $sysFile = $this->sysFiles->get($ref->uid_local);

        if (! $sysFile) {
            return null;
        }

        $name = $ref->title ?: $ref->alternative ?: $sysFile->name;
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
     * @param  Collection<int|string, mixed>  $selectedPages
     * @param  Collection<int|string, mixed>  $pages
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
                $this->error("  Failed to import [{$t3Page->uid}] {$t3Page->title}: ".$e->getMessage()); // @phpstan-ignore property.notFound, property.notFound

                continue;
            }

            $processed->put((int) $t3Page->uid, $result['page']); // @phpstan-ignore property.notFound
            $result['updated'] ? $updated++ : $imported++;
            $action = $result['updated'] ? 'Updated' : 'Imported';
            $path = $result['path'] === '' ? '/' : '/'.$result['path'];
            $this->info("  {$action}: {$t3Page->title} ({$path}) [{$result['domain']}]"); // @phpstan-ignore property.notFound
        }

        $importedLabel = $imported === 1 ? 'page' : 'pages';
        $updatedLabel = $updated === 1 ? 'page' : 'pages';
        $this->info("Import complete. {$imported} {$importedLabel} imported, {$updated} {$updatedLabel} updated.");
    }

    /**
     * Imports or updates one selected TYPO3 page.
     *
     * @param  Collection<int|string, mixed>  $pagesById
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

            $parent = $processed->get((int) $parentRecord->uid) // @phpstan-ignore property.notFound
                ?: $this->findSourcePage($parentRecord, $domain);

            if (! $parent) {
                throw new \RuntimeException(sprintf(
                    'Destination parent page [%d] %s has not been imported.',
                    (int) $parentRecord->uid, // @phpstan-ignore property.notFound
                    (string) $parentRecord->title, // @phpstan-ignore property.notFound
                ));
            }
        }

        $records = $this->recordsForPage($t3Page, $contentElements);
        $pageData['theme'] = $this->themeForRecords($records);
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
                $page->update(['created_at' => date('Y-m-d H:i:s', (int) $t3Page->crdate)]);
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
     * @param  Collection<int|string, mixed>  $pagesById
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
     * @param  Collection<int|string, mixed>  $pagesById
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
     * @param  Collection<int|string, mixed>  $pages
     * @param  Collection<int|string, mixed>  $contentElements
     */
    protected function importPages(Collection $pages, Collection $contentElements): void
    {
        $pageMap = $pages->groupBy('pid');
        $pagesById = $pages->keyBy('uid');
        $createdPages = Collection::make();
        $imported = 0;

        $importChildren = function (int $parentUid, Page $parentPage, string $domain) use (&$importChildren, $pageMap, $pagesById, $contentElements, &$createdPages, &$imported) {
            $children = $pageMap->get($parentUid, Collection::make());

            foreach ($children as $t3Page) {
                try {
                    DB::connection(config('cms.db', 'sqlite'))->transaction(function () use ($t3Page, $parentPage, $domain, $pagesById, $contentElements, &$createdPages, &$imported) {
                        $slug = $this->slugFromPath($t3Page->slug);
                        $to = $this->redirectTarget($t3Page, $pagesById);
                        $pageData = $this->buildPageData($t3Page, $slug, $domain, $to);
                        $records = $this->recordsForPage($t3Page, $contentElements);
                        $pageData['theme'] = $this->themeForRecords($records);
                        $content = $this->buildContent($records);

                        $page = $this->createPage($pageData, $content['elements'], $parentPage);
                        $this->createVersion($page, $pageData, $content['elements'], $content['fileIds'], $content['elementIds']);

                        if ($t3Page->crdate > 0) {
                            $page->update(['created_at' => date('Y-m-d H:i:s', $t3Page->crdate)]);
                        }

                        $createdPages->put($t3Page->uid, $page);
                        $imported++;
                        $this->info("  Imported: {$t3Page->title} (/{$slug}) [{$domain}]");
                    });
                } catch (\Exception $e) {
                    $this->error("  Failed to import [{$t3Page->uid}] {$t3Page->title}: ".$e->getMessage());

                    continue;
                }

                if ($createdPages->has($t3Page->uid)) {
                    /** @var Page $childParent */
                    $childParent = $createdPages->get($t3Page->uid);
                    $importChildren($t3Page->uid, $childParent, $domain);
                }
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
        $total = $imported + $rootCount;
        $totalLabel = $total === 1 ? 'page' : 'pages';
        $pageLabel = $imported === 1 ? 'child page' : 'child pages';
        $rootLabel = $rootCount === 1 ? 'root page' : 'root pages';

        $this->info("Import complete. {$total} TYPO3 {$totalLabel} processed ({$rootCount} {$rootLabel}, {$imported} {$pageLabel} imported).");
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
     * @param  Collection<int|string, mixed>  $pages
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
     * @param  Collection<int|string, mixed>  $pages
     */
    protected function printSelectedDryRun(Collection $pages): void
    {
        foreach ($pages as $page) {
            $type = $page->doktype == 4 ? ' [shortcut]' : ''; // @phpstan-ignore property.notFound
            $hidden = $page->hidden ? ' [hidden]' : ''; // @phpstan-ignore property.notFound
            $this->line("[{$page->uid}] {$page->title} ({$page->slug}){$type}{$hidden}"); // @phpstan-ignore property.notFound, property.notFound, property.notFound
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
