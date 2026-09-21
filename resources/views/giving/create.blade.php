@extends('layouts.app')

@section('content')
    <h1>New giving campaign — {{ $organization->name }}</h1>

    <form method="post" action="{{ route('giving.store', $organization) }}">
        @csrf
        @include('giving._form', ['campaign' => null])
        <button class="btn-primary" type="submit" style="margin-top:16px;">Save as draft</button>
    </form>
@endsection
