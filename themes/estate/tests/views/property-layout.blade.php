@extends('estate::layouts.main')

@section('main')
    @include('estate::property', [
        'data' => $data,
        'files' => $files,
        'page' => $page,
    ])
@endsection
