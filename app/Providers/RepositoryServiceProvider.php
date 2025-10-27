<?php

namespace App\Providers;

use App\Domain\Task\Repositories\TaskRepositoryInterface;
use App\Infrastructure\Task\Repositories\EloquentTaskRepository;
use Illuminate\Support\ServiceProvider;

class RepositoryServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // TaskRepositoryのバインディング
        $this->app->bind(
            TaskRepositoryInterface::class,
            EloquentTaskRepository::class
        );
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
