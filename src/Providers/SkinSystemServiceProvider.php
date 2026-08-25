<?php

namespace Azuriom\Plugin\SkinSystem\Providers;

use Azuriom\Extensions\Plugin\BasePluginServiceProvider;
use Azuriom\Models\Permission;

class SkinSystemServiceProvider extends BasePluginServiceProvider
{
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

        Permission::registerPermissions([
            'skinsystem.skin' => 'skinsystem::admin.permissions.skin',
            'skinsystem.admin' => 'skinsystem::admin.permissions.admin',
        ]);
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
     * @return array<string, array<string, string>>
     */
    protected function adminNavigation(): array
    {
        return [
            'skinsystem' => [
                'name' => trans('skinsystem::admin.title'),
                'icon' => 'bi bi-person-bounding-box',
                'permission' => 'skinsystem.admin',
                'route' => 'skinsystem.admin.index',
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

