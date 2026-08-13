@extends('auth.layout')
@section('title','Choose a new password')
@section('content')
<div class="text-center mb-4"><span class="brand-square text-white fw-bold"><i class="fa-solid fa-lock" aria-hidden="true"></i></span><h1 class="h3 mt-3">Choose a new password</h1><p class="text-secondary">Use at least 12 characters with upper-case, lower-case, and a number.</p></div>
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
<form method="post" action="{{ route('password.update') }}">@csrf<input type="hidden" name="token" value="{{ $token }}"><div class="mb-3"><label class="form-label">Email address</label><input type="email" class="form-control" name="email" value="{{ old('email',$email) }}" required autofocus></div><div class="mb-3"><label class="form-label">New password</label><input type="password" class="form-control" name="password" autocomplete="new-password" required></div><div class="mb-3"><label class="form-label">Confirm new password</label><input type="password" class="form-control" name="password_confirmation" autocomplete="new-password" required></div><button class="btn btn-primary w-100">Reset password</button></form>
@endsection
