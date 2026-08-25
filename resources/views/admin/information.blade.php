@extends('admin.layouts.admin')

@section('title', trans('skinsystem::admin.nav.information'))

@section('content')
    @include('skinsystem::admin.partials.header', [
        'title' => trans('skinsystem::admin.nav.information'),
        'description' => trans('skinsystem::admin.information.description'),
    ])

    <div class="card">
        <div class="card-header">
            <strong><i class="bi bi-list-check me-2" aria-hidden="true"></i>{{ trans('skinsystem::admin.requirements.title') }}</strong>
        </div>
        <div class="card-body">
            <ol class="mb-3">
                <li class="mb-2">{{ trans('skinsystem::admin.requirements.skinsrestorer') }}</li>
                <li class="mb-2">{{ trans('skinsystem::admin.requirements.bridge') }}</li>
                <li class="mb-2">{{ trans('skinsystem::admin.requirements.public_url') }}</li>
                <li class="mb-2">{{ trans('skinsystem::admin.requirements.uuid') }}</li>
                <li class="mb-2">{{ trans('skinsystem::admin.requirements.url_allowlist') }}</li>
                <li class="mb-2">{{ trans('skinsystem::admin.requirements.skin_api') }}</li>
                <li class="mb-2">{{ trans('skinsystem::admin.requirements.proxy') }}</li>
                <li class="mb-2">{{ trans('skinsystem::admin.requirements.cache_lock') }}</li>
                <li>{{ trans('skinsystem::admin.requirements.scheduler') }}</li>
            </ol>

            <div class="alert alert-info mb-0" role="note">
                <i class="bi bi-info-circle me-2" aria-hidden="true"></i>
                {{ trans('skinsystem::admin.requirements.submitted_semantics') }}
            </div>
        </div>
    </div>
@endsection
