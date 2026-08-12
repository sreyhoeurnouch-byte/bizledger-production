<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>@yield('title','BizLedger') · BizLedger</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <link href="{{ asset('assets/css/bootstrap.min.css') }}" rel="stylesheet">
    <link href="{{ asset('assets/css/app.css') }}" rel="stylesheet">
</head>
<body>
<nav class="navbar navbar-expand-xl navbar-dark app-navbar sticky-top">
    <div class="container-fluid px-3 px-lg-4">
        <a class="navbar-brand fw-bold" href="{{ route('dashboard') }}"><span class="brand-square"><i class="fa-solid fa-chart-line" aria-hidden="true"></i></span><span>BizLedger</span></a>
        <button class="navbar-toggler" data-bs-toggle="collapse" data-bs-target="#mainNav" aria-label="Toggle navigation"><span class="navbar-toggler-icon"></span></button>
        <div class="collapse navbar-collapse" id="mainNav">
            <ul class="navbar-nav me-auto mb-2 mb-xl-0">
                <li class="nav-item"><a class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}">Dashboard</a></li>
                <li class="nav-item dropdown"><a class="nav-link dropdown-toggle {{ request()->routeIs('vendors.*') ? 'active' : '' }}" href="#" data-bs-toggle="dropdown">Vendor</a><div class="dropdown-menu app-menu"><a class="dropdown-item" href="{{ route('vendors.index') }}"><span class="menu-mark"><i class="fa-solid fa-truck" aria-hidden="true"></i></span><span><strong>Vendor center</strong><small>Suppliers and payables</small></span></a></div></li>
                <li class="nav-item dropdown"><a class="nav-link dropdown-toggle {{ request()->routeIs('items.*','stock.*') ? 'active' : '' }}" href="#" data-bs-toggle="dropdown">Item</a><div class="dropdown-menu app-menu"><a class="dropdown-item" href="{{ route('items.index') }}"><span class="menu-mark"><i class="fa-solid fa-boxes-stacked" aria-hidden="true"></i></span><span><strong>Items</strong><small>Catalog, costs, and pricing</small></span></a><a class="dropdown-item" href="{{ route('stock.index') }}"><span class="menu-mark"><i class="fa-solid fa-right-left" aria-hidden="true"></i></span><span><strong>Stock movements</strong><small>Receipts, issues, and transfers</small></span></a></div></li>
                <li class="nav-item dropdown"><a class="nav-link dropdown-toggle {{ request()->routeIs('customers.*') ? 'active' : '' }}" href="#" data-bs-toggle="dropdown">Customer</a><div class="dropdown-menu app-menu"><a class="dropdown-item" href="{{ route('customers.index') }}"><span class="menu-mark"><i class="fa-solid fa-users" aria-hidden="true"></i></span><span><strong>Customer center</strong><small>Customers and receivables</small></span></a></div></li>
                <li class="nav-item dropdown"><a class="nav-link dropdown-toggle {{ request()->routeIs('purchasing.*','accounting.*') ? 'active' : '' }}" href="#" data-bs-toggle="dropdown">Finance</a><div class="dropdown-menu app-menu"><a class="dropdown-item" href="{{ route('purchasing.index') }}"><span class="menu-mark"><i class="fa-solid fa-cart-shopping" aria-hidden="true"></i></span><span><strong>Purchase orders</strong><small>Plan and receive supplier orders</small></span></a><a class="dropdown-item" href="{{ route('accounting.index') }}"><span class="menu-mark"><i class="fa-solid fa-book" aria-hidden="true"></i></span><span><strong>Chart of accounts</strong><small>Accounts and opening balances</small></span></a></div></li>
                <li class="nav-item"><a class="nav-link {{ request()->routeIs('reports.*') ? 'active' : '' }}" href="{{ route('reports.stock') }}">Report</a></li>
            </ul>
            <div class="nav-utility"><span class="utility-company d-none d-xxl-inline">{{ auth()->user()->company->name }}</span><span class="utility-dot"></span><div class="dropdown"><button class="user-menu dropdown-toggle" data-bs-toggle="dropdown"><span class="user-avatar">{{ str(auth()->user()->name)->substr(0,1)->upper() }}</span><span class="d-none d-lg-inline">{{ auth()->user()->name }}</span></button><div class="dropdown-menu dropdown-menu-end shadow-sm"><div class="dropdown-header">Signed in to BizLedger</div><form method="post" action="{{ route('logout') }}">@csrf<button class="dropdown-item text-danger">Sign out</button></form></div></div></div>
        </div>
    </div>
</nav>
<div class="company-bar"><div class="container-fluid px-3 px-lg-4 d-flex justify-content-between align-items-center h-100"><div class="company-crumb"><span class="crumb-dot"></span><strong>{{ auth()->user()->company->name }}</strong><span class="text-secondary">/ Head Office</span></div><span class="period-status"><span></span> Fiscal year {{ date('Y') }} open</span></div></div>
<main class="container-fluid px-3 px-lg-4 py-3 py-lg-4 app-content">
    @if(session('success'))<div class="alert alert-success alert-dismissible fade show">{{ session('success') }}<button class="btn-close" data-bs-dismiss="alert"></button></div>@endif
    @if($errors->any())<div class="alert alert-danger"><strong>Please correct the following:</strong><ul class="mb-0 mt-1">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    @yield('content')
</main>
<script src="{{ asset('assets/js/bootstrap.bundle.min.js') }}"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const densityKey = 'bizledger-table-density';
    const applyDensity = (compact) => document.body.classList.toggle('table-density-compact', compact);
    applyDensity(localStorage.getItem(densityKey) === 'compact');
    document.querySelectorAll('[data-table-density]').forEach((button) => button.addEventListener('click', () => {
        const compact = !document.body.classList.contains('table-density-compact');
        localStorage.setItem(densityKey, compact ? 'compact' : 'comfortable');
        applyDensity(compact);
        button.innerHTML = compact ? '<i class="fa-solid fa-table-list" aria-hidden="true"></i>Comfortable view' : '<i class="fa-solid fa-list" aria-hidden="true"></i>Compact view';
    }));
    document.querySelectorAll('[data-print-page]').forEach((button) => button.addEventListener('click', () => window.print()));
    document.querySelectorAll('[data-export-table]').forEach((button) => button.addEventListener('click', () => {
        const table = button.closest('.card')?.querySelector('table');
        if (!table) return;
        const rows = [...table.querySelectorAll('tr')].filter((row) => row.offsetParent !== null);
        const csv = rows.map((row) => [...row.cells].map((cell) => '"' + cell.innerText.replaceAll('"', '""').replaceAll(/\s+/g, ' ').trim() + '"').join(',')).join('\n');
        const link = document.createElement('a');
        link.href = URL.createObjectURL(new Blob([csv], { type: 'text/csv;charset=utf-8;' }));
        link.download = `${document.title.toLowerCase().replaceAll(/[^a-z0-9]+/g, '-') || 'bizledger'}-export.csv`;
        link.click(); URL.revokeObjectURL(link.href);
    }));
});
</script>
@stack('scripts')
</body>
</html>
