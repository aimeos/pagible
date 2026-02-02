<?php

namespace Orchestra\Canvas;

use Illuminate\Cache\Console\CacheTableCommand;
use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Database\Console\Factories\FactoryMakeCommand;
use Illuminate\Database\Console\Seeds\SeederMakeCommand;
use Illuminate\Foundation\Console\CastMakeCommand;
use Illuminate\Foundation\Console\ChannelMakeCommand;
use Illuminate\Foundation\Console\ClassMakeCommand;
use Illuminate\Foundation\Console\ComponentMakeCommand;
use Illuminate\Foundation\Console\ConsoleMakeCommand;
use Illuminate\Foundation\Console\EnumMakeCommand;
use Illuminate\Foundation\Console\EventMakeCommand;
use Illuminate\Foundation\Console\ExceptionMakeCommand;
use Illuminate\Foundation\Console\InterfaceMakeCommand;
use Illuminate\Foundation\Console\JobMakeCommand;
use Illuminate\Foundation\Console\JobMiddlewareMakeCommand;
use Illuminate\Foundation\Console\ListenerMakeCommand;
use Illuminate\Foundation\Console\MailMakeCommand;
use Illuminate\Foundation\Console\ModelMakeCommand;
use Illuminate\Foundation\Console\NotificationMakeCommand;
use Illuminate\Foundation\Console\ObserverMakeCommand;
use Illuminate\Foundation\Console\PolicyMakeCommand;
use Illuminate\Foundation\Console\ProviderMakeCommand;
use Illuminate\Foundation\Console\RequestMakeCommand;
use Illuminate\Foundation\Console\ResourceMakeCommand;
use Illuminate\Foundation\Console\RuleMakeCommand;
use Illuminate\Foundation\Console\ScopeMakeCommand;
use Illuminate\Foundation\Console\TestMakeCommand;
use Illuminate\Foundation\Console\ViewMakeCommand;
use Illuminate\Notifications\Console\NotificationTableCommand;
use Illuminate\Queue\Console\BatchesTableCommand;
use Illuminate\Queue\Console\FailedTableCommand;
use Illuminate\Queue\Console\TableCommand as QueueTableCommand;
use Illuminate\Routing\Console\ControllerMakeCommand;
use Illuminate\Routing\Console\MiddlewareMakeCommand;
use Illuminate\Session\Console\SessionTableCommand;
use Illuminate\Support\ServiceProvider;

/**
 * @see https://github.com/laravel/framework/blob/11.x/src/Illuminate/Foundation/Providers/ArtisanServiceProvider.php
 */
class LaravelServiceProvider extends ServiceProvider implements DeferrableProvider
{
    /**
     * Register the service provider.
     */
    public function boot(): void
    {
        $this->registerCastMakeCommand();
        $this->registerChannelMakeCommand();
        $this->registerClassMakeCommand();
        $this->registerComponentMakeCommand();
        $this->registerConsoleMakeCommand();
        $this->registerControllerMakeCommand();
        $this->registerEnumMakeCommand();
        $this->registerEventMakeCommand();
        $this->registerExceptionMakeCommand();
        $this->registerFactoryMakeCommand();
        $this->registerJobMakeCommand();
        $this->registerJobMiddlewareMakeCommand();
        $this->registerInterfaceMakeCommand();
        $this->registerListenerMakeCommand();
        $this->registerMailMakeCommand();
        $this->registerMiddlewareMakeCommand();
        $this->registerMigrateMakeCommand();
        $this->registerModelMakeCommand();
        $this->registerNotificationMakeCommand();
        $this->registerObserverMakeCommand();
        $this->registerPolicyMakeCommand();
        $this->registerProviderMakeCommand();
        $this->registerRequestMakeCommand();
        $this->registerResourceMakeCommand();
        $this->registerRuleMakeCommand();
        $this->registerScopeMakeCommand();
        $this->registerSeederMakeCommand();
        $this->registerTestMakeCommand();
        $this->registerViewMakeCommand();

        $this->registerCacheTableCommand();
        $this->registerNotificationTableCommand();
        $this->registerQueueBatchesTableCommand();
        $this->registerQueueFailedTableCommand();
        $this->registerQueueTableCommand();
        $this->registerSessionTableCommand();

        $this->registerUserFactoryMakeCommand();
        $this->registerUserModelMakeCommand();

        $this->commands([
            Console\CastMakeCommand::class,
            Console\ChannelMakeCommand::class,
            Console\ComponentMakeCommand::class,
            Console\ConsoleMakeCommand::class,
            Console\ClassMakeCommand::class,
            Console\ControllerMakeCommand::class,
            Console\EnumMakeCommand::class,
            Console\EventMakeCommand::class,
            Console\ExceptionMakeCommand::class,
            Console\FactoryMakeCommand::class,
            Console\JobMakeCommand::class,
            Console\InterfaceMakeCommand::class,
            Console\ListenerMakeCommand::class,
            Console\MailMakeCommand::class,
            Console\MiddlewareMakeCommand::class,
            Console\MigrateMakeCommand::class,
            Console\ModelMakeCommand::class,
            Console\NotificationMakeCommand::class,
            Console\ObserverMakeCommand::class,
            Console\PolicyMakeCommand::class,
            Console\ProviderMakeCommand::class,
            Console\RequestMakeCommand::class,
            Console\ResourceMakeCommand::class,
            Console\RuleMakeCommand::class,
            Console\ScopeMakeCommand::class,
            Console\SeederMakeCommand::class,
            Console\TestMakeCommand::class,
            Console\ViewMakeCommand::class,

            Console\BatchesTableCommand::class,
            Console\CacheTableCommand::class,
            Console\FailedTableCommand::class,
            Console\NotificationTableCommand::class,
            Console\QueueTableCommand::class,
            Console\SessionTableCommand::class,

            Console\UserFactoryMakeCommand::class,
            Console\UserModelMakeCommand::class,
        ]);
    }

    /**
     * Register the command.
     */
    protected function registerCastMakeCommand(): void
    {
        $this->app->singleton(
            CastMakeCommand::class, static fn ($app) => new Console\CastMakeCommand($app['files'])
        );
    }

    /**
     * Register the command.
     */
    protected function registerChannelMakeCommand(): void
    {
        $this->app->singleton(
            ChannelMakeCommand::class, static fn ($app) => new Console\ChannelMakeCommand($app['files'])
        );
    }

    /**
     * Register the command.
     */
    protected function registerClassMakeCommand(): void
    {
        $this->app->singleton(
            ClassMakeCommand::class, static fn ($app) => new Console\ClassMakeCommand($app['files'])
        );
    }

    /**
     * Register the command.
     */
    protected function registerComponentMakeCommand(): void
    {
        $this->app->singleton(
            ComponentMakeCommand::class, static fn ($app) => new Console\ComponentMakeCommand($app['files'])
        );
    }

    /**
     * Register the command.
     */
    protected function registerConsoleMakeCommand(): void
    {
        $this->app->singleton(
            ConsoleMakeCommand::class, static fn ($app) => new Console\ConsoleMakeCommand($app['files'])
        );
    }

    /**
     * Register the command.
     */
    protected function registerControllerMakeCommand(): void
    {
        $this->app->singleton(
            ControllerMakeCommand::class, static fn ($app) => new Console\ControllerMakeCommand($app['files'])
        );
    }

    /**
     * Register the command.
     */
    protected function registerEnumMakeCommand(): void
    {
        $this->app->singleton(
            EnumMakeCommand::class, fn ($app) => new Console\EnumMakeCommand($app['files'])
        );
    }

    /**
     * Register the command.
     */
    protected function registerEventMakeCommand(): void
    {
        $this->app->singleton(
            EventMakeCommand::class, static fn ($app) => new Console\EventMakeCommand($app['files'])
        );
    }

    /**
     * Register the command.
     */
    protected function registerExceptionMakeCommand(): void
    {
        $this->app->singleton(
            ExceptionMakeCommand::class, static fn ($app) => new Console\ExceptionMakeCommand($app['files'])
        );
    }

    /**
     * Register the command.
     */
    protected function registerFactoryMakeCommand(): void
    {
        $this->app->singleton(
            FactoryMakeCommand::class, static fn ($app) => new Console\FactoryMakeCommand($app['files'])
        );
    }

    /**
     * Register the command.
     */
    protected function registerJobMakeCommand(): void
    {
        $this->app->singleton(
            JobMakeCommand::class, static fn ($app) => new Console\JobMakeCommand($app['files'])
        );
    }

    /**
     * Register the command.
     *
     * @return void
     */
    protected function registerJobMiddlewareMakeCommand()
    {
        $this->app->singleton(
            JobMiddlewareMakeCommand::class, static fn ($app) => new Console\JobMiddlewareMakeCommand($app['files'])
        );
    }

    /**
     * Register the command.
     */
    protected function registerInterfaceMakeCommand(): void
    {
        $this->app->singleton(
            InterfaceMakeCommand::class, static fn ($app) => new Console\InterfaceMakeCommand($app['files'])
        );
    }

    /**
     * Register the command.
     */
    protected function registerListenerMakeCommand(): void
    {
        $this->app->singleton(
            ListenerMakeCommand::class, static fn ($app) => new Console\ListenerMakeCommand($app['files'])
        );
    }

    /**
     * Register the command.
     */
    protected function registerMailMakeCommand(): void
    {
        $this->app->singleton(
            MailMakeCommand::class, static fn ($app) => new Console\MailMakeCommand($app['files'])
        );
    }

    /**
     * Register the command.
     */
    protected function registerMiddlewareMakeCommand(): void
    {
        $this->app->singleton(
            MiddlewareMakeCommand::class, static fn ($app) => new Console\MiddlewareMakeCommand($app['files'])
        );
    }

    /**
     * Register the command.
     */
    protected function registerMigrateMakeCommand(): void
    {
        $this->app->singleton(Console\MigrateMakeCommand::class, static function ($app) {
            // Once we have the migration creator registered, we will create the command
            // and inject the creator. The creator is responsible for the actual file
            // creation of the migrations, and may be extended by these developers.
            $creator = $app['migration.creator'];

            $composer = $app['composer'];

            return new Console\MigrateMakeCommand($creator, $composer);
        });
    }

    /**
     * Register the command.
     */
    protected function registerModelMakeCommand(): void
    {
        $this->app->singleton(
            ModelMakeCommand::class, static fn ($app) => new Console\ModelMakeCommand($app['files'])
        );
    }

    /**
     * Register the command.
     */
    protected function registerNotificationMakeCommand(): void
    {
        $this->app->singleton(
            NotificationMakeCommand::class, static fn ($app) => new Console\NotificationMakeCommand($app['files'])
        );
    }

    /**
     * Register the command.
     */
    protected function registerObserverMakeCommand(): void
    {
        $this->app->singleton(
            ObserverMakeCommand::class, static fn ($app) => new Console\ObserverMakeCommand($app['files'])
        );
    }

    /**
     * Register the command.
     */
    protected function registerPolicyMakeCommand(): void
    {
        $this->app->singleton(
            PolicyMakeCommand::class, static fn ($app) => new Console\PolicyMakeCommand($app['files'])
        );
    }

    /**
     * Register the command.
     */
    protected function registerProviderMakeCommand(): void
    {
        $this->app->singleton(
            ProviderMakeCommand::class, static fn ($app) => new Console\ProviderMakeCommand($app['files'])
        );
    }

    /**
     * Register the command.
     */
    protected function registerRequestMakeCommand(): void
    {
        $this->app->singleton(
            RequestMakeCommand::class, static fn ($app) => new Console\RequestMakeCommand($app['files'])
        );
    }

    /**
     * Register the command.
     */
    protected function registerResourceMakeCommand(): void
    {
        $this->app->singleton(
            ResourceMakeCommand::class, static fn ($app) => new Console\ResourceMakeCommand($app['files'])
        );
    }

    /**
     * Register the command.
     */
    protected function registerRuleMakeCommand(): void
    {
        $this->app->singleton(
            RuleMakeCommand::class, static fn ($app) => new Console\RuleMakeCommand($app['files'])
        );
    }

    /**
     * Register the command.
     */
    protected function registerScopeMakeCommand(): void
    {
        $this->app->singleton(
            ScopeMakeCommand::class, static fn ($app) => new Console\ScopeMakeCommand($app['files'])
        );
    }

    /**
     * Register the command.
     */
    protected function registerSeederMakeCommand(): void
    {
        $this->app->singleton(
            SeederMakeCommand::class, static fn ($app) => new Console\SeederMakeCommand($app['files'])
        );
    }

    /**
     * Register the command.
     */
    protected function registerTestMakeCommand(): void
    {
        $this->app->singleton(
            TestMakeCommand::class, static fn ($app) => new Console\TestMakeCommand($app['files'])
        );
    }

    /**
     * Register the command.
     */
    protected function registerViewMakeCommand(): void
    {
        $this->app->singleton(
            ViewMakeCommand::class, static fn ($app) => new Console\ViewMakeCommand($app['files'])
        );
    }

    /**
     * Register the command.
     */
    protected function registerCacheTableCommand(): void
    {
        $this->app->singleton(
            CacheTableCommand::class, static fn ($app) => new Console\CacheTableCommand($app['files'])
        );
    }

    /**
     * Register the command.
     */
    protected function registerNotificationTableCommand(): void
    {
        $this->app->singleton(
            NotificationTableCommand::class, static fn ($app) => new Console\NotificationTableCommand($app['files'])
        );
    }

    /**
     * Register the command.
     */
    protected function registerQueueBatchesTableCommand(): void
    {
        $this->app->singleton(
            BatchesTableCommand::class, static fn ($app) => new Console\BatchesTableCommand($app['files'])
        );
    }

    /**
     * Register the command.
     */
    protected function registerQueueFailedTableCommand(): void
    {
        $this->app->singleton(
            FailedTableCommand::class, static fn ($app) => new Console\FailedTableCommand($app['files'])
        );
    }

    /**
     * Register the command.
     */
    protected function registerQueueTableCommand(): void
    {
        $this->app->singleton(
            QueueTableCommand::class, static fn ($app) => new Console\QueueTableCommand($app['files'])
        );
    }

    /**
     * Register the command.
     */
    protected function registerSessionTableCommand(): void
    {
        $this->app->singleton(
            SessionTableCommand::class, static fn ($app) => new Console\SessionTableCommand($app['files'])
        );
    }

    /**
     * Register the command.
     */
    protected function registerUserFactoryMakeCommand(): void
    {
        $this->app->singleton(
            Console\UserFactoryMakeCommand::class, static fn ($app) => new Console\UserFactoryMakeCommand($app['files'])
        );
    }

    /**
     * Register the command.
     */
    protected function registerUserModelMakeCommand(): void
    {
        $this->app->singleton(
            Console\UserModelMakeCommand::class, static fn ($app) => new Console\UserModelMakeCommand($app['files'])
        );
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array<int, class-string>
     */
    public function provides()
    {
        return [
            CastMakeCommand::class,
            Console\CastMakeCommand::class,
            ChannelMakeCommand::class,
            Console\ChannelMakeCommand::class,
            ClassMakeCommand::class,
            Console\ClassMakeCommand::class,
            ComponentMakeCommand::class,
            Console\ComponentMakeCommand::class,
            ConsoleMakeCommand::class,
            Console\ConsoleMakeCommand::class,
            ControllerMakeCommand::class,
            Console\ControllerMakeCommand::class,
            EnumMakeCommand::class,
            Console\EnumMakeCommand::class,
            EventMakeCommand::class,
            Console\EventMakeCommand::class,
            ExceptionMakeCommand::class,
            Console\ExceptionMakeCommand::class,
            FactoryMakeCommand::class,
            Console\FactoryMakeCommand::class,
            InterfaceMakeCommand::class,
            Console\InterfaceMakeCommand::class,
            JobMakeCommand::class,
            Console\JobMakeCommand::class,
            JobMiddlewareMakeCommand::class,
            Console\JobMiddlewareMakeCommand::class,
            ListenerMakeCommand::class,
            Console\ListenerMakeCommand::class,
            MailMakeCommand::class,
            Console\MailMakeCommand::class,
            MiddlewareMakeCommand::class,
            Console\MiddlewareMakeCommand::class,
            Console\MigrateMakeCommand::class,
            ModelMakeCommand::class,
            Console\ModelMakeCommand::class,
            NotificationMakeCommand::class,
            Console\NotificationMakeCommand::class,
            ObserverMakeCommand::class,
            Console\ObserverMakeCommand::class,
            PolicyMakeCommand::class,
            Console\PolicyMakeCommand::class,
            ProviderMakeCommand::class,
            Console\ProviderMakeCommand::class,
            RequestMakeCommand::class,
            Console\RequestMakeCommand::class,
            ResourceMakeCommand::class,
            Console\ResourceMakeCommand::class,
            RuleMakeCommand::class,
            Console\RuleMakeCommand::class,
            ScopeMakeCommand::class,
            Console\ScopeMakeCommand::class,
            SeederMakeCommand::class,
            Console\SeederMakeCommand::class,
            TestMakeCommand::class,
            Console\TestMakeCommand::class,
            ViewMakeCommand::class,
            Console\ViewMakeCommand::class,

            BatchesTableCommand::class,
            Console\BatchesTableCommand::class,
            CacheTableCommand::class,
            Console\CacheTableCommand::class,
            FailedTableCommand::class,
            Console\FailedTableCommand::class,
            NotificationTableCommand::class,
            Console\NotificationTableCommand::class,
            QueueTableCommand::class,
            Console\QueueTableCommand::class,
            SessionTableCommand::class,
            Console\SessionTableCommand::class,

            Console\UserFactoryMakeCommand::class,
            Console\UserModelMakeCommand::class,
        ];
    }
}
