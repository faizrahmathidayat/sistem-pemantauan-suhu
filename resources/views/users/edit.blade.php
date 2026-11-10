@extends('layouts.app')

@section('title', 'Ubah User '.$user->name)

@section('content')
    <x-page-header :title="'Ubah user '.$user->name" :crumbs="[['label' => 'User', 'url' => route('users.index')]]" />

    @include('users._form', ['action' => route('users.update', $user)])
@endsection
