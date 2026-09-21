@extends('layouts.app')

@section('content')
    <h1>{{ $organization ? 'Upload audio for '.$organization->name : 'Upload audio' }}</h1>

    <form method="post" action="{{ $organization ? route('organizations.audio.store', $organization) : route('audio.store') }}">
        @csrf
        @include('audio._form', ['audio' => null])
        <button class="btn-primary" type="submit" style="margin-top:16px;">Save as draft</button>
    </form>
@endsection
