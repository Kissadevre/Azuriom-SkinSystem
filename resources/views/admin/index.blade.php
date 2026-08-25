@extends('admin.layouts.admin')

@section('title', trans('skinsystem::admin.title'))

@section('content')
    <div class="card shadow mb-4">
        <div class="card-body">
            <p class="mb-0">{{ trans('skinsystem::admin.development') }}</p>
        </div>
    </div>
@endsection

