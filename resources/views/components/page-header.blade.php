@props(['eyebrow','title','description','action'=>null,'target'=>'recordModal','icon'=>null])
@php
    $icons = ['vendor center' => 'fa-truck', 'customer center' => 'fa-users', 'vendors' => 'fa-truck', 'customers' => 'fa-users', 'items' => 'fa-boxes-stacked', 'stock movements' => 'fa-right-left', 'purchase orders' => 'fa-cart-shopping', 'chart of accounts' => 'fa-book', 'stock valuation' => 'fa-chart-pie'];
    $iconClass = $icon ?? ($icons[str($title)->lower()->toString()] ?? 'fa-layer-group');
@endphp
<div class="module-header d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
    <div class="d-flex align-items-center gap-3"><span class="module-icon"><i class="fa-solid {{ $iconClass }}" aria-hidden="true"></i></span><div><div class="section-label mb-1">{{ $eyebrow }}</div><h1 class="h4 mb-1 fw-semibold">{{ $title }}</h1><p class="text-secondary mb-0 small">{{ $description }}</p></div></div>
    @if($action && auth()->user()->role !== 'viewer')<button class="btn btn-primary btn-sm action-create" data-bs-toggle="modal" data-bs-target="#{{ $target }}"><i class="fa-solid fa-plus" aria-hidden="true"></i> {{ $action }}</button>@endif
</div>
