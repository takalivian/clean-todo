<?php

namespace App\Providers;

use App\Domain\Task\Repositories\TaskRepositoryInterface;
use App\Domain\Tag\Repositories\TagRepositoryInterface;
use App\Infrastructure\Task\Repositories\EloquentTaskRepository;
use App\Infrastructure\Tag\Repositories\EloquentTagRepository;
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

        // TagRepositoryのバインディング
        $this->app->bind(
            TagRepositoryInterface::class,
            EloquentTagRepository::class
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
