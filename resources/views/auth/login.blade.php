<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Sign in · BizLedger</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <link href="{{ asset('assets/css/bootstrap.min.css') }}" rel="stylesheet">
    <link href="{{ asset('assets/css/app.css') }}" rel="stylesheet">
</head>
<body>
<main class="login-page d-flex align-items-center justify-content-center py-5">
    <div class="card login-card shadow-lg"><div class="card-body p-4 p-md-5">
        <div class="text-center mb-4"><span class="brand-square text-white fw-bold"><i class="fa-solid fa-chart-line" aria-hidden="true"></i></span><h1 class="h3 mt-3">Welcome to BizLedger</h1><p class="text-secondary">Sign in to your business workspace.</p></div>
        @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
        <form method="post" action="{{ route('login.store') }}">@csrf
            <div class="mb-3"><label class="form-label">Email address</label><input type="email" class="form-control" name="email" value="{{ old('email','admin@bizledger.local') }}" required autofocus></div>
            <div class="mb-3"><label class="form-label">Password</label><input type="password" class="form-control" name="password" autocomplete="current-password" required></div>
            <div class="mb-3 form-check"><input class="form-check-input" type="checkbox" name="remember" value="1" id="remember"><label class="form-check-label" for="remember">Keep me signed in</label></div>
            <button class="btn btn-primary w-100">Sign in</button>
        </form>
    </div></div>
</main>
</body>
</html>
