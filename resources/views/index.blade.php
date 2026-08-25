@extends('layouts.app')

@section('title', trans('skinsystem::messages.title'))

@push('styles')
    <link href="{{ plugin_asset('skinsystem', 'css/skinsystem.css') }}" rel="stylesheet">
@endpush

@push('footer-scripts')
    <script src="{{ plugin_asset('skinsystem', 'vendor/skinview3d/skinview3d.bundle.js') }}"></script>
    <script src="{{ plugin_asset('skinsystem', 'js/skinsystem.js') }}"></script>
@endpush

@section('content')
    @php
        $syncStatus = $syncState?->status ?? \Azuriom\Plugin\SkinSystem\Models\SkinSyncState::STATUS_PENDING;
        $syncBadgeClass = match($syncStatus) {
            \Azuriom\Plugin\SkinSystem\Models\SkinSyncState::STATUS_SUBMITTED => 'success',
            \Azuriom\Plugin\SkinSystem\Models\SkinSyncState::STATUS_FAILED => 'danger',
            \Azuriom\Plugin\SkinSystem\Models\SkinSyncState::STATUS_UNCERTAIN => 'warning',
            \Azuriom\Plugin\SkinSystem\Models\SkinSyncState::STATUS_PENDING => 'info',
            default => 'secondary',
        };
    @endphp

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
        <div>
            <h1 class="mb-1">{{ trans('skinsystem::messages.title') }}</h1>
            <p class="text-muted mb-0">{{ trans('skinsystem::messages.description') }}</p>
        </div>

        @if($skin)
            <a href="{{ $skin->publicUrl() }}" class="btn btn-outline-secondary" download="skin.png">
                <i class="bi bi-download"></i> {{ trans('skinsystem::messages.actions.download') }}
            </a>
        @endif
    </div>

    @if(!$skin && $syncState?->action === \Azuriom\Plugin\SkinSystem\Models\SkinSyncState::ACTION_CLEAR)
        <div class="alert alert-{{ $syncStatus === \Azuriom\Plugin\SkinSystem\Models\SkinSyncState::STATUS_FAILED ? 'danger' : ($syncStatus === \Azuriom\Plugin\SkinSystem\Models\SkinSyncState::STATUS_SUBMITTED ? 'success' : 'warning') }} mb-4">
            <div class="d-flex flex-wrap align-items-start justify-content-between gap-3">
                <div>
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <strong>{{ trans('skinsystem::messages.sync.clear_state_title') }}</strong>
                        <span class="badge text-bg-{{ $syncBadgeClass }}">
                            {{ trans('skinsystem::messages.sync.status.'.$syncStatus) }}
                        </span>
                    </div>
                    <div>{{ trans('skinsystem::messages.sync.clear_state_help') }}</div>
                    @if($syncState->error)
                        <div class="mt-1">{{ trans('skinsystem::messages.sync.errors.'.$syncState->error) }}</div>
                    @endif
                    @if($syncState->dispatched_at)
                        <small class="d-block mt-1">
                            {{ trans('skinsystem::messages.fields.last_dispatched_at') }}:
                            {{ format_date($syncState->dispatched_at, true) }}
                        </small>
                    @endif
                </div>

                <form action="{{ route('skinsystem.skins.sync') }}" method="POST">
                    @csrf
                    <button type="submit" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-repeat"></i> {{ trans('skinsystem::messages.actions.sync') }}
                    </button>
                </form>
            </div>
        </div>
    @endif

    <div class="row g-4">
        <div class="col-lg-6">
            <div class="card shadow-sm h-100">
                <div class="card-header">
                    <h2 class="h5 mb-0">{{ trans('skinsystem::messages.viewer.title') }}</h2>
                </div>
                <div class="card-body">
                    <div class="skinsystem-viewer-shell"
                         data-skinsystem-viewer
                         @if($skin) data-skin-url="{{ $skin->publicUrl() }}" @endif>
                        <canvas class="skinsystem-viewer-canvas" aria-label="{{ trans('skinsystem::messages.viewer.canvas') }}">
                            {{ trans('skinsystem::messages.viewer.unsupported') }}
                        </canvas>

                        <div class="skinsystem-viewer-placeholder" data-viewer-placeholder @if($skin) hidden @endif>
                            <i class="bi bi-person-bounding-box" aria-hidden="true"></i>
                            <span>{{ trans('skinsystem::messages.viewer.empty') }}</span>
                        </div>
                    </div>

                    <div class="alert alert-danger mt-3 mb-0" data-viewer-error hidden role="alert">
                        <i class="bi bi-exclamation-triangle"></i>
                        {{ trans('skinsystem::messages.viewer.error') }}
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card shadow-sm mb-4">
                <div class="card-header">
                    <h2 class="h5 mb-0">{{ trans('skinsystem::messages.upload.title') }}</h2>
                </div>
                <div class="card-body">
                    <form action="{{ route('skinsystem.skins.store') }}" method="POST" enctype="multipart/form-data">
                        @csrf

                        <div class="mb-3">
                            <label class="form-label" for="skinInput">{{ trans('skinsystem::messages.fields.skin') }}</label>
                            <input type="file"
                                   class="form-control @error('skin') is-invalid @enderror"
                                   id="skinInput"
                                   name="skin"
                                   accept=".png,image/png"
                                   required>
                            @error('skin')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <div class="form-text">{{ trans('skinsystem::messages.upload.help') }}</div>
                        </div>

                        <div class="mb-4">
                            <label class="form-label" for="variantInput">{{ trans('skinsystem::messages.fields.variant') }}</label>
                            <select class="form-select @error('variant') is-invalid @enderror" id="variantInput" name="variant" required>
                                @foreach(\Azuriom\Plugin\SkinSystem\Models\Skin::variants() as $variant)
                                    <option value="{{ $variant }}" @selected(old('variant', $skin?->variant ?? 'auto') === $variant)>
                                        {{ trans('skinsystem::messages.variants.'.$variant) }}
                                    </option>
                                @endforeach
                            </select>
                            @error('variant')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-cloud-arrow-up"></i>
                            {{ trans($skin ? 'skinsystem::messages.actions.replace' : 'skinsystem::messages.actions.upload') }}
                        </button>
                    </form>
                </div>
            </div>

            @if($skin)
                <div class="card shadow-sm">
                    <div class="card-header">
                        <h2 class="h5 mb-0">{{ trans('skinsystem::messages.current.title') }}</h2>
                    </div>
                    <div class="card-body skinsystem-skin-meta">
                        <dl class="row mb-4">
                            <dt class="col-sm-4">{{ trans('skinsystem::messages.fields.variant') }}</dt>
                            <dd class="col-sm-8">{{ trans('skinsystem::messages.variants.'.$skin->variant) }}</dd>

                            @if($skin->variant === \Azuriom\Plugin\SkinSystem\Models\Skin::VARIANT_AUTO)
                                <dt class="col-sm-4">{{ trans('skinsystem::messages.fields.resolved_variant') }}</dt>
                                <dd class="col-sm-8">{{ trans('skinsystem::messages.variants.'.$skin->resolved_variant) }}</dd>
                            @endif

                            <dt class="col-sm-4">{{ trans('skinsystem::messages.fields.revision') }}</dt>
                            <dd class="col-sm-8">{{ $skin->revision }}</dd>

                            <dt class="col-sm-4">{{ trans('skinsystem::messages.fields.sync_status') }}</dt>
                            <dd class="col-sm-8">
                                <span class="badge text-bg-{{ $syncBadgeClass }}">
                                    {{ trans('skinsystem::messages.sync.status.'.$syncStatus) }}
                                </span>
                            </dd>

                            @if($syncState?->dispatched_at)
                                <dt class="col-sm-4">{{ trans('skinsystem::messages.fields.last_dispatched_at') }}</dt>
                                <dd class="col-sm-8">{{ format_date($syncState->dispatched_at, true) }}</dd>
                            @endif

                            <dt class="col-sm-4">SHA-256</dt>
                            <dd class="col-sm-8"><code>{{ $skin->sha256 }}</code></dd>
                        </dl>

                        @if($syncState?->error)
                            <div class="alert alert-{{ $syncStatus === \Azuriom\Plugin\SkinSystem\Models\SkinSyncState::STATUS_FAILED ? 'danger' : 'warning' }} py-2">
                                {{ trans('skinsystem::messages.sync.errors.'.$syncState->error) }}
                            </div>
                        @endif

                        <p class="small text-muted">{{ trans('skinsystem::messages.sync.status_help') }}</p>

                        <div class="d-flex flex-wrap gap-2">
                            <form action="{{ route('skinsystem.skins.sync') }}" method="POST">
                                @csrf
                                <button type="submit" class="btn btn-outline-primary">
                                    <i class="bi bi-arrow-repeat"></i> {{ trans('skinsystem::messages.actions.sync') }}
                                </button>
                            </form>

                            <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteSkinModal">
                                <i class="bi bi-trash"></i> {{ trans('skinsystem::messages.actions.delete') }}
                            </button>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>

    @if($skin)
        <div class="modal fade" id="deleteSkinModal" tabindex="-1" aria-labelledby="deleteSkinModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h2 class="modal-title fs-5" id="deleteSkinModalLabel">{{ trans('skinsystem::messages.delete.title') }}</h2>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ trans('messages.actions.close') }}"></button>
                    </div>
                    <div class="modal-body">
                        {{ trans('skinsystem::messages.delete.confirm') }}
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            {{ trans('messages.actions.cancel') }}
                        </button>
                        <form action="{{ route('skinsystem.skins.destroy') }}" method="POST">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-danger">
                                <i class="bi bi-trash"></i> {{ trans('skinsystem::messages.actions.delete') }}
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    @endif
@endsection
