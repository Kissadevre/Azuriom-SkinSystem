@extends('admin.layouts.admin')

@section('title', trans('skinsystem::admin.nav.settings'))

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

        <div class="card mb-4">
            <div class="card-header">
                <strong>{{ trans('skinsystem::admin.settings.title') }}</strong>
            </div>
            <div class="card-body p-0">
                <div class="d-flex align-items-center justify-content-between gap-4 p-4 border-bottom">
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

                <div class="p-4 border-bottom">
                    <label class="form-label fw-semibold" for="serverId">
                        {{ trans('skinsystem::admin.settings.server') }}
                    </label>
                    <select class="form-select @error('server_id') is-invalid @enderror"
                            id="serverId"
                            name="server_id">
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
                    <div class="form-text">{{ trans('skinsystem::admin.settings.server_help') }}</div>
                </div>

                <div class="p-4 border-bottom">
                    <label class="form-label fw-semibold" for="libraryLimit">
                        {{ trans('skinsystem::admin.settings.library_limit') }}
                    </label>
                    <input class="form-control @error('library_limit') is-invalid @enderror"
                           type="number"
                           id="libraryLimit"
                           name="library_limit"
                           min="1"
                           max="{{ \Azuriom\Plugin\SkinSystem\Services\SkinSystemSettings::MAX_LIBRARY_LIMIT }}"
                           value="{{ old('library_limit', $libraryLimit) }}"
                           required>
                    @error('library_limit')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                    <div class="form-text">{{ trans('skinsystem::admin.settings.library_limit_help') }}</div>
                </div>

                <div class="p-4">
                    <label class="form-label fw-semibold" for="publicEndpoint">
                        {{ trans('skinsystem::admin.settings.endpoint') }}
                    </label>
                    <input class="form-control font-monospace"
                           id="publicEndpoint"
                           value="{{ $publicEndpoint }}"
                           readonly>
                    <div class="form-text">{{ trans('skinsystem::admin.settings.endpoint_help') }}</div>
                </div>
            </div>
        </div>

        <div class="text-end mb-4">
            <button type="submit" class="btn btn-primary px-4">
                <i class="bi bi-check-lg me-1" aria-hidden="true"></i>
                {{ trans('messages.actions.save') }}
            </button>
        </div>
    </form>

@endsection
