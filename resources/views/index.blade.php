@extends('layouts.app')

@section('title', trans('skinsystem::messages.title'))

@section('content')
    <div class="container content">
        <h1>{{ trans('skinsystem::messages.title') }}</h1>

        <div class="alert alert-info mb-0" role="status">
            {{ trans('skinsystem::messages.development') }}
        </div>
    </div>
@endsection

