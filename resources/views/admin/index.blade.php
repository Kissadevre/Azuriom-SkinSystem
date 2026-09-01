@extends('admin.layouts.admin')

@section('title', trans('skinsystem::admin.nav.settings'))

@push('styles')
    <link href="{{ plugin_asset('skinsystem', 'css/admin.css') }}" rel="stylesheet">
@endpush

@push('footer-scripts')
    <script src="{{ plugin_asset('skinsystem', 'js/admin.js') }}"></script>
@endpush

@section('content')
    @include('skinsystem::admin.partials.header', [
        'title' => trans('skinsystem::admin.nav.settings'),
        'description' => trans('skinsystem::admin.settings.description'),
    ])

    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card h-100">
                <div class="card-body d-flex align-items-center justify-content-between gap-3">
                    <div>
                        <div class="text-muted mb-1">{{ trans('skinsystem::admin.stats.total') }}</div>
                        <div class="fs-2 fw-semibold">{{ $totalSkins }}</div>
                    </div>
                    <i class="bi bi-people-fill fs-1 text-primary" aria-hidden="true"></i>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card h-100">
                <div class="card-body d-flex align-items-center justify-content-between gap-3">
                    <div>
                        <div class="text-muted mb-1">{{ trans('skinsystem::admin.stats.submitted') }}</div>
                        <div class="fs-2 fw-semibold">{{ $submittedSkins }}</div>
                    </div>
                    <i class="bi bi-send-check-fill fs-1 text-success" aria-hidden="true"></i>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card h-100">
                <div class="card-body d-flex align-items-center justify-content-between gap-3">
                    <div>
                        <div class="text-muted mb-1">{{ trans('skinsystem::admin.stats.attention') }}</div>
                        <div class="fs-2 fw-semibold">{{ $attentionSkins }}</div>
                    </div>
                    <i class="bi bi-exclamation-triangle-fill fs-1 text-warning" aria-hidden="true"></i>
                </div>
            </div>
        </div>
    </div>

    @if(!$httpsReady && $deliveryMode !== \Azuriom\Plugin\SkinSystem\Services\SkinSystemSettings::DELIVERY_MINESKIN)
        <div class="alert alert-danger" role="alert">
            <i class="bi bi-shield-exclamation me-2" aria-hidden="true"></i>
            {{ trans('skinsystem::admin.requirements.https_warning') }}
        </div>
    @endif

    @if($servers->isEmpty())
        <div class="alert alert-warning" role="alert">
            <i class="bi bi-hdd-network me-2" aria-hidden="true"></i>
            {{ trans('skinsystem::admin.settings.no_servers') }}
        </div>
    @endif

    <form method="POST" action="{{ route('skinsystem.admin.update') }}">
        @csrf
        @method('PUT')
        <input type="hidden" name="remove_mineskin_api_key" id="removeMineSkinApiKey" value="0">

        <section class="card skinsystem-admin-card mb-4">
            <div class="card-header skinsystem-admin-card-header">
                <span class="skinsystem-admin-icon text-info bg-info bg-opacity-10">
                    <i class="bi bi-signpost-split" aria-hidden="true"></i>
                </span>
                <div>
                    <h2 class="h5 mb-1">{{ trans('skinsystem::admin.delivery.title') }}</h2>
                    <p class="text-muted small mb-0">{{ trans('skinsystem::admin.delivery.description') }}</p>
                </div>
            </div>
            <div class="card-body p-4">
                @php
                    $deliveryModeIcons = [
                        'direct' => 'bi-link-45deg',
                        'mineskin' => 'bi-cloud-arrow-up',
                        'hybrid' => 'bi-intersect',
                    ];
                    $mineSkinKeyHasError = $errors->has('mineskin_api_key');
                @endphp

                <div class="skinsystem-section-heading">
                    <span class="skinsystem-section-label">{{ trans('skinsystem::admin.delivery.mode_heading') }}</span>
                    <span class="text-muted small">{{ trans('skinsystem::admin.delivery.mode_help') }}</span>
                </div>
                <div class="skinsystem-delivery-grid mb-4">
                    @foreach(\Azuriom\Plugin\SkinSystem\Services\SkinSystemSettings::deliveryModes() as $mode)
                        <label class="skinsystem-delivery-option">
                            <input class="visually-hidden"
                                   type="radio"
                                   name="delivery_mode"
                                   value="{{ $mode }}"
                                   @checked(old('delivery_mode', $deliveryMode) === $mode)>
                            <span class="skinsystem-delivery-icon" aria-hidden="true">
                                <i class="bi {{ $deliveryModeIcons[$mode] }}"></i>
                            </span>
                            <span class="skinsystem-delivery-copy">
                                <span class="d-flex align-items-center flex-wrap gap-2">
                                    <strong>{{ trans('skinsystem::admin.delivery.modes.'.$mode.'.title') }}</strong>
                                    @if($mode === 'hybrid')
                                        <span class="badge rounded-pill text-bg-primary">{{ trans('skinsystem::admin.delivery.recommended') }}</span>
                                    @endif
                                </span>
                                <small>{{ trans('skinsystem::admin.delivery.modes.'.$mode.'.description') }}</small>
                            </span>
                            <span class="skinsystem-delivery-check" aria-hidden="true"><i class="bi bi-check-lg"></i></span>
                        </label>
                    @endforeach
                </div>
                @error('delivery_mode')
                    <div class="text-danger small mb-3">{{ $message }}</div>
                @enderror

                <section class="skinsystem-mineskin-panel"
                         data-mineskin-integration
                         data-key-configured="{{ $hasMineSkinApiKey ? 'true' : 'false' }}"
                         data-editor-open="{{ $mineSkinKeyHasError || ! $hasMineSkinApiKey ? 'true' : 'false' }}">
                    <div class="skinsystem-mineskin-header">
                        <div class="d-flex align-items-center gap-3">
                            <span class="skinsystem-mineskin-mark" aria-hidden="true">
                                <i class="bi bi-cloud-check"></i>
                            </span>
                            <div>
                                <h3 class="h6 mb-1">{{ trans('skinsystem::admin.delivery.integration_title') }}</h3>
                                <p class="small text-muted mb-0">{{ trans('skinsystem::admin.delivery.integration_description') }}</p>
                            </div>
                        </div>
                        <span class="skinsystem-status-pill {{ $hasMineSkinApiKey ? 'is-connected' : 'is-missing' }}">
                            <span aria-hidden="true"></span>
                            {{ trans($hasMineSkinApiKey
                                ? 'skinsystem::admin.delivery.key_configured'
                                : 'skinsystem::admin.delivery.key_missing') }}
                        </span>
                    </div>

                    <div class="skinsystem-integration-body">
                        <div class="skinsystem-capability-grid">
                            <div class="skinsystem-capability {{ $hasMineSkinApiKey ? 'is-success' : 'is-muted' }}">
                                <span class="skinsystem-capability-icon" aria-hidden="true">
                                    <i class="bi {{ $hasMineSkinApiKey ? 'bi-shield-check' : 'bi-shield-lock' }}"></i>
                                </span>
                                <span>
                                    <small>{{ trans('skinsystem::admin.delivery.connection_title') }}</small>
                                    <strong>{{ trans($hasMineSkinApiKey
                                        ? 'skinsystem::admin.delivery.connection_verified'
                                        : 'skinsystem::admin.delivery.connection_missing') }}</strong>
                                </span>
                            </div>

                            <div class="skinsystem-capability {{ ! $hasMineSkinApiKey ? 'is-muted' : ($mineSkinCapesGranted ? 'is-success' : 'is-warning') }}">
                                <span class="skinsystem-capability-icon" aria-hidden="true">
                                    <i class="bi {{ $mineSkinCapesGranted ? 'bi-person-badge' : 'bi-person-x' }}"></i>
                                </span>
                                <span>
                                    <small>{{ trans('skinsystem::admin.delivery.cape_access_title') }}</small>
                                    <strong>{{ trans(! $hasMineSkinApiKey
                                        ? 'skinsystem::admin.delivery.connection_missing'
                                        : ($mineSkinCapesGranted
                                            ? 'skinsystem::admin.delivery.capes_available_short'
                                            : 'skinsystem::admin.delivery.capes_unavailable_short')) }}</strong>
                                </span>
                            </div>
                        </div>

                        <div class="skinsystem-key-editor" data-mineskin-key-editor @if($hasMineSkinApiKey && ! $mineSkinKeyHasError) hidden @endif>
                            <label class="form-label fw-semibold" for="mineSkinApiKey">
                                {{ trans('skinsystem::admin.delivery.api_key') }}
                            </label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-key" aria-hidden="true"></i></span>
                                <input class="form-control @error('mineskin_api_key') is-invalid @enderror"
                                       type="password"
                                       id="mineSkinApiKey"
                                       name="mineskin_api_key"
                                       value=""
                                       maxlength="512"
                                       autocomplete="new-password"
                                       placeholder="{{ $hasMineSkinApiKey ? trans('skinsystem::admin.delivery.key_keep_placeholder') : trans('skinsystem::admin.delivery.key_placeholder') }}"
                                       aria-describedby="mineSkinApiKeyHelp">
                                <button class="btn btn-outline-secondary"
                                        type="button"
                                        data-mineskin-key-visibility
                                        data-show-label="{{ trans('skinsystem::admin.delivery.show_key') }}"
                                        data-hide-label="{{ trans('skinsystem::admin.delivery.hide_key') }}"
                                        aria-label="{{ trans('skinsystem::admin.delivery.show_key') }}"
                                        title="{{ trans('skinsystem::admin.delivery.show_key') }}">
                                    <i class="bi bi-eye" aria-hidden="true"></i>
                                </button>
                                @error('mineskin_api_key')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="form-text" id="mineSkinApiKeyHelp">
                                <i class="bi bi-lock me-1" aria-hidden="true"></i>
                                {{ trans('skinsystem::admin.delivery.api_key_help') }}
                            </div>
                            @if($hasMineSkinApiKey)
                                <button class="btn btn-sm btn-link px-0 mt-2" type="button" data-mineskin-cancel-edit>
                                    {{ trans('skinsystem::admin.delivery.cancel_replace') }}
                                </button>
                            @endif
                        </div>

                        @if($hasMineSkinApiKey)
                            <div class="skinsystem-key-actions" data-mineskin-key-actions>
                                <button class="btn btn-outline-primary" type="button" data-mineskin-replace>
                                    <i class="bi bi-arrow-repeat me-1" aria-hidden="true"></i>
                                    {{ trans('skinsystem::admin.delivery.replace_key') }}
                                </button>
                                <button class="btn btn-outline-danger" type="button" data-mineskin-remove>
                                    <i class="bi bi-trash3 me-1" aria-hidden="true"></i>
                                    {{ trans('skinsystem::admin.delivery.remove_key') }}
                                </button>
                            </div>

                            <div class="skinsystem-removal-notice" data-mineskin-removal-notice hidden>
                                <span class="skinsystem-removal-icon" aria-hidden="true"><i class="bi bi-exclamation-triangle"></i></span>
                                <span class="flex-grow-1">
                                    <strong>{{ trans('skinsystem::admin.delivery.remove_pending_title') }}</strong>
                                    <small>{{ trans('skinsystem::admin.delivery.remove_pending') }}</small>
                                </span>
                                <button class="btn btn-sm btn-outline-danger" type="button" data-mineskin-cancel-removal>
                                    {{ trans('skinsystem::admin.delivery.undo_remove') }}
                                </button>
                            </div>
                        @endif
                    </div>
                </section>
            </div>
        </section>

        <div class="row g-4 align-items-stretch mb-4">
            <div class="col-xl-7">
                <section class="card h-100 skinsystem-admin-card">
                    <div class="card-header skinsystem-admin-card-header">
                        <span class="skinsystem-admin-icon text-primary bg-primary bg-opacity-10">
                            <i class="bi bi-arrow-repeat" aria-hidden="true"></i>
                        </span>
                        <div>
                            <h2 class="h5 mb-1">{{ trans('skinsystem::admin.settings.title') }}</h2>
                            <p class="text-muted small mb-0">{{ trans('skinsystem::admin.settings.sync_description') }}</p>
                        </div>
                    </div>
                    <div class="card-body p-4">
                        <div class="skinsystem-setting-row mb-4">
                            <label for="syncEnabled" class="mb-0">
                                <span class="d-block fw-semibold">{{ trans('skinsystem::admin.settings.enabled') }}</span>
                                <small class="text-muted">{{ trans('skinsystem::admin.settings.enabled_help') }}</small>
                            </label>
                            <input type="hidden" name="sync_enabled" value="0">
                            <div class="form-check form-switch fs-4 mb-0">
                                <input class="form-check-input @error('sync_enabled') is-invalid @enderror"
                                       type="checkbox"
                                       name="sync_enabled"
                                       value="1"
                                       id="syncEnabled"
                                       @checked(old('sync_enabled', $syncEnabled))>
                            </div>
                        </div>

                        <div>
                            <label class="form-label fw-semibold" for="serverId">{{ trans('skinsystem::admin.settings.server') }}</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-hdd-network" aria-hidden="true"></i></span>
                                <select class="form-select @error('server_id') is-invalid @enderror" id="serverId" name="server_id">
                                    <option value="">{{ trans('skinsystem::admin.settings.select_server') }}</option>
                                    @foreach($servers as $server)
                                        <option value="{{ $server->id }}" @selected((int) old('server_id', $serverId) === $server->id)>
                                            {{ $server->name }} — {{ trans('skinsystem::admin.server_types.'.$server->type) }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('server_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="form-text">{{ trans('skinsystem::admin.settings.server_help') }}</div>
                        </div>

                        <div class="mt-4">
                            <div class="skinsystem-section-heading">
                                <span class="skinsystem-section-label">{{ trans('skinsystem::admin.settings.application_target') }}</span>
                                <span class="text-muted small">{{ trans('skinsystem::admin.settings.application_target_help') }}</span>
                            </div>
                            <div class="skinsystem-target-grid">
                                @php
                                    $applicationTargetIcons = [
                                        'uuid' => 'bi-fingerprint',
                                        'username' => 'bi-person-badge',
                                    ];
                                @endphp
                                @foreach(\Azuriom\Plugin\SkinSystem\Services\SkinSystemSettings::applicationTargets() as $target)
                                    <label class="skinsystem-delivery-option skinsystem-target-option">
                                        <input class="visually-hidden"
                                               type="radio"
                                               name="application_target"
                                               value="{{ $target }}"
                                               @checked(old('application_target', $applicationTarget) === $target)>
                                        <span class="skinsystem-delivery-icon" aria-hidden="true">
                                            <i class="bi {{ $applicationTargetIcons[$target] }}"></i>
                                        </span>
                                        <span class="skinsystem-delivery-copy">
                                            <strong>{{ trans('skinsystem::admin.settings.application_targets.'.$target.'.title') }}</strong>
                                            <small>{{ trans('skinsystem::admin.settings.application_targets.'.$target.'.description') }}</small>
                                        </span>
                                        <span class="skinsystem-delivery-check" aria-hidden="true"><i class="bi bi-check-lg"></i></span>
                                    </label>
                                @endforeach
                            </div>
                            @error('application_target')
                                <div class="text-danger small mt-2">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </section>
            </div>

            <div class="col-xl-5">
                <section class="card h-100 skinsystem-admin-card">
                    <div class="card-header skinsystem-admin-card-header">
                        <span class="skinsystem-admin-icon text-success bg-success bg-opacity-10">
                            <i class="bi bi-collection" aria-hidden="true"></i>
                        </span>
                        <div>
                            <h2 class="h5 mb-1">{{ trans('skinsystem::admin.settings.library_title') }}</h2>
                            <p class="text-muted small mb-0">{{ trans('skinsystem::admin.settings.library_description') }}</p>
                        </div>
                    </div>
                    <div class="card-body p-4">
                        <div class="mb-4">
                            <label class="form-label fw-semibold" for="libraryLimit">{{ trans('skinsystem::admin.settings.library_limit') }}</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-person" aria-hidden="true"></i></span>
                                <input class="form-control @error('library_limit') is-invalid @enderror"
                                       type="text"
                                       id="libraryLimit"
                                       name="library_limit"
                                       inputmode="numeric"
                                       pattern="[0-9]+"
                                       autocomplete="off"
                                       data-integer-only
                                       data-minimum="1"
                                       data-minimum-message="{{ trans('skinsystem::admin.validation.library_limit_min') }}"
                                       data-maximum="{{ \Azuriom\Plugin\SkinSystem\Services\SkinSystemSettings::MAX_LIBRARY_LIMIT }}"
                                       data-maximum-message="{{ trans('skinsystem::admin.validation.library_limit_max') }}"
                                       value="{{ old('library_limit', $libraryLimit) }}"
                                       aria-describedby="libraryLimitHelp"
                                       required>
                                <span class="input-group-text">{{ trans('skinsystem::admin.settings.skins_unit') }}</span>
                                @error('library_limit')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="form-text" id="libraryLimitHelp">{{ trans('skinsystem::admin.settings.library_limit_help') }}</div>
                        </div>

                        <div>
                            <label class="form-label fw-semibold" for="publicEndpoint">{{ trans('skinsystem::admin.settings.endpoint') }}</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-link-45deg" aria-hidden="true"></i></span>
                                <input class="form-control font-monospace" id="publicEndpoint" value="{{ $publicEndpoint }}" readonly>
                            </div>
                            <div class="form-text">{{ trans('skinsystem::admin.settings.endpoint_help') }}</div>
                        </div>
                    </div>
                </section>
            </div>
        </div>

        @php
            $userMenuIconValue = old('user_menu_icon', $userMenuIcon);
            $userMenuIconPreview = is_string($userMenuIconValue)
                && preg_match('/^bi-[a-z0-9]+(?:-[a-z0-9]+)*$/D', $userMenuIconValue) === 1
                    ? $userMenuIconValue
                    : \Azuriom\Plugin\SkinSystem\Services\SkinSystemSettings::DEFAULT_USER_MENU_ICON;
        @endphp
        <section class="card skinsystem-admin-card mb-4">
            <div class="card-header skinsystem-admin-card-header">
                <span class="skinsystem-admin-icon text-info bg-info bg-opacity-10">
                    <i class="bi bi-person-lines-fill" aria-hidden="true"></i>
                </span>
                <div>
                    <h2 class="h5 mb-1">{{ trans('skinsystem::admin.settings.user_menu_title') }}</h2>
                    <p class="text-muted small mb-0">{{ trans('skinsystem::admin.settings.user_menu_description') }}</p>
                </div>
            </div>
            <div class="card-body p-4">
                <div class="row g-4 align-items-stretch">
                    <div class="col-lg-7">
                        <div class="skinsystem-setting-row h-100">
                            <label for="userMenuEnabled" class="mb-0">
                                <span class="d-block fw-semibold">{{ trans('skinsystem::admin.settings.user_menu_enabled') }}</span>
                                <small class="text-muted">{{ trans('skinsystem::admin.settings.user_menu_enabled_help') }}</small>
                            </label>
                            <input type="hidden" name="user_menu_enabled" value="0">
                            <div class="form-check form-switch fs-4 mb-0">
                                <input class="form-check-input @error('user_menu_enabled') is-invalid @enderror"
                                       type="checkbox"
                                       name="user_menu_enabled"
                                       value="1"
                                       id="userMenuEnabled"
                                       @checked(old('user_menu_enabled', $showInUserMenu))>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-5">
                        <label class="form-label fw-semibold" for="userMenuIcon">
                            {{ trans('skinsystem::admin.settings.user_menu_icon') }}
                        </label>
                        <div class="input-group @error('user_menu_icon') has-validation @enderror">
                            <span class="input-group-text skinsystem-icon-preview" aria-hidden="true">
                                <i class="bi {{ $userMenuIconPreview }}" data-user-menu-icon-preview></i>
                            </span>
                            <input class="form-control font-monospace @error('user_menu_icon') is-invalid @enderror"
                                   type="text"
                                   id="userMenuIcon"
                                   name="user_menu_icon"
                                   value="{{ $userMenuIconValue }}"
                                   maxlength="64"
                                   pattern="bi-[a-z0-9]+(?:-[a-z0-9]+)*"
                                   placeholder="{{ \Azuriom\Plugin\SkinSystem\Services\SkinSystemSettings::DEFAULT_USER_MENU_ICON }}"
                                   autocomplete="off"
                                   data-user-menu-icon
                                   data-default-icon="{{ \Azuriom\Plugin\SkinSystem\Services\SkinSystemSettings::DEFAULT_USER_MENU_ICON }}"
                                   aria-describedby="userMenuIconHelp"
                                   required>
                            @error('user_menu_icon')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="form-text" id="userMenuIconHelp">
                            {!! trans('skinsystem::admin.settings.user_menu_icon_help') !!}
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <div class="d-flex justify-content-end mb-4">
            <button type="submit" class="btn btn-primary btn-lg px-4">
                <i class="bi bi-check-lg me-1" aria-hidden="true"></i>
                {{ trans('messages.actions.save') }}
            </button>
        </div>
    </form>

@endsection
