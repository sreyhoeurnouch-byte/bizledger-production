@extends('auth.layout')
@section('title','Reset password')
@section('content')
<div class="text-center mb-4"><span class="brand-square text-white fw-bold"><i class="fa-solid fa-key" aria-hidden="true"></i></span><h1 class="h3 mt-3">Reset your password</h1><p class="text-secondary">Enter your email and we’ll send a secure reset link.</p></div>
@if(session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
<form method="post" action="{{ route('password.email') }}">@csrf<div class="mb-3"><label class="form-label">Email address</label><input type="email" class="form-control" name="email" value="{{ old('email') }}" required autofocus></div><button class="btn btn-primary w-100">Email reset link</button></form><div class="text-center mt-3"><a href="{{ route('login') }}">Back to sign in</a></div>
@endsection
