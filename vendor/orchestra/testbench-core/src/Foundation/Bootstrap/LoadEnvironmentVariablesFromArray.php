<?php

namespace Orchestra\Testbench\Foundation\Bootstrap;

use Dotenv\Parser\Entry;
use Dotenv\Parser\Parser;
use Dotenv\Store\StringStore;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Collection;
use Orchestra\Sidekick\Env;

/**
 * @internal
 */
final class LoadEnvironmentVariablesFromArray
{
    /**
     * Construct a new Create Vendor Symlink bootstrapper.
     *
     * @param  array<int, mixed>  $environmentVariables
     */
    public function __construct(
        public readonly array $environmentVariables
    ) {
        //
    }

    /**
     * Bootstrap the given application.
     *
     * @param  \Illuminate\Contracts\Foundation\Application  $app
     * @return void
     */
    public function bootstrap(Application $app): void
    {
        $store = new StringStore(implode(PHP_EOL, $this->environmentVariables));
        $parser = new Parser;

        (new Collection($parser->parse($store->read())))
            ->filter(static fn (Entry $entry) => $entry->getValue()->isDefined())
            ->each(static function (Entry $entry) {
                /** @var \Dotenv\Parser\Entry $entry */
                Env::set($entry->getName(), $entry->getValue()->get()->getChars());
            });
    }
}
