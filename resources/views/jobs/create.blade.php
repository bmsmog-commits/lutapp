@extends('layouts.app')

@section('content')
    <h1>{{ $organization ? 'Post a job for '.$organization->name : 'Post a job' }}</h1>

    <form method="post" action="{{ $organization ? route('organizations.jobs.store', $organization) : route('jobs.store') }}">
        @csrf
        @include('jobs._form', ['job' => null])
        <button class="btn-primary" type="submit" style="margin-top:16px;">Save as draft</button>
    </form>
@endsection
