<?php

namespace App\Providers;

use App\Models\Groupe;
use App\Models\Seance;
use App\Observers\SeanceObserver;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Route::bind('group', fn (string $value) => Groupe::findOrFail($value));
        Seance::observe(SeanceObserver::class);
    }
}
