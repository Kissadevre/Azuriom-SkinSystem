<?php

namespace Azuriom\Plugin\SkinSystem\Providers;

use Azuriom\Extensions\Plugin\BaseRouteServiceProvider;
use Azuriom\Plugin\SkinSystem\Http\Middleware\LogSkinSystemRequest;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends BaseRouteServiceProvider
{
    /**
     * Define the plugin routes.
     */
    public function loadRoutes(): void
    {
        Route::middleware(['web', LogSkinSystemRequest::class])
            ->prefix($this->plugin->id)
            ->name($this->plugin->id.'.')
            ->group(plugin_path($this->plugin->id.'/routes/web.php'));

        Route::middleware(['admin-access', LogSkinSystemRequest::class])
            ->prefix('admin/'.$this->plugin->id)
            ->name($this->plugin->id.'.admin.')
            ->group(plugin_path($this->plugin->id.'/routes/admin.php'));

        Route::middleware([SubstituteBindings::class, 'throttle:skinsystem.images', LogSkinSystemRequest::class])
            ->prefix('api/'.$this->plugin->id)
            ->name($this->plugin->id.'.api.')
            ->group(plugin_path($this->plugin->id.'/routes/api.php'));
    }
}
