<?php

namespace App\Providers;

use App\Models\Shared\Contract;
use App\Observers\ContractObserver;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Contract::observe(ContractObserver::class);
    }
}
