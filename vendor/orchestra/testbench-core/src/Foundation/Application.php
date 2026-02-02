<?php

namespace Orchestra\Testbench\Foundation;

use Illuminate\Console\Application as Artisan;
use Illuminate\Console\Scheduling\ScheduleListCommand;
use Illuminate\Console\Signals;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Database\Schema\Builder as SchemaBuilder;
use Illuminate\Foundation\Bootstrap\HandleExceptions;
use Illuminate\Foundation\Bootstrap\LoadEnvironmentVariables;
use Illuminate\Foundation\Bootstrap\RegisterProviders;
use Illuminate\Foundation\Console\AboutCommand;
use Illuminate\Foundation\Console\ChannelListCommand;
use Illuminate\Foundation\Console\RouteListCommand;
use Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull;
use Illuminate\Foundation\Http\Middleware\PreventRequestsDuringMaintenance;
use Illuminate\Foundation\Http\Middleware\TrimStrings;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Http\Middleware\TrustHosts;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\JsonApi\JsonApiResource;
use Illuminate\Mail\Markdown;
use Illuminate\Queue\Console\WorkCommand;
use Illuminate\Queue\Queue;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Arr;
use Illuminate\Support\EncodedHtmlString;
use Illuminate\Support\Once;
use Illuminate\Support\Sleep;
use Illuminate\Support\Str;
use Illuminate\Validation\Validator;
use Illuminate\View\Component;
use Orchestra\Testbench\Concerns\CreatesApplication;
use Orchestra\Testbench\Console\Commander;
use Orchestra\Testbench\Contracts\Config as ConfigContract;
use Orchestra\Testbench\Workbench\Workbench;

use function Orchestra\Sidekick\Filesystem\join_paths;

/**
 * @api
 *
 * @phpstan-import-type TExtraConfig from \Orchestra\Testbench\Foundation\Config
 * @phpstan-import-type TOptionalExtraConfig from \Orchestra\Testbench\Foundation\Config
 *
 * @phpstan-type TConfig array{
 *   extra?: TOptionalExtraConfig,
 *   load_environment_variables?: bool,
 *   enabled_package_discoveries?: bool
 * }
 */
class Application
{
    use CreatesApplication {
        resolveApplicationResolvingCallback as protected resolveApplicationResolvingCallbackFromTrait;
        resolveApplicationConfiguration as protected resolveApplicationConfigurationFromTrait;
    }

    /**
     * The Illuminate application instance.
     *
     * @var \Illuminate\Foundation\Application|null
     */
    protected $app;

    /**
     * List of configurations.
     *
     * @var array<string, mixed>
     *
     * @phpstan-var TExtraConfig
     */
    protected array $config = [
        'env' => [],
        'providers' => [],
        'dont-discover' => [],
        'bootstrappers' => [],
    ];

    /**
     * The application resolving callback.
     *
     * @var (callable(\Illuminate\Foundation\Application):(void))|null
     */
    protected $resolvingCallback;

    /**
     * Load Environment variables.
     *
     * @var bool
     */
    protected bool $loadEnvironmentVariables = false;

    /**
     * Create new application resolver.
     *
     * @param  string|null  $basePath
     * @param  (callable(\Illuminate\Foundation\Application):(void))|null  $resolvingCallback
     */
    public function __construct(
        protected readonly ?string $basePath = null,
        ?callable $resolvingCallback = null
    ) {
        $this->resolvingCallback = $resolvingCallback;
    }

    /**
     * Create new application resolver.
     *
     * @param  string|null  $basePath
     * @param  (callable(\Illuminate\Foundation\Application):(void))|null  $resolvingCallback
     * @param  array<string, mixed>  $options
     *
     * @phpstan-param TConfig  $options
     *
     * @return static
     */
    public static function make(?string $basePath = null, ?callable $resolvingCallback = null, array $options = [])
    {
        return (new static($basePath, $resolvingCallback))->configure($options);
    }

    /**
     * Create new application resolver from configuration file.
     *
     * @param  \Orchestra\Testbench\Contracts\Config  $config
     * @param  (callable(\Illuminate\Foundation\Application):(void))|null  $resolvingCallback
     * @param  array<string, mixed>  $options
     *
     * @phpstan-param TConfig  $options
     *
     * @return static
     */
    public static function makeFromConfig(ConfigContract $config, ?callable $resolvingCallback = null, array $options = [])
    {
        $basePath = $config['laravel'] ?? static::applicationBasePath();

        return (new static($config['laravel'], $resolvingCallback))->configure(array_merge($options, [
            'load_environment_variables' => is_file("{$basePath}/.env"),
            'extra' => $config->getExtraAttributes(),
        ]));
    }

    /**
     * Create symlink to vendor path via new application instance.
     *
     * @param  string|null  $basePath
     * @param  string  $workingVendorPath
     * @return \Illuminate\Foundation\Application
     *
     * @codeCoverageIgnore
     */
    public static function createVendorSymlink(?string $basePath, string $workingVendorPath)
    {
        $app = static::create(basePath: $basePath, options: ['extra' => ['dont-discover' => ['*']]]);

        (new Actions\CreateVendorSymlink($workingVendorPath))->handle($app);

        return $app;
    }

    /**
     * Delete symlink to vendor path via new application instance.
     *
     * @param  string|null  $basePath
     * @return \Illuminate\Foundation\Application
     *
     * @codeCoverageIgnore
     */
    public static function deleteVendorSymlink(?string $basePath)
    {
        $app = static::create(basePath: $basePath, options: ['extra' => ['dont-discover' => ['*']]]);

        (new Actions\DeleteVendorSymlink)->handle($app);

        return $app;
    }

    /**
     * Create new application instance.
     *
     * @param  string|null  $basePath
     * @param  (callable(\Illuminate\Foundation\Application):(void))|null  $resolvingCallback
     * @param  array<string, mixed>  $options
     *
     * @phpstan-param TConfig  $options
     *
     * @return \Illuminate\Foundation\Application
     */
    public static function create(?string $basePath = null, ?callable $resolvingCallback = null, array $options = [])
    {
        return static::make($basePath, $resolvingCallback, $options)->createApplication();
    }

    /**
     * Create new application instance from configuration file.
     *
     * @param  \Orchestra\Testbench\Contracts\Config  $config
     * @param  (callable(\Illuminate\Foundation\Application):(void))|null  $resolvingCallback
     * @param  array<string, mixed>  $options
     *
     * @phpstan-param TConfig  $options
     *
     * @return \Illuminate\Foundation\Application
     */
    public static function createFromConfig(ConfigContract $config, ?callable $resolvingCallback = null, array $options = [])
    {
        return static::makeFromConfig($config, $resolvingCallback, $options)->createApplication();
    }

    /**
     * Flush the application states.
     *
     * @param  \Orchestra\Testbench\Console\Commander|\Orchestra\Testbench\PHPUnit\TestCase  $instance
     * @return void
     */
    public static function flushState(object $instance): void
    {
        AboutCommand::flushState();
        Artisan::forgetBootstrappers();
        ChannelListCommand::resolveTerminalWidthUsing(null);
        Component::flushCache();
        Component::forgetComponentsResolver();
        Component::forgetFactory();
        ConvertEmptyStringsToNull::flushState();
        EncodedHtmlString::flushState();
        Factory::flushState();

        if (! $instance instanceof Commander) {
            HandleExceptions::flushState($instance);
        }

        if (class_exists(JsonApiResource::class)) {
            JsonResource::flushState();
            JsonApiResource::flushState();
        } else {
            JsonResource::wrap('data');
        }

        Markdown::flushState();
        Migrator::withoutMigrations([]);
        Model::handleDiscardedAttributeViolationUsing(null);
        Model::handleLazyLoadingViolationUsing(null);
        Model::handleMissingAttributeViolationUsing(null);
        Model::automaticallyEagerLoadRelationships(false);
        Model::preventAccessingMissingAttributes(false);
        Model::preventLazyLoading(false);
        Model::preventSilentlyDiscardingAttributes(false);
        Once::flush();
        PreventRequestsDuringMaintenance::flushState();
        Queue::createPayloadUsing(null);
        RegisterProviders::flushState();
        RouteListCommand::resolveTerminalWidthUsing(null);
        ScheduleListCommand::resolveTerminalWidthUsing(null);
        SchemaBuilder::$defaultStringLength = 255;
        SchemaBuilder::$defaultMorphKeyType = 'int';
        Signals::resolveAvailabilityUsing(null); // @phpstan-ignore argument.type
        Sleep::fake(false);
        Str::createRandomStringsNormally();
        Str::createUlidsNormally();
        Str::createUuidsNormally();
        ThrottleRequests::shouldHashKeys();
        TrimStrings::flushState();
        TrustProxies::flushState();
        TrustHosts::flushState();
        Validator::flushState();
        ValidateCsrfToken::flushState();
        WorkCommand::flushState();
    }

    /**
     * Configure the application options.
     *
     * @param  array<string, mixed>  $options
     *
     * @phpstan-param TConfig  $options
     *
     * @return $this
     */
    public function configure(array $options)
    {
        if (isset($options['load_environment_variables']) && \is_bool($options['load_environment_variables'])) {
            $this->loadEnvironmentVariables = $options['load_environment_variables'];
        }

        if (isset($options['enables_package_discoveries']) && \is_bool($options['enables_package_discoveries'])) {
            Arr::set($options, 'extra.dont-discover', []);
        }

        /** @var TExtraConfig $config */
        $config = Arr::only($options['extra'] ?? [], array_keys($this->config));

        $this->config = $config;

        return $this;
    }

    /**
     * Ignore package discovery from.
     *
     * @api
     *
     * @return array<int, string>
     */
    public function ignorePackageDiscoveriesFrom()
    {
        return $this->config['dont-discover'] ?? [];
    }

    /**
     * Get package providers.
     *
     * @api
     *
     * @param  \Illuminate\Foundation\Application  $app
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app)
    {
        return $this->config['providers'] ?? [];
    }

    /**
     * Get package bootstrapper.
     *
     * @api
     *
     * @param  \Illuminate\Foundation\Application  $app
     * @return array<int, class-string>
     */
    protected function getPackageBootstrappers($app)
    {
        if (\is_null($bootstrappers = ($this->config['bootstrappers'] ?? null))) {
            return [];
        }

        return Arr::wrap($bootstrappers);
    }

    /**
     * Resolve application resolving callback.
     *
     * @internal
     *
     * @param  \Illuminate\Foundation\Application  $app
     * @return void
     */
    protected function resolveApplicationResolvingCallback($app): void
    {
        $this->resolveApplicationResolvingCallbackFromTrait($app);

        if (\is_callable($this->resolvingCallback)) {
            \call_user_func($this->resolvingCallback, $app);
        }
    }

    /**
     * Resolve the application's base path.
     *
     * @api
     *
     * @return string
     */
    protected function getApplicationBasePath()
    {
        return $this->basePath ?? static::applicationBasePath();
    }

    /**
     * Resolve application core environment variables implementation.
     *
     * @internal
     *
     * @param  \Illuminate\Foundation\Application  $app
     * @return void
     */
    protected function resolveApplicationEnvironmentVariables($app)
    {
        Env::disablePutenv();

        $app->terminating(static function () {
            Env::enablePutenv();
        });

        if ($this->loadEnvironmentVariables === true) {
            $app->make(LoadEnvironmentVariables::class)->bootstrap($app);
        }

        (new Bootstrap\LoadEnvironmentVariablesFromArray($this->config['env'] ?? []))->bootstrap($app);
    }

    /**
     * Resolve application core configuration implementation.
     *
     * @internal
     *
     * @param  \Illuminate\Foundation\Application  $app
     * @return void
     */
    protected function resolveApplicationConfiguration($app)
    {
        $this->resolveApplicationConfigurationFromTrait($app);

        (new Bootstrap\EnsuresDefaultConfiguration)->bootstrap($app);
    }

    /**
     * Resolve application Console Kernel implementation.
     *
     * @api
     *
     * @param  \Illuminate\Foundation\Application  $app
     * @return void
     */
    protected function resolveApplicationConsoleKernel($app)
    {
        if ($this->hasCustomApplicationKernels() === true) {
            return;
        }

        $kernel = Workbench::applicationConsoleKernel() ?? 'Orchestra\Testbench\Console\Kernel';

        if (is_file($app->basePath(join_paths('app', 'Console', 'Kernel.php'))) && class_exists('App\Console\Kernel')) {
            $kernel = 'App\Console\Kernel';
        }

        $app->singleton('Illuminate\Contracts\Console\Kernel', $kernel);
    }

    /**
     * Resolve application HTTP Kernel implementation.
     *
     * @api
     *
     * @param  \Illuminate\Foundation\Application  $app
     * @return void
     */
    protected function resolveApplicationHttpKernel($app)
    {
        if ($this->hasCustomApplicationKernels() === true) {
            return;
        }

        $kernel = Workbench::applicationHttpKernel() ?? 'Orchestra\Testbench\Http\Kernel';

        if (is_file($app->basePath(join_paths('app', 'Http', 'Kernel.php'))) && class_exists('App\Http\Kernel')) {
            $kernel = 'App\Http\Kernel';
        }

        $app->singleton('Illuminate\Contracts\Http\Kernel', $kernel);
    }
}
