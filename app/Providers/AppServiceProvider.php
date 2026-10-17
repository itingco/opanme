<?php

namespace App\Providers;

use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
    }

    public function boot(): void
    {
        // Fix oversized, unstyled Laravel/Tailwind pagination ONLY on
        // Sampling Gudang and History Sampling screens. Other modules
        // keep their previous pagination renderer and behavior.
        View::composer([
            'warehouse_sampling.admin.index',
            'warehouse_sampling.history.compact',
            'warehouse_sampling.history.index',
        ], static function (): void {
            Paginator::defaultView('pagination.warehouse-sampling');
        });
    }
}
