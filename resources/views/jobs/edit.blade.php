@extends('layouts.app')

@section('content')
    <h1>Edit job</h1>

    <form method="post" action="{{ route('jobs.update', $job) }}">
        @csrf
        @method('PUT')
        @include('jobs._form', ['job' => $job])
        <button class="btn-primary" type="submit" style="margin-top:16px;">Save changes</button>
    </form>
@endsection
