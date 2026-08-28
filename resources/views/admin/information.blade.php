@extends('admin.layouts.admin')

@section('title', trans('skinsystem::admin.nav.information'))

@push('styles')
    <link href="{{ plugin_asset('skinsystem', 'css/admin.css') }}" rel="stylesheet">
@endpush

@section('content')
    @include('skinsystem::admin.partials.header', [
        'title' => trans('skinsystem::admin.nav.information'),
        'description' => trans('skinsystem::admin.information.description'),
    ])

    <section class="card skinsystem-admin-card mb-4">
        <div class="card-header skinsystem-admin-card-header">
            <span class="skinsystem-admin-icon text-primary bg-primary bg-opacity-10">
                <i class="bi bi-list-check" aria-hidden="true"></i>
            </span>
            <div>
                <h2 class="h5 mb-1">{{ trans('skinsystem::admin.requirements.title') }}</h2>
                <p class="text-muted small mb-0">{{ trans('skinsystem::admin.requirements.description') }}</p>
            </div>
        </div>
        <div class="card-body p-4">
            <div class="row g-3">
                <div class="col-lg-4">
                    <div class="skinsystem-guide h-100">
                        <span class="skinsystem-guide-icon text-primary"><i class="bi bi-server" aria-hidden="true"></i></span>
                        <h3 class="h6">{{ trans('skinsystem::admin.requirements.server_title') }}</h3>
                        <ul class="skinsystem-checklist mb-0">
                            <li>{{ trans('skinsystem::admin.requirements.skinsrestorer') }}</li>
                            <li>{{ trans('skinsystem::admin.requirements.bridge') }}</li>
                            <li>{{ trans('skinsystem::admin.requirements.uuid') }}</li>
                            <li>{{ trans('skinsystem::admin.requirements.proxy') }}</li>
                        </ul>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="skinsystem-guide h-100">
                        <span class="skinsystem-guide-icon text-success"><i class="bi bi-globe2" aria-hidden="true"></i></span>
                        <h3 class="h6">{{ trans('skinsystem::admin.requirements.delivery_title') }}</h3>
                        <ul class="skinsystem-checklist mb-0">
                            <li>{{ trans('skinsystem::admin.requirements.public_url') }}</li>
                            <li>{{ trans('skinsystem::admin.requirements.url_allowlist') }}</li>
                        </ul>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="skinsystem-guide h-100">
                        <span class="skinsystem-guide-icon text-warning"><i class="bi bi-shield-check" aria-hidden="true"></i></span>
                        <h3 class="h6">{{ trans('skinsystem::admin.requirements.operation_title') }}</h3>
                        <ul class="skinsystem-checklist mb-0">
                            <li>{{ trans('skinsystem::admin.requirements.skin_api') }}</li>
                            <li>{{ trans('skinsystem::admin.requirements.cache_lock') }}</li>
                            <li>{{ trans('skinsystem::admin.requirements.scheduler') }}</li>
                        </ul>
                    </div>
                </div>
            </div>

            <div class="alert alert-info mt-4 mb-0" role="note">
                <i class="bi bi-info-circle me-2" aria-hidden="true"></i>
                {{ trans('skinsystem::admin.requirements.submitted_semantics') }}
            </div>
        </div>
    </section>

    @php
        $credits = [
            ['key' => 'azuriom', 'icon' => 'bi-window', 'url' => 'https://github.com/Azuriom/Azuriom'],
            ['key' => 'laravel', 'icon' => 'bi-code-slash', 'url' => 'https://laravel.com'],
            ['key' => 'bootstrap', 'icon' => 'bi-bootstrap', 'url' => 'https://getbootstrap.com'],
            ['key' => 'skinview3d', 'icon' => 'bi-person-bounding-box', 'url' => 'https://github.com/bs-community/skinview3d'],
            ['key' => 'skinsrestorer', 'icon' => 'bi-controller', 'url' => 'https://github.com/SkinsRestorer/SkinsRestorer'],
            ['key' => 'mineskin', 'icon' => 'bi-wind', 'url' => 'https://mineskin.org'],
            ['key' => 'azlink', 'icon' => 'bi-hdd-network', 'url' => 'https://github.com/Azuriom/AzLink'],
            ['key' => 'original', 'icon' => 'bi-git', 'url' => 'https://github.com/SkinsRestorer/SkinSystem'],
            ['key' => 'skin_api', 'icon' => 'bi-plug', 'url' => 'https://github.com/AzuriomCommunity/Plugin-SkinAPI'],
            ['key' => 'skin3d_plugin', 'icon' => 'bi-box', 'url' => 'https://github.com/vexato/skin3d-viewer'],
        ];
    @endphp

    <section class="card skinsystem-admin-card">
        <div class="card-header skinsystem-admin-card-header">
            <span class="skinsystem-admin-icon text-danger bg-danger bg-opacity-10">
                <i class="bi bi-heart" aria-hidden="true"></i>
            </span>
            <div>
                <h2 class="h5 mb-1">{{ trans('skinsystem::admin.credits.title') }}</h2>
                <p class="text-muted small mb-0">{{ trans('skinsystem::admin.credits.description') }}</p>
            </div>
        </div>
        <div class="card-body p-4">
            <div class="skinsystem-credits-grid">
                @foreach($credits as $credit)
                    <a class="skinsystem-credit" href="{{ $credit['url'] }}" target="_blank" rel="noopener noreferrer">
                        <span class="skinsystem-credit-icon"><i class="bi {{ $credit['icon'] }}" aria-hidden="true"></i></span>
                        <span>
                            <strong>{{ trans('skinsystem::admin.credits.'.$credit['key'].'.name') }}</strong>
                            <small>{{ trans('skinsystem::admin.credits.'.$credit['key'].'.role') }}</small>
                        </span>
                        <i class="bi bi-box-arrow-up-right skinsystem-credit-link" aria-hidden="true"></i>
                    </a>
                @endforeach
            </div>

            <p class="small text-muted mt-4 mb-0">{{ trans('skinsystem::admin.credits.disclaimer') }}</p>
        </div>
    </section>
@endsection
