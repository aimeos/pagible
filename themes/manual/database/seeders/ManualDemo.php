<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Database\Seeders;

use Aimeos\Cms\Models\Page;
use Aimeos\Cms\Utils;
use Aimeos\Cms\Validation;


/**
 * Manual theme demo for the documentation of Waypoint, a fictional audit log package for Laravel.
 */
class ManualDemo extends AbstractDemo
{
    /** @var array<string, string> Meta descriptions keyed by page path */
    protected const DESCRIPTIONS = [
        'activity' => 'Reference of the Waypoint Activity model: attributes, query scopes and the old and new values of each change.',
        'api' => 'Complete reference of the Waypoint API: the Auditable trait, the Activity model, the Waypoint facade and Artisan commands.',
        'auditable' => 'Reference of the Auditable trait: choose the logged attributes, hide secrets and pause auditing for Eloquent models.',
        'changelog' => 'All notable changes to Waypoint, the audit log package for Laravel, by release.',
        'commands' => 'Artisan commands of Waypoint to install the package, prune old activities and export audit logs.',
        'configuration' => 'All Waypoint configuration options for the database, queue, retention and causer with their default values.',
        'custom-events' => 'Log your own events like logins, exports or approvals in Waypoint next to the automatic model changes.',
        'docs' => 'Learn how to install Waypoint, record changes of your Eloquent models and show an audit trail in your Laravel app.',
        'facade' => 'Reference of the Waypoint facade: log activities manually, set the causer, pause logging and group batches.',
        'getting-started' => 'Install Waypoint, add the Auditable trait to a model and show its history in less than five minutes.',
        'guides' => 'Practical guides for custom events, pruning old activities and testing audit logs in Laravel.',
        'installation' => 'Install Waypoint with Composer, publish the config and run the migration in your Laravel app.',
        'pruning' => 'Keep your audit log small by pruning old activities on a schedule while retaining what compliance needs.',
        'quickstart' => 'Add the Auditable trait to an Eloquent model and show who changed what and when in five minutes.',
        'testing' => 'Assert that your Laravel code writes the right audit log entries with the Waypoint fake.',
    ];

    private string $element;
    /** @var array<string, string> */
    private array $icons = [];
    private string $logoFile;


    /**
     * Creates the API reference section below the home page.
     *
     * @param Page $home Home page
     * @return static Same object for fluent calls
     */
    protected function addApi( Page $home ) : static
    {
        $api = $this->page( [
            'name' => 'API reference',
            'title' => 'Waypoint API Reference',
            'path' => 'api',
        ], [
            $this->text( 'The API reference describes every trait, method and command of Waypoint 2.4. If you are new to Waypoint, start with the [Quickstart](/quickstart) and come back here when you need the details.' ),
            ['id' => Utils::uid(), 'type' => 'cards', 'group' => 'main', 'data' => [
                'columns' => '2',
                'cards' => [
                    ['title' => 'Auditable trait', 'url' => '/auditable', 'file' => $this->icon( 'layers' ), 'text' => 'Record the changes of your Eloquent models.'],
                    ['title' => 'Activity model', 'url' => '/activity', 'file' => $this->icon( 'box' ), 'text' => 'Query, filter and display logged activities.'],
                    ['title' => 'Waypoint facade', 'url' => '/facade', 'file' => $this->icon( 'code' ), 'text' => 'Log manually, set the causer and pause logging.'],
                    ['title' => 'Artisan commands', 'url' => '/commands', 'file' => $this->icon( 'terminal' ), 'text' => 'Install, prune and export from your shell.'],
                ],
            ]],
        ], $home );

        $this->page( [
            'name' => 'Auditable trait',
            'title' => 'Auditable: Record Changes of Eloquent Models',
            'path' => 'auditable',
        ], [
            $this->text( 'The `Auditable` trait listens to the `created`, `updated`, `deleted` and `restored` events of a model and writes an activity for each of them.' ),
            $this->code( 'php', <<<'PHP'
use Illuminate\Database\Eloquent\Model;
use Waypoint\Auditable;

class Invoice extends Model
{
    use Auditable;
}
PHP ),
            $this->heading( 'Properties' ),
            $this->table( [
                ['Property', 'Type', 'Description'],
                ['`$audit`', '`array`', 'Attributes to log, defaults to all fillable attributes'],
                ['`$auditExcept`', '`array`', 'Attributes never to log, e.g. `password`'],
                ['`$auditEvents`', '`array`', 'Model events to log, defaults to all four'],
                ['`$auditEmpty`', '`bool`', 'Log updates without changed attributes, defaults to `false`'],
            ] ),
            $this->heading( 'Methods' ),
            $this->heading( 'activities()', '3' ),
            $this->text( 'Returns a `MorphMany` relation with all activities of the model, newest first.' ),
            $this->code( 'php', '$invoice->activities()->with(\'causer\')->limit(20)->get();' ),
            $this->heading( 'withoutAuditing(callable $callback)', '3' ),
            $this->text( "Runs the callback without writing activities for this model class. Use it for imports and data migrations.\n\n> [!CAUTION]\n> Changes made inside the callback can't be traced or restored from the audit log later." ),
            $this->code( 'php', <<<'PHP'
Invoice::withoutAuditing(function () {
    Invoice::query()->update(['currency' => 'EUR']);
});
PHP ),
            $this->heading( 'auditDescription(string $event)', '3' ),
            $this->text( 'Override this method to store a readable description like "Invoice INV-1042 was paid". It is shown in the activity feed instead of the event name.' ),
        ], $api );

        $this->page( [
            'name' => 'Activity model',
            'title' => 'Activity: Query and Display the Audit Log',
            'path' => 'activity',
        ], [
            $this->text( 'Every logged change is stored as a `Waypoint\Models\Activity`. It is a normal Eloquent model, so you can query it like any other.' ),
            $this->table( [
                ['Attribute', 'Type', 'Description'],
                ['`event`', '`string`', 'Name of the event, e.g. `updated`'],
                ['`subject`', '`Model`', 'Model the activity belongs to'],
                ['`causer`', '`?Model`', 'User who made the change'],
                ['`changes`', '`Collection`', 'Old and new values of the changed attributes'],
                ['`batch_id`', '`?string`', 'Groups activities written by `Waypoint::batch()`'],
                ['`created_at`', '`Carbon`', 'Time of the change'],
            ] ),
            $this->heading( 'Query scopes' ),
            $this->code( 'php', <<<'PHP'
use Waypoint\Models\Activity;

Activity::forSubject($invoice)
    ->causedBy($user)
    ->event('updated')
    ->between(now()->subWeek(), now())
    ->get();
PHP ),
            $this->heading( 'Show the changes' ),
            $this->text( '`changes` contains the old and new value of every changed attribute. Loop over it to render a diff in Blade:' ),
            $this->code( 'html', <<<'HTML'
@foreach ($activity->changes as $attribute => $change)
    <dt>{{ $attribute }}</dt>
    <dd><del>{{ $change['old'] }}</del> <ins>{{ $change['new'] }}</ins></dd>
@endforeach
HTML ),
        ], $api );

        $this->page( [
            'name' => 'Waypoint facade',
            'title' => 'Waypoint Facade: Log Activities Manually',
            'path' => 'facade',
        ], [
            $this->text( 'Use the `Waypoint` facade for everything that isn\'t a model change and to control how activities are written.' ),
            $this->table( [
                ['Method', 'Description'],
                ['`log(string $event)`', 'Starts a new activity, finish it with `save()`'],
                ['`causer(?Model $user)`', 'Sets the causer for all following activities'],
                ['`pause()` / `resume()`', 'Stops and continues logging for the current request'],
                ['`batch(callable $callback)`', 'Groups all activities of the callback under one batch ID'],
            ] ),
            $this->heading( 'Examples' ),
            $this->code( 'php', <<<'PHP'
use Waypoint\Facades\Waypoint;

Waypoint::log('exported')
    ->on($report)
    ->with(['format' => 'csv', 'rows' => 1250])
    ->save();

Waypoint::batch(function () use ($order) {
    $order->markAsShipped();
    $order->invoice->markAsPaid();
});
PHP ),
        ], $api );

        $this->page( [
            'name' => 'Artisan commands',
            'title' => 'Waypoint Artisan Commands',
            'path' => 'commands',
        ], [
            $this->text( 'Waypoint registers its commands automatically. Run `php artisan list waypoint` to see them all.' ),
            $this->table( [
                ['Command', 'Description'],
                ['`waypoint:install`', 'Publishes the config and migration'],
                ['`waypoint:prune`', 'Deletes activities older than the retention period'],
                ['`waypoint:export`', 'Writes activities to a CSV or JSON file'],
                ['`waypoint:stats`', 'Shows the number of activities per model and event'],
            ] ),
            $this->heading( 'Examples' ),
            $this->code( 'bash', <<<'SH'
php artisan waypoint:prune --days=365
php artisan waypoint:export storage/audit.csv --model="App\Models\Invoice"
php artisan waypoint:stats
SH ),
        ], $api );

        return $this;
    }


    /**
     * Creates the changelog below the home page.
     *
     * @param Page $home Home page
     * @return static Same object for fluent calls
     */
    protected function addChangelog( Page $home ) : static
    {
        $this->page( [
            'name' => 'Changelog',
            'title' => 'Waypoint Changelog',
            'path' => 'changelog',
        ], [
            $this->text( 'All notable changes to Waypoint are listed here. Waypoint follows [semantic versioning](https://semver.org), so minor releases never break your code.' ),
            ['id' => Utils::uid(), 'type' => 'timeline', 'group' => 'main', 'data' => [
                'layout' => 'vertical',
                'items' => [
                    ['label' => 'v2.4.0 · Oct 6, 2026', 'title' => 'Batches and exports', 'text' => "- **Added:** `Waypoint::batch()` groups related activities\n- **Added:** `waypoint:export` writes CSV and JSON files\n- **Fixed:** encrypted casts were logged as cipher text"],
                    ['label' => 'v2.3.0 · Aug 18, 2026', 'title' => 'Laravel 13 support', 'text' => "- **Added:** support for Laravel 13\n- **Changed:** activities are written after the database transaction commits\n- **Fixed:** `restored` events were missing for soft-deleted models"],
                    ['label' => 'v2.0.0 · Mar 2, 2026', 'title' => 'Queued logging', 'text' => "- **Breaking:** requires PHP 8.2 and Laravel 11 or later\n- **Breaking:** the `properties` column was renamed to `changes`\n- **Added:** write activities on a queue with `WAYPOINT_QUEUE`"],
                    ['label' => 'v1.6.0 · Nov 10, 2025', 'title' => 'Pruning', 'text' => "- **Added:** `waypoint:prune` and the `retention` option\n- **Added:** `Prunable` support for the Activity model"],
                ],
            ]],
        ], $home );

        return $this;
    }


    /**
     * Creates the documentation section below the home page.
     *
     * @param Page $home Home page
     * @return static Same object for fluent calls
     */
    protected function addDocs( Page $home ) : static
    {
        $docs = $this->page( [
            'name' => 'Docs',
            'title' => 'Waypoint Documentation',
            'path' => 'docs',
        ], [
            $this->text( "Waypoint records every change of your Eloquent models: who changed what, when, and from which value to which. You get a complete audit trail for support, debugging and compliance without writing a single observer.\n\nThis documentation takes you from the first install to custom events and tested audit logs." ),
            ['id' => Utils::uid(), 'type' => 'cards', 'group' => 'main', 'data' => [
                'columns' => '2',
                'cards' => [
                    ['title' => 'Installation', 'url' => '/installation', 'file' => $this->icon( 'download' ), 'text' => 'Install Waypoint with Composer and run the migration.'],
                    ['title' => 'Quickstart', 'url' => '/quickstart', 'file' => $this->icon( 'zap' ), 'text' => 'Log your first model changes in five minutes.'],
                    ['title' => 'Custom events', 'url' => '/custom-events', 'file' => $this->icon( 'clock' ), 'text' => 'Log logins, exports and approvals.'],
                    ['title' => 'API reference', 'url' => '/api', 'file' => $this->icon( 'book' ), 'text' => 'Every trait, method and command in detail.'],
                ],
            ]],
        ], $home );

        $start = $this->page( [
            'name' => 'Getting started',
            'title' => 'Getting Started with Waypoint',
            'path' => 'getting-started',
        ], [
            $this->text( "New to Waypoint? These three pages take you from a fresh Laravel app to a working audit log:\n\n1. [Installation](/installation): require the package and run the migration\n2. [Quickstart](/quickstart): log the changes of your first model\n3. [Configuration](/configuration): set up the queue, retention and causer" ),
        ], $docs );

        $this->page( [
            'name' => 'Installation',
            'title' => 'Install Waypoint',
            'path' => 'installation',
        ], [
            $this->text( "Waypoint requires PHP 8.2 or later and Laravel 11, 12 or 13. It works with MySQL, MariaDB, PostgreSQL, SQLite and SQL Server.\n\n> [!NOTE]\n> Upgrading from Waypoint 1.x? Read the [changelog](/changelog) first, version 2 renamed the `properties` column." ),
            $this->heading( 'Require the package' ),
            $this->code( 'bash', 'composer require waypoint/laravel' ),
            $this->text( 'Laravel discovers the service provider automatically, so you don\'t have to register anything.' ),
            $this->heading( 'Set up the database' ),
            ['id' => Utils::uid(), 'type' => 'timeline', 'group' => 'main', 'data' => [
                'layout' => 'vertical',
                'items' => [
                    ['label' => 'Step 1', 'title' => 'Publish the files', 'text' => 'Run `php artisan waypoint:install`. It copies `config/waypoint.php` and the migration into your app.'],
                    ['label' => 'Step 2', 'title' => 'Run the migration', 'text' => 'Run `php artisan migrate` to create the `activities` table.'],
                    ['label' => 'Step 3', 'title' => 'Log your first changes', 'text' => 'Continue with the [Quickstart](/quickstart) to make your models auditable.'],
                ],
            ]],
            $this->code( 'bash', <<<'SH'
php artisan waypoint:install
php artisan migrate
SH ),
            $this->text( "> [!TIP]\n> Store activities in a separate database by setting `WAYPOINT_CONNECTION`. Your audit log then survives a `migrate:fresh` in development." ),
        ], $start );

        $this->page( [
            'name' => 'Quickstart',
            'title' => 'Waypoint Quickstart',
            'path' => 'quickstart',
        ], [
            $this->text( 'In this quickstart you add an audit trail to invoices and show it on the invoice page. It takes about five minutes.' ),
            $this->heading( 'Make the model auditable' ),
            $this->text( 'Add the `Auditable` trait and list the attributes you want to log:' ),
            $this->code( 'php', <<<'PHP'
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Waypoint\Auditable;

class Invoice extends Model
{
    use Auditable;

    protected array $audit = ['number', 'status', 'total', 'due_at'];
}
PHP ),
            $this->heading( 'Change a record' ),
            $this->text( 'Save the model as usual. Waypoint stores the old and new values together with the logged-in user:' ),
            $this->code( 'php', <<<'PHP'
$invoice->update(['status' => 'paid']);

$invoice->activities()->first()->changes;
// ['status' => ['old' => 'open', 'new' => 'paid']]
PHP ),
            $this->heading( 'Show the history' ),
            $this->text( 'Load the activities with their causer and render them in your Blade view:' ),
            $this->code( 'html', <<<'HTML'
<ul>
    @foreach ($invoice->activities()->with('causer')->get() as $activity)
        <li>{{ $activity->causer?->name }} {{ $activity->event }} {{ $activity->created_at->diffForHumans() }}</li>
    @endforeach
</ul>
HTML ),
            $this->text( "> [!TIP]\n> Run `php artisan waypoint:stats` to see how many activities each model has written so far." ),
            $this->heading( 'Next steps' ),
            $this->text( "- Log logins and exports with [custom events](/custom-events)\n- Keep the table small by [pruning old activities](/pruning)\n- Check your audit log in [tests](/testing)" ),
        ], $start );

        $this->page( [
            'name' => 'Configuration',
            'title' => 'Configure Waypoint',
            'path' => 'configuration',
        ], [
            $this->text( 'All options live in `config/waypoint.php`. Most of them can be set with environment variables, so you rarely need to touch the file.' ),
            $this->heading( 'Environment variables' ),
            $this->table( [
                ['Variable', 'Default', 'Description'],
                ['`WAYPOINT_ENABLED`', '`true`', 'Turns logging on or off globally'],
                ['`WAYPOINT_CONNECTION`', '`null`', 'Database connection, defaults to the connection of your app'],
                ['`WAYPOINT_QUEUE`', '`null`', 'Queue name to write activities asynchronously'],
                ['`WAYPOINT_RETENTION`', '`365`', 'Days to keep activities when pruning'],
            ] ),
            $this->heading( 'Resolve the causer' ),
            $this->text( 'By default, the causer is the user of the default auth guard. Change it if you log changes made through an API or admin guard:' ),
            $this->code( 'php', <<<'PHP'
// config/waypoint.php
return [
    'causer' => fn () => auth('admin')->user() ?? auth()->user(),
    'table' => 'activities',
];
PHP ),
            $this->text( "> [!WARNING]\n> Changing `table` on a running system hides all existing activities. Rename the table in a migration instead." ),
        ], $start );

        $guides = $this->page( [
            'name' => 'Guides',
            'title' => 'Waypoint Guides',
            'path' => 'guides',
        ], [
            $this->text( "Step-by-step guides for the tasks you will face once your audit log is running:\n\n- [Custom events](/custom-events): log more than model changes\n- [Pruning](/pruning): keep the activities table small\n- [Testing](/testing): assert the audit log in your tests" ),
        ], $docs );

        $this->page( [
            'name' => 'Custom events',
            'title' => 'Log Custom Events with Waypoint',
            'path' => 'custom-events',
        ], [
            $this->text( 'Not everything worth logging is a model change. Logins, downloads, approvals and exports belong in the audit trail as well.' ),
            $this->heading( 'Log an event' ),
            $this->code( 'php', <<<'PHP'
use Waypoint\Facades\Waypoint;

Waypoint::log('approved')
    ->on($expense)
    ->with(['amount' => $expense->amount])
    ->save();
PHP ),
            $this->heading( 'Log authentication events' ),
            $this->text( 'Listen to the auth events of Laravel to record logins:' ),
            $this->code( 'php', <<<'PHP'
use Illuminate\Auth\Events\Login;
use Waypoint\Facades\Waypoint;

class LogSuccessfulLogin
{
    public function handle(Login $event): void
    {
        Waypoint::log('login')->on($event->user)->with(['ip' => request()->ip()])->save();
    }
}
PHP ),
            $this->text( "> [!NOTE]\n> Custom events show up in `activities()` of the subject just like model changes, so one query returns the whole history." ),
        ], $guides );

        $this->page( [
            'name' => 'Pruning',
            'title' => 'Prune Old Activities in Waypoint',
            'path' => 'pruning',
        ], [
            $this->text( 'An audit log grows with every save. Prune old activities regularly so queries stay fast and you don\'t keep personal data longer than you need to.' ),
            $this->heading( 'Schedule the command' ),
            $this->code( 'php', <<<'PHP'
// routes/console.php
use Illuminate\Support\Facades\Schedule;

Schedule::command('waypoint:prune')->dailyAt('03:00');
PHP ),
            $this->heading( 'Keep some events longer' ),
            $this->text( 'Set a retention period per event, e.g. to keep deletions for ten years:' ),
            $this->code( 'php', <<<'PHP'
'retention' => [
    'default' => 365,
    'deleted' => 3650,
],
PHP ),
            $this->text( "> [!IMPORTANT]\n> Check the retention rules of your industry before you set the periods. Pruned activities are deleted permanently." ),
        ], $guides );

        $this->page( [
            'name' => 'Testing',
            'title' => 'Test Your Audit Log with Waypoint',
            'path' => 'testing',
        ], [
            $this->text( 'Call `Waypoint::fake()` in your test. Activities are then collected in memory and you can assert them without touching the database.' ),
            $this->code( 'php', <<<'PHP'
use Waypoint\Facades\Waypoint;

public function test_paying_an_invoice_is_logged(): void
{
    Waypoint::fake();

    $this->actingAs($user)->post("/invoices/{$invoice->id}/pay");

    Waypoint::assertLogged('updated', $invoice);
    Waypoint::assertLoggedCount(1);
}
PHP ),
            $this->heading( 'Available assertions' ),
            $this->table( [
                ['Assertion', 'Description'],
                ['`assertLogged($event, $subject)`', 'An activity for the event and subject was written'],
                ['`assertNotLogged($event, $subject)`', 'No such activity was written'],
                ['`assertLoggedCount($count)`', 'Exactly this many activities were written'],
                ['`assertNothingLogged()`', 'No activity was written at all'],
            ] ),
        ], $guides );

        return $this;
    }


    /**
     * Returns a code content element.
     *
     * @param string $language Programming language
     * @param string $text Source code
     * @return array<string, mixed> Code content element
     */
    protected function code( string $language, string $text ) : array
    {
        return ['id' => Utils::uid(), 'type' => 'code', 'group' => 'main', 'data' => [
            'language' => ['value' => $language],
            'text' => $text,
        ]];
    }


    /**
     * Creates the shared Waypoint footer and returns its ID.
     *
     * @return string Element ID
     */
    protected function element() : string
    {
        return $this->element ??= $this->saveElement( 'cards', 'Waypoint footer', ['columns' => '4', 'cards' => [
            ['title' => 'Waypoint', 'text' => "Audit logs for Laravel. Open source and MIT licensed.\n\n[Star Waypoint on GitHub](https://github.com/waypoint-php/waypoint)"],
            ['title' => 'Docs', 'text' => "- [Installation](/installation)\n- [Quickstart](/quickstart)\n- [Configuration](/configuration)\n- [Guides](/guides)"],
            ['title' => 'Reference', 'text' => "- [Auditable trait](/auditable)\n- [Activity model](/activity)\n- [Waypoint facade](/facade)\n- [Artisan commands](/commands)"],
            ['title' => 'Community', 'text' => "- [Changelog](/changelog)\n- [Discussions](https://github.com/waypoint-php/waypoint/discussions)\n- [Report an issue](https://github.com/waypoint-php/waypoint/issues)"],
        ]] );
    }


    /**
     * Returns a heading content element.
     *
     * @param string $title Heading text
     * @param string $level Heading level
     * @return array<string, mixed> Heading content element
     */
    protected function heading( string $title, string $level = '2' ) : array
    {
        return ['id' => Utils::uid(), 'type' => 'heading', 'group' => 'main', 'data' => ['title' => $title, 'level' => $level]];
    }


    /**
     * Returns the Waypoint home page.
     *
     * @return Page Home page
     */
    protected function home() : Page
    {
        $elementId = $this->element();
        $fileId = $this->logoFile();

        $config = [
            'website' => Validation::entry( 'website', ['title' => 'Waypoint'], 'config' ),
        ] + $this->logos( $fileId ) + [
            'manual::project' => [
                'type' => 'manual::project',
                'files' => [],
                'data' => [
                    'name' => 'Waypoint',
                    'version' => 'v2.4',
                    'description' => 'Audit logs for Laravel that record who changed which Eloquent model, when and how.',
                    'repository' => 'https://github.com/waypoint-php/waypoint',
                    'language' => 'PHP',
                    'license' => 'MIT',
                ],
            ],
        ];

        $content = [
            ['id' => Utils::uid(), 'type' => 'hero', 'group' => 'main', 'data' => [
                'title' => 'Audit logs for Laravel *in one line*',
                'subtitle' => 'Waypoint 2.4: batches and exports',
                'text' => 'Know who changed which record, when, and from which value to which. Add one trait to your Eloquent models and Waypoint writes the audit trail for you.',
                'buttons' => [
                    ['label' => 'Get started', 'url' => '/installation'],
                    ['label' => 'View on GitHub', 'url' => 'https://github.com/waypoint-php/waypoint'],
                ],
            ]],
            $this->code( 'php', <<<'PHP'
use Waypoint\Auditable;

class Invoice extends Model
{
    use Auditable;
}

$invoice->update(['status' => 'paid']);
$invoice->activities()->first()->changes; // ['status' => ['old' => 'open', 'new' => 'paid']]
PHP ),
            ['id' => Utils::uid(), 'type' => 'cards', 'group' => 'main', 'data' => [
                'title' => 'Everything an audit log needs, nothing it doesn\'t',
                'columns' => '3',
                'cards' => [
                    ['title' => 'One trait per model', 'file' => $this->icon( 'layers' ), 'text' => 'Creates, updates, deletes and restores are logged with their old and new values.'],
                    ['title' => 'Knows the causer', 'file' => $this->icon( 'shield' ), 'text' => 'Every activity stores the user who made the change, from any auth guard.'],
                    ['title' => 'Custom events', 'file' => $this->icon( 'clock' ), 'text' => 'Log logins, exports and approvals next to the model changes.'],
                    ['title' => 'Queued writes', 'file' => $this->icon( 'gauge' ), 'text' => 'Write activities on a queue so your requests stay fast.'],
                    ['title' => 'Pruning built in', 'file' => $this->icon( 'refresh' ), 'text' => 'Delete old activities on a schedule with a retention period per event.'],
                    ['title' => 'Testable', 'file' => $this->icon( 'code' ), 'text' => 'Fake the audit log in your tests and assert exactly what was written.'],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'cards', 'group' => 'main', 'data' => [
                'layout' => 'figures',
                'cards' => [
                    ['title' => '1.2M', 'text' => 'Installs on Packagist'],
                    ['title' => '3.4k', 'text' => 'Stars on GitHub'],
                    ['title' => '11–13', 'text' => 'Supported Laravel versions'],
                    ['title' => '0', 'text' => 'Observers to write'],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'cards', 'group' => 'main', 'data' => [
                'title' => 'Find your way around',
                'columns' => '3',
                'cards' => [
                    ['title' => 'Getting started', 'url' => '/getting-started', 'file' => $this->icon( 'zap' ), 'text' => 'Install Waypoint and log your first changes in five minutes.'],
                    ['title' => 'Guides', 'url' => '/guides', 'file' => $this->icon( 'book' ), 'text' => 'Custom events, pruning and testing step by step.'],
                    ['title' => 'API reference', 'url' => '/api', 'file' => $this->icon( 'terminal' ), 'text' => 'Every trait, method and Artisan command in detail.'],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'cta', 'group' => 'main', 'data' => [
                'title' => 'Add an audit trail to your app *today*',
                'text' => 'Require Waypoint with Composer and follow the quickstart. It takes about five minutes.',
                'buttons' => [
                    ['label' => 'Read the quickstart', 'url' => '/quickstart'],
                    ['label' => 'Browse the API', 'url' => '/api'],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'reference', 'refid' => $elementId, 'group' => 'footer'],
        ];

        $meta = [
            'meta-tags' => Validation::entry( 'meta-tags', [
                'description' => 'Waypoint is an open source audit log package for Laravel that records who changed which Eloquent model, when and how.',
                'keywords' => 'Waypoint, Laravel, audit log, activity log, Eloquent, PHP, package, documentation',
            ], 'meta' ),
            'social-media' => Validation::entry( 'social-media', [
                'title' => 'Waypoint | Audit Logs for Laravel',
                'description' => 'Audit logs for Laravel in one line.',
                'file' => ['id' => $fileId, 'type' => 'file'],
            ], 'meta' ),
        ];

        return $this->saveRoot( 'Waypoint | Audit Logs for Laravel', $config, $meta, $content, $elementId, $fileId );
    }


    /**
     * Creates a white line icon once and returns its file reference.
     *
     * @param string $name Icon name
     * @return array<string, string> File reference
     */
    protected function icon( string $name ) : array
    {
        $paths = [
            'book' => '<path d="M4 5a2 2 0 0 1 2-2h13v16H6a2 2 0 0 0-2 2z"/><path d="M4 21V5M8 7h7"/>',
            'box' => '<path d="M12 3l8 4.5v9L12 21l-8-4.5v-9z"/><path d="M4 7.5l8 4.5 8-4.5M12 12v9"/>',
            'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
            'code' => '<path d="M8 8l-4 4 4 4M16 8l4 4-4 4M13.5 5l-3 14"/>',
            'download' => '<path d="M12 4v11M7 10l5 5 5-5M5 20h14"/>',
            'gauge' => '<path d="M4 16a8 8 0 1 1 16 0"/><path d="M12 16l4-5"/>',
            'layers' => '<path d="M12 3l9 5-9 5-9-5z"/><path d="M3 13l9 5 9-5"/>',
            'refresh' => '<path d="M20 11a8 8 0 0 0-14.6-4.5L4 8M4 4v4h4"/><path d="M4 13a8 8 0 0 0 14.6 4.5L20 16M20 20v-4h-4"/>',
            'shield' => '<path d="M12 3l7 3v5c0 4.5-3 8.2-7 10-4-1.8-7-5.5-7-10V6z"/><path d="M9 12l2 2 4-4"/>',
            'terminal' => '<rect x="3" y="4" width="18" height="16" rx="2"/><path d="M7 9l3 3-3 3M12 15h5"/>',
            'zap' => '<path d="M13 3L5 14h6l-1 7 8-11h-6z"/>',
        ];

        $this->icons[$name] ??= $this->svgFile(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="#FFFFFF" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">' . $paths[$name] . '</svg>',
            'icon-' . $name . '.svg',
            ucfirst( $name ) . ' icon',
            'White line icon: ' . $name,
            true,
        );

        return ['id' => $this->icons[$name], 'type' => 'file'];
    }


    /**
     * Creates the Waypoint SVG logo and returns its file ID.
     *
     * @return string File ID
     */
    protected function logoFile() : string
    {
        if( !isset( $this->logoFile ) )
        {
            $svg = <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 166 40" role="img" aria-labelledby="title desc">
  <title id="title">Waypoint logo</title>
  <desc id="desc">Blue rounded square with a white map pin beside the wordmark Waypoint</desc>
  <rect x="2" y="4" width="32" height="32" rx="8" fill="#2563EB"/>
  <path d="M18 31s-7-6.5-7-12.5a7 7 0 0 1 14 0c0 6-7 12.5-7 12.5z" fill="#FFFFFF"/>
  <circle cx="18" cy="18.5" r="2.75" fill="#2563EB"/>
  <text x="44" y="29" fill="#64748B" font-family="system-ui, -apple-system, 'Segoe UI', sans-serif" font-size="24" font-weight="700" letter-spacing="-1">Waypoint</text>
</svg>
SVG;

            $this->logoFile = $this->svgFile( $svg, 'waypoint-logo.svg', 'Waypoint logo',
                'Blue rounded square with a white map pin beside the wordmark Waypoint', true );
        }

        return $this->logoFile;
    }


    /**
     * Creates a documentation page below the given parent and returns it.
     *
     * @param array<string, mixed> $data Page attributes
     * @param array<int, array<string, mixed>> $content Content elements
     * @param Page $parent Parent page
     * @return Page Created page
     */
    protected function page( array $data, array $content, Page $parent ) : Page
    {
        $elementId = $this->element();
        $fileId = $this->logoFile();

        $footer = [
            ['id' => Utils::uid(), 'type' => 'reference', 'refid' => $elementId, 'group' => 'footer'],
        ];

        return $this->savePage( $data + ['lang' => 'en', 'type' => 'docs', 'status' => 1], $content, $parent,
            $elementId, $fileId, $footer, 'Waypoint, Laravel, audit log, activity log, Eloquent, documentation' );
    }


    /**
     * Builds the Manual demo page tree.
     */
    protected function pages() : void
    {
        $home = $this->home();

        $this->addDocs( $home )
            ->addApi( $home )
            ->addChangelog( $home );
    }


    /**
     * Returns a table content element with a header row.
     *
     * @param array<int, array<int, string>> $rows Table rows including the header
     * @return array<string, mixed> Table content element
     */
    protected function table( array $rows ) : array
    {
        return ['id' => Utils::uid(), 'type' => 'table', 'group' => 'main', 'data' => [
            'header' => 'row',
            'table' => $rows,
        ]];
    }


    /**
     * Returns a text content element.
     *
     * @param string $text Markdown text
     * @return array<string, mixed> Text content element
     */
    protected function text( string $text ) : array
    {
        return ['id' => Utils::uid(), 'type' => 'text', 'group' => 'main', 'data' => ['text' => $text]];
    }
}
