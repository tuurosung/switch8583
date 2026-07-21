<?php

declare(strict_types=1);

namespace Tuurosung\switch8583\Laravel;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Tuurosung\switch8583\Iso8583Factory;


/**
 * The optional Laravel integration.
 *
 * This is the only file in the package that touches the framework, and it is
 * intentionally thin: merge the default config, bind a configured
 * {@see Iso8583Factory} as a singleton, and expose the config for publishing.
 * The package's `illuminate/*` requirements live under "suggest"/dev only, so
 * a non-Laravel consumer never loads this class or pulls the framework.
 *
 * Auto-discovery: reference this provider under the "extra.laravel.providers"
 * key of composer.json so Laravel registers it automatically.
 */
final class Iso8583ServiceProvider extends ServiceProvider
{
    private const CONFIG_PATH = __DIR__ . '/../../config/iso8583.php';

    public function register(): void
    {
        $this->mergeConfigFrom(self::CONFIG_PATH, 'iso8583');

        $this->app->singleton(Iso8583Factory::class, static function (Application $app): Iso8583Factory {
            /** @var array{version?: string, mti_encoding?: string} $config */
            $config = $app['config']->get('iso8583', []);

            return Iso8583Factory::for(
                $config['version'] ?? '1987',
                $config['mti_encoding'] ?? 'ascii',
            );
        });
    }


    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes(
                [self::CONFIG_PATH => $this->app->configPath('iso8583.php')],
                'iso8583-config',
            );
        }
    }


    /**
     * @return array<int, class-string>
     */
    public function provides(): array
    {
        return [Iso8583Factory::class];
    }
}