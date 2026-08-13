<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>@yield('title') · BizLedger</title><link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet"><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css"><link href="{{ asset('assets/css/bootstrap.min.css') }}" rel="stylesheet"><link href="{{ asset('assets/css/app.css') }}" rel="stylesheet"></head>
<body><main class="login-page d-flex align-items-center justify-content-center py-5"><div class="card login-card shadow-lg"><div class="card-body p-4 p-md-5">@yield('content')</div></div></main></body>
</html>
