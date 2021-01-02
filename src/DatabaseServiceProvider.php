<?php
declare(strict_types=1);
namespace Zodream\Database;

use Zodream\Infrastructure\Contracts\Database;
use Zodream\Infrastructure\Support\ServiceProvider;

class DatabaseServiceProvider extends ServiceProvider {

    public function register()
    {
        $this->app->scopedIf(Database::class, Command::class);
        $this->app->alias(Database::class, 'db');
    }
}