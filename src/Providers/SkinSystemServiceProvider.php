<?php

namespace Azuriom\Plugin\SkinSystem\Providers;

use Azuriom\Extensions\Plugin\BasePluginServiceProvider;
use Azuriom\Models\Permission;
use Azuriom\Models\Setting;
use Azuriom\Plugin\SkinSystem\Commands\CleanupSkinRevisions;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class SkinSystemServiceProvider extends BasePluginServiceProvider
{
    /**
     * Register secrets before any setting value can be hydrated from storage.
     */
    public function register(): void
    {
        Setting::markAsEncrypted(SkinSystemSettings::MINESKIN_API_KEY_KEY);
    }

    /**
     * Bootstrap the plugin services.
     */
    public function boot(): void
    {
        $this->loadViews();
        $this->loadTranslations();
        $this->loadMigrations();
        $this->registerRouteDescriptions();
        $this->registerAdminNavigation();
        $this->registerUserNavigation();

        if (method_exists($this, 'registerSchedule')) {
            $this->registerSchedule();
        }

        $this->commands(CleanupSkinRevisions::class);

        RateLimiter::for('skinsystem.images', function (Request $request) {
            return Limit::perMinute(300)->by($request->ip());
        });

        Permission::registerPermissions([
            'skinsystem.skin' => 'skinsystem::admin.permissions.skin',
            'skinsystem.library' => 'skinsystem::admin.permissions.library',
            'skinsystem.cape' => 'skinsystem::admin.permissions.cape',
            'skinsystem.admin' => 'skinsystem::admin.permissions.admin',
        ]);
    }

    /**
     * Remove expired immutable revisions and orphaned PNG blobs every day.
     */
    protected function schedule(Schedule $schedule): void
    {
        $schedule->command('skinsystem:cleanup')->daily()->withoutOverlapping(15);
    }

    /**
     * Return the routes that can be added to the site navigation.
     *
     * @return array<string, string>
     */
    protected function routeDescriptions(): array
    {
        return [
            'skinsystem.index' => trans('skinsystem::messages.title'),
        ];
    }

    /**
     * Return the plugin entry for the administration navigation.
     *
     * @return array<string, array<string, mixed>>
     */
    protected function adminNavigation(): array
    {
        return [
            'skinsystem' => [
                'name' => trans('skinsystem::admin.title'),
                'type' => 'dropdown',
                'icon' => 'bi bi-person-bounding-box',
                'permission' => 'skinsystem.admin',
                'route' => 'skinsystem.admin.*',
                'items' => [
                    'skinsystem.admin.index' => trans('skinsystem::admin.nav.settings'),
                    'skinsystem.admin.information' => trans('skinsystem::admin.nav.information'),
                ],
            ],
        ];
    }

    /**
     * Return the plugin entry for the authenticated user menu.
     *
     * @return array<string, array<string, string>>
     */
    protected function userNavigation(): array
    {
        return [
            'skinsystem' => [
                'route' => 'skinsystem.index',
                'name' => trans('skinsystem::messages.title'),
                'permission' => 'skinsystem.skin',
                'icon' => 'bi bi-person-bounding-box',
            ],
        ];
    }
}
