@extends('layouts.app')

@section('title', 'Tambah User')

@section('content')
    <x-page-header title="Tambah user" :crumbs="[['label' => 'User', 'url' => route('users.index')]]" />

    @include('users._form', ['action' => route('users.store')])
@endsection
