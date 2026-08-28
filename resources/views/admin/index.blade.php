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

    @unless($httpsReady)
        <div class="alert alert-danger" role="alert">
            <i class="bi bi-shield-exclamation me-2" aria-hidden="true"></i>
            {{ trans('skinsystem::admin.requirements.https_warning') }}
        </div>
    @endunless

    @if($servers->isEmpty())
        <div class="alert alert-warning" role="alert">
            <i class="bi bi-hdd-network me-2" aria-hidden="true"></i>
            {{ trans('skinsystem::admin.settings.no_servers') }}
        </div>
    @endif

    <form method="POST" action="{{ route('skinsystem.admin.update') }}">
        @csrf
        @method('PUT')
        <input type="hidden" name="remove_mineskin_api_key" value="0">

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
                <div class="skinsystem-delivery-grid mb-4">
                    @foreach(\Azuriom\Plugin\SkinSystem\Services\SkinSystemSettings::deliveryModes() as $mode)
                        <label class="skinsystem-delivery-option">
                            <input class="form-check-input"
                                   type="radio"
                                   name="delivery_mode"
                                   value="{{ $mode }}"
                                   @checked(old('delivery_mode', $deliveryMode) === $mode)>
                            <span>
                                <strong>{{ trans('skinsystem::admin.delivery.modes.'.$mode.'.title') }}</strong>
                                <small>{{ trans('skinsystem::admin.delivery.modes.'.$mode.'.description') }}</small>
                            </span>
                        </label>
                    @endforeach
                </div>
                @error('delivery_mode')
                    <div class="text-danger small mb-3">{{ $message }}</div>
                @enderror

                <div class="row g-3 align-items-end">
                    <div class="col-lg-8">
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <label class="form-label fw-semibold mb-0" for="mineSkinApiKey">
                                {{ trans('skinsystem::admin.delivery.api_key') }}
                            </label>
                            @if($hasMineSkinApiKey)
                                <span class="badge text-bg-success">{{ trans('skinsystem::admin.delivery.key_configured') }}</span>
                            @else
                                <span class="badge text-bg-secondary">{{ trans('skinsystem::admin.delivery.key_missing') }}</span>
                            @endif
                        </div>
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
                            @error('mineskin_api_key')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="form-text" id="mineSkinApiKeyHelp">
                            {{ trans('skinsystem::admin.delivery.api_key_help') }}
                        </div>
                    </div>
                    <div class="col-lg-4">
                        @if($hasMineSkinApiKey)
                            <div class="d-flex flex-column gap-2">
                                <div class="small {{ $mineSkinCapesGranted ? 'text-success' : 'text-warning' }}">
                                    <i class="bi {{ $mineSkinCapesGranted ? 'bi-check-circle' : 'bi-exclamation-triangle' }} me-1" aria-hidden="true"></i>
                                    {{ trans($mineSkinCapesGranted
                                        ? 'skinsystem::admin.delivery.capes_available'
                                        : 'skinsystem::admin.delivery.capes_unavailable') }}
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="remove_mineskin_api_key" value="1" id="removeMineSkinApiKey">
                                    <label class="form-check-label text-danger" for="removeMineSkinApiKey">
                                        {{ trans('skinsystem::admin.delivery.remove_key') }}
                                    </label>
                                </div>
                            </div>
                        @else
                            <div class="small text-muted">
                                <i class="bi bi-eye-slash me-1" aria-hidden="true"></i>
                                {{ trans('skinsystem::admin.delivery.cape_hidden_without_key') }}
                            </div>
                        @endif
                    </div>
                </div>
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

        <div class="d-flex justify-content-end mb-4">
            <button type="submit" class="btn btn-primary btn-lg px-4">
                <i class="bi bi-check-lg me-1" aria-hidden="true"></i>
                {{ trans('messages.actions.save') }}
            </button>
        </div>
    </form>

@endsection
