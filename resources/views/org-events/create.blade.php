@extends('layouts.app')

@section('content')
    <h1>New event — {{ $organization->name }}</h1>

    <form method="post" action="{{ route('org-events.store', $organization) }}">
        @csrf
        @include('org-events._form', ['event' => null])
        <button class="btn-primary" type="submit" style="margin-top:16px;">Save as draft</button>
    </form>
@endsection
