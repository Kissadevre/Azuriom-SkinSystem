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
        $variantIcons = [
            \Azuriom\Plugin\SkinSystem\Models\Skin::VARIANT_AUTO => 'bi-stars',
            \Azuriom\Plugin\SkinSystem\Models\Skin::VARIANT_CLASSIC => 'bi-person-standing',
            \Azuriom\Plugin\SkinSystem\Models\Skin::VARIANT_SLIM => 'bi-person',
        ];
    @endphp

    <div class="skinsystem-page">
        <div class="skinsystem-hero d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
            <div class="d-flex align-items-center gap-3">
                <span class="skinsystem-hero-icon" aria-hidden="true">
                    <i class="bi bi-person-bounding-box"></i>
                </span>
                <div>
                    <h1 class="mb-1">{{ trans('skinsystem::messages.title') }}</h1>
                    <p class="text-body-secondary mb-0">{{ trans('skinsystem::messages.description') }}</p>
                </div>
            </div>

            @if($skin)
                <div class="d-flex flex-wrap align-items-center gap-2">
                    <span class="badge rounded-pill text-bg-{{ $syncBadgeClass }} px-3 py-2">
                        <i class="bi bi-circle-fill me-1 skinsystem-status-dot" aria-hidden="true"></i>
                        {{ trans('skinsystem::messages.sync.status.'.$syncStatus) }}
                    </span>
                </div>
            @endif
        </div>

        @if(!$skin && $syncState?->action === \Azuriom\Plugin\SkinSystem\Models\SkinSyncState::ACTION_CLEAR)
            <div class="alert alert-{{ $syncStatus === \Azuriom\Plugin\SkinSystem\Models\SkinSyncState::STATUS_FAILED ? 'danger' : ($syncStatus === \Azuriom\Plugin\SkinSystem\Models\SkinSyncState::STATUS_SUBMITTED ? 'success' : 'warning') }} shadow-sm mb-4">
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
                            <i class="bi bi-arrow-repeat me-1" aria-hidden="true"></i>
                            {{ trans('skinsystem::messages.actions.sync') }}
                        </button>
                    </form>
                </div>
            </div>
        @endif

        @if($libraryEnabled)
            <section class="card skinsystem-card mb-4" id="skin-library">
                <div class="card-header skinsystem-card-header d-flex flex-wrap align-items-center justify-content-between gap-3">
                    <div>
                        <span class="skinsystem-eyebrow">{{ trans('skinsystem::messages.library.eyebrow') }}</span>
                        <h2 class="h5 mb-0">{{ trans('skinsystem::messages.library.title') }}</h2>
                    </div>
                    <span class="badge rounded-pill text-bg-secondary px-3 py-2">
                        {{ trans('skinsystem::messages.library.usage', ['count' => $savedSkins->count(), 'limit' => $libraryLimit]) }}
                    </span>
                </div>
                <div class="card-body p-3 p-md-4">
                    @if($savedSkins->isEmpty())
                        <div class="skinsystem-library-empty">
                            <i class="bi bi-collection" aria-hidden="true"></i>
                            <strong>{{ trans('skinsystem::messages.library.empty_title') }}</strong>
                            <span>{{ trans('skinsystem::messages.library.empty') }}</span>
                        </div>
                    @else
                        <div class="skinsystem-library-grid">
                            @foreach($savedSkins as $savedSkin)
                                @php($isActive = $skin && $skin->sha256 === $savedSkin->sha256 && $skin->variant === $savedSkin->variant)
                                <article class="skinsystem-library-item {{ $isActive ? 'is-active' : '' }}">
                                    <div class="skinsystem-library-preview">
                                        <img src="{{ route('skinsystem.library.image', $savedSkin) }}"
                                             alt="{{ trans('skinsystem::messages.library.preview_alt', ['name' => $savedSkin->name]) }}"
                                             loading="lazy">
                                        @if($isActive)
                                            <span class="badge text-bg-success">
                                                <i class="bi bi-check-circle me-1" aria-hidden="true"></i>
                                                {{ trans('skinsystem::messages.library.active') }}
                                            </span>
                                        @endif
                                    </div>
                                    <div class="p-3">
                                        <h3 class="h6 text-truncate mb-1" title="{{ $savedSkin->name }}">{{ $savedSkin->name }}</h3>
                                        <div class="small text-body-secondary mb-3">
                                            {{ trans('skinsystem::messages.variants.'.$savedSkin->variant) }}
                                        </div>
                                        <div class="d-flex gap-2">
                                            <form class="flex-grow-1" action="{{ route('skinsystem.library.activate', $savedSkin) }}" method="POST">
                                                @csrf
                                                <button class="btn btn-primary btn-sm w-100" type="submit" @disabled($isActive)>
                                                    <i class="bi bi-check2-circle me-1" aria-hidden="true"></i>
                                                    {{ trans($isActive ? 'skinsystem::messages.library.active' : 'skinsystem::messages.actions.activate') }}
                                                </button>
                                            </form>
                                            <button class="btn btn-outline-danger btn-sm"
                                                    type="button"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#deleteSavedSkinModal"
                                                    data-delete-saved-url="{{ route('skinsystem.library.destroy', $savedSkin) }}"
                                                    data-delete-saved-name="{{ $savedSkin->name }}"
                                                    aria-label="{{ trans('skinsystem::messages.actions.remove_saved') }}">
                                                <i class="bi bi-trash" aria-hidden="true"></i>
                                            </button>
                                        </div>
                                    </div>
                                </article>
                            @endforeach
                        </div>
                    @endif
                </div>
            </section>
        @endif

        <div class="row g-4 align-items-start">
            <div class="col-xl-7 col-lg-6">
                <section class="card skinsystem-card skinsystem-viewer-card">
                    <div class="card-header skinsystem-card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
                        <div>
                            <span class="skinsystem-eyebrow">{{ trans('skinsystem::messages.viewer.eyebrow') }}</span>
                            <h2 class="h5 mb-0">{{ trans('skinsystem::messages.viewer.title') }}</h2>
                        </div>
                        <span class="skinsystem-viewer-tip">
                            <i class="bi bi-mouse me-1" aria-hidden="true"></i>
                            {{ trans('skinsystem::messages.viewer.tip') }}
                        </span>
                    </div>
                    <div class="card-body p-3 p-md-4">
                        <div class="skinsystem-viewer-shell"
                             data-skinsystem-viewer
                             @if($skin) data-skin-url="{{ $skin->publicUrl() }}" @endif>
                            <canvas class="skinsystem-viewer-canvas" aria-label="{{ trans('skinsystem::messages.viewer.canvas') }}">
                                {{ trans('skinsystem::messages.viewer.unsupported') }}
                            </canvas>

                            <div class="skinsystem-viewer-placeholder" data-viewer-placeholder @if($skin) hidden @endif>
                                <span class="skinsystem-placeholder-icon">
                                    <i class="bi bi-person-bounding-box" aria-hidden="true"></i>
                                </span>
                                <strong>{{ trans('skinsystem::messages.viewer.empty_title') }}</strong>
                                <span>{{ trans('skinsystem::messages.viewer.empty') }}</span>
                            </div>

                            <div class="skinsystem-viewer-loading" data-viewer-loading hidden>
                                <span class="spinner-border spinner-border-sm" aria-hidden="true"></span>
                                <span>{{ trans('skinsystem::messages.viewer.loading') }}</span>
                            </div>
                        </div>

                        <div class="alert alert-danger mt-3 mb-0" data-viewer-error hidden role="alert">
                            <i class="bi bi-exclamation-triangle me-1" aria-hidden="true"></i>
                            {{ trans('skinsystem::messages.viewer.error') }}
                        </div>
                    </div>
                </section>
            </div>

            <div class="col-xl-5 col-lg-6">
                <section class="card skinsystem-card">
                    <div class="card-header skinsystem-tabs-header">
                        <ul class="nav nav-tabs card-header-tabs skinsystem-tabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active"
                                        id="upload-skin-tab"
                                        data-bs-toggle="tab"
                                        data-bs-target="#upload-skin-pane"
                                        type="button"
                                        role="tab"
                                        aria-controls="upload-skin-pane"
                                        aria-selected="true">
                                    <i class="bi bi-cloud-arrow-up me-1" aria-hidden="true"></i>
                                    {{ trans('skinsystem::messages.upload.title') }}
                                </button>
                            </li>
                            @if($skin)
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link"
                                            id="current-skin-tab"
                                            data-bs-toggle="tab"
                                            data-bs-target="#current-skin-pane"
                                            type="button"
                                            role="tab"
                                            aria-controls="current-skin-pane"
                                            aria-selected="false">
                                        <i class="bi bi-person-check me-1" aria-hidden="true"></i>
                                        {{ trans('skinsystem::messages.current.tab') }}
                                    </button>
                                </li>
                            @endif
                        </ul>
                    </div>
                    <div class="tab-content">
                        <div class="tab-pane fade show active" id="upload-skin-pane" role="tabpanel" aria-labelledby="upload-skin-tab" tabindex="0">
                            <div class="card-body p-3 p-md-4">
                        <form action="{{ route('skinsystem.skins.store') }}"
                              method="POST"
                              enctype="multipart/form-data"
                              data-skinsystem-upload>
                            @csrf

                            <div class="mb-4">
                                <label class="form-label fw-semibold" for="skinInput">
                                    {{ trans('skinsystem::messages.fields.skin') }}
                                </label>
                                <label class="skinsystem-dropzone @error('skin') is-invalid @enderror"
                                       for="skinInput"
                                       data-skin-dropzone
                                       data-invalid-type="{{ trans('skinsystem::messages.upload.invalid_type') }}"
                                       data-invalid-size="{{ trans('skinsystem::messages.upload.invalid_size') }}">
                                    <input type="file"
                                           class="visually-hidden"
                                           id="skinInput"
                                           name="skin"
                                           accept=".png,image/png"
                                           required>

                                    <span class="skinsystem-dropzone-icon" aria-hidden="true">
                                        <i class="bi bi-cloud-arrow-up"></i>
                                    </span>
                                    <span class="skinsystem-dropzone-copy" data-dropzone-copy>
                                        <strong>{{ trans('skinsystem::messages.upload.drop_title') }}</strong>
                                        <span>{{ trans('skinsystem::messages.upload.drop_action') }}</span>
                                    </span>
                                    <span class="skinsystem-file-selection" data-selected-file hidden>
                                        <span class="skinsystem-file-icon" aria-hidden="true">
                                            <i class="bi bi-file-earmark-image"></i>
                                        </span>
                                        <span class="text-start overflow-hidden">
                                            <strong class="d-block text-truncate" data-file-name></strong>
                                            <small class="text-body-secondary" data-file-size></small>
                                        </span>
                                        <span class="skinsystem-change-file">{{ trans('skinsystem::messages.upload.change') }}</span>
                                    </span>
                                </label>
                                @error('skin')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                                <div class="text-danger small mt-2" data-file-error hidden></div>
                                <div class="skinsystem-file-rules" aria-label="{{ trans('skinsystem::messages.upload.requirements') }}">
                                    <span><i class="bi bi-filetype-png" aria-hidden="true"></i> PNG</span>
                                    <span><i class="bi bi-aspect-ratio" aria-hidden="true"></i> 64×64 / 64×32</span>
                                    <span><i class="bi bi-hdd" aria-hidden="true"></i> 3 MB</span>
                                </div>
                            </div>

                            <fieldset class="mb-4">
                                <legend class="form-label fw-semibold mb-1">
                                    {{ trans('skinsystem::messages.fields.variant') }}
                                </legend>
                                <p class="small text-body-secondary mb-3">
                                    {{ trans('skinsystem::messages.upload.variant_help') }}
                                </p>
                                <div class="skinsystem-model-grid">
                                    @foreach(\Azuriom\Plugin\SkinSystem\Models\Skin::variants() as $variant)
                                        <input class="btn-check"
                                               type="radio"
                                               name="variant"
                                               id="variant-{{ $variant }}"
                                               value="{{ $variant }}"
                                               @checked(old('variant', $skin?->variant ?? 'auto') === $variant)
                                               required>
                                        <label class="skinsystem-model-option" for="variant-{{ $variant }}">
                                            <i class="bi {{ $variantIcons[$variant] }}" aria-hidden="true"></i>
                                            <span>
                                                <strong>{{ trans('skinsystem::messages.variants.'.$variant) }}</strong>
                                                <small>{{ trans('skinsystem::messages.variant_descriptions.'.$variant) }}</small>
                                            </span>
                                        </label>
                                    @endforeach
                                </div>
                                @error('variant')
                                    <div class="text-danger small mt-2">{{ $message }}</div>
                                @enderror
                            </fieldset>

                            <div class="skinsystem-sync-note mb-4">
                                <i class="bi bi-lightning-charge-fill" aria-hidden="true"></i>
                                <span>{{ trans('skinsystem::messages.upload.sync_note') }}</span>
                            </div>

                            <div class="skinsystem-upload-actions">
                                <button type="submit"
                                        name="action"
                                        value="activate"
                                        class="btn btn-primary btn-lg skinsystem-submit"
                                        data-upload-submit>
                                    <span data-submit-label>
                                        <i class="bi bi-cloud-arrow-up me-1" aria-hidden="true"></i>
                                        {{ trans($skin ? 'skinsystem::messages.actions.replace' : 'skinsystem::messages.actions.upload') }}
                                    </span>
                                    <span data-submit-loading hidden>
                                        <span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>
                                        {{ trans('skinsystem::messages.actions.uploading') }}
                                    </span>
                                </button>

                                @if($libraryEnabled)
                                    <button type="button"
                                            class="btn btn-outline-primary btn-lg skinsystem-submit"
                                            data-save-skin-open
                                            data-bs-toggle="modal"
                                            data-bs-target="#saveSkinModal">
                                        <i class="bi bi-bookmark-plus me-1" aria-hidden="true"></i>
                                        {{ trans('skinsystem::messages.actions.save') }}
                                    </button>
                                @endif
                            </div>

                            @if($libraryEnabled)
                                <div class="modal fade"
                                     id="saveSkinModal"
                                     tabindex="-1"
                                     aria-labelledby="saveSkinModalLabel"
                                     aria-hidden="true"
                                     @if($errors->has('name') || $errors->has('replacement_id')) data-open-on-load @endif>
                                    <div class="modal-dialog modal-dialog-centered">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h2 class="modal-title fs-5" id="saveSkinModalLabel">{{ trans('skinsystem::messages.library.save_title') }}</h2>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ trans('messages.actions.close') }}"></button>
                                            </div>
                                            <div class="modal-body">
                                                <p class="text-body-secondary">{{ trans('skinsystem::messages.library.save_help') }}</p>
                                                <div class="mb-3">
                                                    <label class="form-label fw-semibold" for="skinName">{{ trans('skinsystem::messages.fields.name') }}</label>
                                                    <input class="form-control @error('name') is-invalid @enderror"
                                                           id="skinName"
                                                           name="name"
                                                           maxlength="16"
                                                           pattern="[A-Za-z0-9]+"
                                                           value="{{ old('name') }}"
                                                           placeholder="{{ trans('skinsystem::messages.upload.name_placeholder') }}">
                                                    @error('name')
                                                        <div class="invalid-feedback">{{ $message }}</div>
                                                    @enderror
                                                    <div class="form-text">{{ trans('skinsystem::messages.library.name_help') }}</div>
                                                </div>

                                                @if($savedSkins->count() >= $libraryLimit)
                                                    <div class="alert alert-warning py-2 small">
                                                        <i class="bi bi-exclamation-triangle me-1" aria-hidden="true"></i>
                                                        {{ trans('skinsystem::messages.library.limit_reached', ['limit' => $libraryLimit]) }}
                                                    </div>
                                                    <label class="form-label fw-semibold" for="replacementSkin">{{ trans('skinsystem::messages.library.replace_label') }}</label>
                                                    <select class="form-select @error('replacement_id') is-invalid @enderror"
                                                            id="replacementSkin"
                                                            name="replacement_id">
                                                        <option value="">{{ trans('skinsystem::messages.library.replace_placeholder') }}</option>
                                                        @foreach($savedSkins as $savedSkin)
                                                            <option value="{{ $savedSkin->id }}" @selected((int) old('replacement_id') === $savedSkin->id)>
                                                                {{ $savedSkin->name }} — {{ trans('skinsystem::messages.variants.'.$savedSkin->variant) }}
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                    @error('replacement_id')
                                                        <div class="invalid-feedback">{{ $message }}</div>
                                                    @enderror
                                                @endif
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ trans('messages.actions.cancel') }}</button>
                                                <button type="submit" name="action" value="save" class="btn btn-primary" data-save-skin-submit>
                                                    <i class="bi bi-bookmark-plus me-1" aria-hidden="true"></i>
                                                    {{ trans('skinsystem::messages.actions.save') }}
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endif
                                </form>
                            </div>
                        </div>

                        @if($skin)
                            <div class="tab-pane fade" id="current-skin-pane" role="tabpanel" aria-labelledby="current-skin-tab" tabindex="0">
                                <div class="card-body p-3 p-md-4 skinsystem-skin-meta">
                                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                                        <div>
                                            <span class="skinsystem-eyebrow">{{ trans('skinsystem::messages.current.eyebrow') }}</span>
                                            <h2 class="h5 mb-0">{{ trans('skinsystem::messages.current.title') }}</h2>
                                        </div>
                                        <span class="badge rounded-pill text-bg-{{ $syncBadgeClass }}">
                                            {{ trans('skinsystem::messages.sync.status.'.$syncStatus) }}
                                        </span>
                                    </div>
                                    <div class="skinsystem-meta-grid mb-3">
                                        <div>
                                            <span>{{ trans('skinsystem::messages.fields.variant') }}</span>
                                            <strong>{{ trans('skinsystem::messages.variants.'.$skin->variant) }}</strong>
                                        </div>
                                        <div>
                                            <span>{{ trans('skinsystem::messages.fields.revision') }}</span>
                                            <strong>#{{ $skin->revision }}</strong>
                                        </div>
                                        @if($skin->variant === \Azuriom\Plugin\SkinSystem\Models\Skin::VARIANT_AUTO)
                                            <div>
                                                <span>{{ trans('skinsystem::messages.fields.resolved_variant') }}</span>
                                                <strong>{{ trans('skinsystem::messages.variants.'.$skin->resolved_variant) }}</strong>
                                            </div>
                                        @endif
                                        @if($syncState?->dispatched_at)
                                            <div>
                                                <span>{{ trans('skinsystem::messages.fields.last_dispatched_at') }}</span>
                                                <strong>{{ format_date($syncState->dispatched_at, true) }}</strong>
                                            </div>
                                        @endif
                                    </div>

                                    @if($syncState?->error)
                                        <div class="alert alert-{{ $syncStatus === \Azuriom\Plugin\SkinSystem\Models\SkinSyncState::STATUS_FAILED ? 'danger' : 'warning' }} py-2 small">
                                            {{ trans('skinsystem::messages.sync.errors.'.$syncState->error) }}
                                        </div>
                                    @endif

                                    <p class="small text-body-secondary">{{ trans('skinsystem::messages.sync.status_help') }}</p>

                                    <div class="skinsystem-current-actions">
                                        <form class="skinsystem-current-primary" action="{{ route('skinsystem.skins.sync') }}" method="POST">
                                            @csrf
                                            <button type="submit" class="btn btn-primary w-100">
                                                <i class="bi bi-arrow-repeat me-1" aria-hidden="true"></i>
                                                {{ trans('skinsystem::messages.actions.sync') }}
                                            </button>
                                        </form>

                                        <a href="{{ $skin->publicUrl() }}" class="btn btn-outline-secondary" download="skin.png">
                                            <i class="bi bi-download me-1" aria-hidden="true"></i>
                                            {{ trans('skinsystem::messages.actions.download') }}
                                        </a>

                                        <button type="button"
                                                class="btn btn-outline-danger"
                                                data-bs-toggle="modal"
                                                data-bs-target="#deleteSkinModal">
                                            <i class="bi bi-trash me-1" aria-hidden="true"></i>
                                            {{ trans('skinsystem::messages.actions.delete') }}
                                        </button>
                                    </div>
                                </div>
                            </div>
                        @endif
                    </div>
                </section>
            </div>
        </div>

    </div>

    @if($libraryEnabled && $savedSkins->isNotEmpty())
        <div class="modal fade" id="deleteSavedSkinModal" tabindex="-1" aria-labelledby="deleteSavedSkinModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h2 class="modal-title fs-5" id="deleteSavedSkinModalLabel">{{ trans('skinsystem::messages.library.delete_title') }}</h2>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ trans('messages.actions.close') }}"></button>
                    </div>
                    <div class="modal-body" data-delete-saved-message data-message-template="{{ trans('skinsystem::messages.library.delete_confirm', ['name' => ':name']) }}"></div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ trans('messages.actions.cancel') }}</button>
                        <form method="POST" data-delete-saved-form>
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-danger">
                                <i class="bi bi-trash me-1" aria-hidden="true"></i>
                                {{ trans('skinsystem::messages.actions.remove_saved') }}
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    @endif

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
                                <i class="bi bi-trash me-1" aria-hidden="true"></i>
                                {{ trans('skinsystem::messages.actions.delete') }}
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    @endif
@endsection
