@extends('layouts.app')

@section('title', 'Tambah Role')

@section('content')
    <x-page-header title="Tambah role" :crumbs="[['label' => 'Role', 'url' => route('roles.index')]]" />

    @include('roles._form', ['action' => route('roles.store')])
@endsection
