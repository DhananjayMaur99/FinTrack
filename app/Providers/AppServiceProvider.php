<?php

namespace App\Providers;

use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // The layout's quick-add modal needs the signed-in user's reasons on every page.
        View::composer('components.app-layout', function ($view) {
            $view->with('quickReasons', auth()->check()
                ? auth()->user()->categories()->orderBy('name')->get()
                : collect());
        });
    }
}
