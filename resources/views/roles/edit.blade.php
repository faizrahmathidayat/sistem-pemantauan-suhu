@extends('layouts.app')

@section('title', 'Ubah Role '.$role->name)

@section('content')
    <x-page-header :title="'Ubah role '.$role->name" :crumbs="[['label' => 'Role', 'url' => route('roles.index')]]" />

    @include('roles._form', ['action' => route('roles.update', $role)])
@endsection
