<?php

declare(strict_types=1);

namespace JackWH\LaravelNewRelic;

use Illuminate\Support\ServiceProvider;
use JackWH\LaravelNewRelic\Commands\NewRelicDeployCommand;

class LaravelNewRelicServiceProvider extends ServiceProvider
{
    /**
     * Register the New Relic Service Provider.
     */
    public function register(): void
    {
        // Load in the package and user configurations
        $this->mergeConfigFrom(
            __DIR__ . '/../config/new-relic.php',
            'new-relic'
        );

        // Bind the transaction and handler classes to the container.
        // We bind them as scoped singletons, meaning they will be
        // automatically reset at the end of each lifecycle request.
        $this->app->scoped(NewRelicTransaction::class, static fn ($app): NewRelicTransaction => new NewRelicTransaction());
        $this->app->scoped(NewRelicTransactionHandler::class, static fn ($app): NewRelicTransactionHandler => new NewRelicTransactionHandler());

        // For CLI processes, check if we should ignore early (before boot)
        // This helps catch very fast processes like schedule:finish
        $this->earlyCliIgnore();
    }

    /**
     * For CLI processes, ignore transactions early before boot completes.
     * This catches very fast processes that would exit before boot().
     */
    protected function earlyCliIgnore(): void
    {
        if (!$this->app->runningInConsole()) {
            return;
        }

        if (!NewRelicTransactionHandler::newRelicEnabled()) {
            return;
        }

        $argv = $_SERVER['argv'] ?? [];
        $commandName = $argv[1] ?? '';

        if ($commandName === '' || $commandName === 'artisan') {
            newrelic_ignore_transaction();

            return;
        }

        $ignoreList = config('new-relic.artisan.ignore', []);
        foreach ($ignoreList as $pattern) {
            if (\Illuminate\Support\Str::is($pattern, $commandName)) {
                newrelic_ignore_transaction();

                return;
            }
        }
    }

    /**
     * Boot the New Relic Service Provider.
     */
    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/new-relic.php' => config_path('new-relic.php'),
        ]);

        if ($this->app->runningInConsole()) {
            // Register the new-relic:deploy command in the console
            $this->commands([NewRelicDeployCommand::class]);
        }

        if (app(NewRelicTransactionHandler::class)::newRelicEnabled()) {
            app(NewRelicTransactionHandler::class)->configureNewRelic();
        }
    }
}
