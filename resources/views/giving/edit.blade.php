@extends('layouts.app')

@section('content')
    <h1>Edit campaign</h1>

    <form method="post" action="{{ route('giving.update', [$organization, $campaign]) }}">
        @csrf
        @method('PUT')
        @include('giving._form', ['campaign' => $campaign])
        <button class="btn-primary" type="submit" style="margin-top:16px;">Save changes</button>
    </form>
@endsection
