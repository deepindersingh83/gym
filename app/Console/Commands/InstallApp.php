<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PDO;
use RuntimeException;
use Throwable;

/**
 * First-time setup (also safe to re-run after an update): checks the
 * environment and database connection, migrates, seeds the software plans
 * and super admin, links storage, publishes assets and warms the caches.
 */
#[Signature('gym:install {--skip-cache : Do not build the config/route/view caches}')]
#[Description('Check the database connection and run every first-time setup step')]
class InstallApp extends Command
{
    public function handle(): int
    {
        $this->newLine();
        $this->line('  <fg=black;bg=bright-green> '.config('app.name').' installer </>');
        $this->newLine();

        // Start from a clean slate so stale cached config cannot hide .env changes.
        // (Not optimize:clear: clearing the app cache needs the database, which is checked next.)
        foreach (['config:clear', 'route:clear', 'view:clear', 'event:clear'] as $command) {
            $this->callSilently($command);
        }

        if (! $this->checkAppKey() || ! $this->checkWritable() || ! $this->checkDatabase()) {
            return self::FAILURE;
        }

        $firstInstall = ! Schema::hasTable('migrations');

        // Run visibly: migration errors and a generated admin password must reach the screen.
        $this->components->info('Creating / updating database tables');
        if ($this->call('migrate', ['--force' => true]) !== self::SUCCESS) {
            return self::FAILURE;
        }

        $this->components->info('Adding software plans and the super admin');
        if ($this->call('db:seed', ['--force' => true]) !== self::SUCCESS) {
            return self::FAILURE;
        }

        $this->step('Linking public storage (logos, member photos)', function () {
            if (! is_link(public_path('storage'))) {
                $this->callSilently('storage:link');
            }

            return is_link(public_path('storage')) || is_dir(public_path('storage'));
        });
        $this->step('Clearing the application cache', fn () => $this->callSilently('cache:clear') === 0);
        $this->step('Publishing panel assets', fn () => $this->callSilently('filament:assets') === 0);

        if (! $this->option('skip-cache') && app()->isProduction()) {
            $this->step('Building caches', fn () => $this->callSilently('optimize') === 0 && $this->callSilently('filament:optimize') === 0);
        }

        $this->summary($firstInstall);

        return self::SUCCESS;
    }

    protected function checkAppKey(): bool
    {
        if (filled(config('app.key'))) {
            $this->components->twoColumnDetail('Application key', '<fg=green>OK</>');

            return true;
        }

        $this->components->error('APP_KEY is empty. Run: php artisan key:generate');

        return false;
    }

    protected function checkWritable(): bool
    {
        $paths = [storage_path(), storage_path('framework/cache'), storage_path('framework/sessions'), storage_path('framework/views'), storage_path('logs'), base_path('bootstrap/cache')];
        $failed = [];

        foreach ($paths as $path) {
            if (! is_dir($path)) {
                @mkdir($path, 0775, true);
            }

            if (! is_writable($path)) {
                $failed[] = $path;
            }
        }

        if ($failed === []) {
            $this->components->twoColumnDetail('Folder permissions', '<fg=green>OK</>');

            return true;
        }

        $user = function_exists('posix_getpwuid') ? (posix_getpwuid(posix_geteuid())['name'] ?? 'this user') : 'this user';
        $this->components->error('These folders are not writable by '.$user.':');
        $this->components->bulletList($failed);
        $this->line("  Fix as root:  chown -R {$user}:{$user} ".base_path().' && chmod -R 775 storage bootstrap/cache');

        return false;
    }

    protected function checkDatabase(): bool
    {
        $connection = config('database.default');
        $settings = config("database.connections.{$connection}");

        if ($connection === 'sqlite' && ($settings['database'] ?? '') !== ':memory:' && ! file_exists($settings['database'])) {
            touch($settings['database']);
        }

        try {
            $pdo = DB::connection()->getPdo();
            $version = rescue(fn () => $pdo->getAttribute(PDO::ATTR_SERVER_VERSION), '', false);
            $this->components->twoColumnDetail(
                "Database connection ({$connection})",
                '<fg=green>OK</> '.trim(($settings['database'] ?? '').($version ? " · server {$version}" : '')),
            );

            return true;
        } catch (Throwable $e) {
            $this->components->error('Cannot connect to the database.');
            $this->components->twoColumnDetail('Connection', $connection);

            if ($connection !== 'sqlite') {
                $this->components->twoColumnDetail('Host : port', ($settings['host'] ?? '?').' : '.($settings['port'] ?? '?'));
                $this->components->twoColumnDetail('Database', (string) ($settings['database'] ?? ''));
                $this->components->twoColumnDetail('User', (string) ($settings['username'] ?? ''));
            }

            $this->components->twoColumnDetail('Error', $e->getMessage());
            $this->newLine();
            $this->line('  <options=bold>Common fixes</>');
            $this->components->bulletList($this->hintsFor($e->getMessage()));

            return false;
        }
    }

    /** @return list<string> */
    protected function hintsFor(string $message): array
    {
        return match (true) {
            str_contains($message, 'Access denied') => [
                'The DB_USERNAME / DB_PASSWORD in .env do not match. Check them in CloudPanel → Databases.',
                'Passwords with spaces or # must be in double quotes in .env: DB_PASSWORD="p@ss #1"',
            ],
            str_contains($message, 'Unknown database') => [
                'The database in DB_DATABASE does not exist. Create it in CloudPanel → Databases (same name).',
            ],
            str_contains($message, 'Connection refused'), str_contains($message, 'No such file'), str_contains($message, 'getaddrinfo') => [
                'MySQL is not reachable at DB_HOST/DB_PORT. On CloudPanel use DB_HOST=127.0.0.1 and DB_PORT=3306.',
            ],
            str_contains($message, 'could not find driver') => [
                'The PHP MySQL driver is missing. Install/enable the pdo_mysql extension for this PHP version.',
            ],
            default => [
                'Check DB_CONNECTION, DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME and DB_PASSWORD in .env.',
                'Then run this installer again.',
            ],
        };
    }

    protected function step(string $label, callable $task): void
    {
        $this->components->task($label, function () use ($task) {
            $ok = $task();

            if (! $ok) {
                throw new RuntimeException('Step failed: see the output above and storage/logs/laravel.log');
            }

            return true;
        });
    }

    protected function summary(bool $firstInstall): void
    {
        $email = strtolower((string) config('gym.super_admin.email'));
        $url = rtrim(config('app.url'), '/');

        $this->newLine();
        $this->components->info($firstInstall ? 'Installation complete.' : 'Update complete.');
        $this->components->twoColumnDetail('Website', $url);
        $this->components->twoColumnDetail('Super admin panel', "{$url}/admin");
        $this->components->twoColumnDetail('Super admin login', $email && User::query()->where('email', $email)->where('is_super_admin', true)->exists() ? $email : '<fg=yellow>not created (SUPER_ADMIN_EMAIL is empty)</>');
        $this->components->twoColumnDetail('Gym owners sign up at', "{$url}/app/register");
        $this->components->twoColumnDetail('Members log in at', "{$url}/member/login");

        if (! app()->isProduction() || config('app.debug')) {
            $this->newLine();
            $this->components->warn('APP_ENV is not "production" or APP_DEBUG is true. Set APP_ENV=production and APP_DEBUG=false on a live server.');
        }
    }
}
