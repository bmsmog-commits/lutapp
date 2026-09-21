@extends('layouts.app')

@section('content')
    <h1>Edit collection</h1>

    <form method="post" action="{{ route('audio.collections.update', $collection) }}">
        @csrf
        @method('PUT')
        @include('audio.collections._form', ['collection' => $collection])
        <button class="btn-primary" type="submit" style="margin-top:16px;">Save changes</button>
    </form>
@endsection
