<?php

namespace App\Providers;

use App\Support\CurrentTenant;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Scoped prevents tenant context leaking between requests in long-lived workers.
        $this->app->scoped(CurrentTenant::class);
    }

    public function boot(): void
    {
        //
    }
}
