@extends('layouts.app')

@section('content')
    <h1>{{ $organization ? 'New collection for '.$organization->name : 'New collection' }}</h1>

    <form method="post" action="{{ $organization ? route('organizations.audio-collections.store', $organization) : route('audio.collections.store') }}">
        @csrf
        @include('audio.collections._form', ['collection' => null])
        <button class="btn-primary" type="submit" style="margin-top:16px;">Save as draft</button>
    </form>
@endsection
