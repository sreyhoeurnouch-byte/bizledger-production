@extends('layouts.app')
@section('title',$contact->name)
@section('content')
<div class="profile-toolbar d-flex flex-wrap align-items-center gap-2 mb-2"><a href="{{ route($kind==='vendor'?'vendors.index':'customers.index') }}" class="toolbar-action"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i>{{ ucfirst($kind) }} list</a>@if(auth()->user()->role !== 'viewer')<a href="{{ route($kind==='vendor'?'vendors.edit':'customers.edit',$contact) }}" class="toolbar-action"><i class="fa-solid fa-pen" aria-hidden="true"></i>Edit</a>@endif</div>
<div class="card mb-2">
    <div class="card-body p-3 p-lg-4">
        <div class="row g-3 align-items-center">
            <div class="col-md-auto"><span class="profile-avatar"><i class="fa-solid {{ $kind==='vendor'?'fa-truck':'fa-user' }}" aria-hidden="true"></i></span></div>
            <div class="col-md">
                <h1 class="h5 mb-1">{{ $contact->name }}</h1>
                <div class="profile-meta"><span><i class="fa-regular fa-envelope" aria-hidden="true"></i> {{ $contact->email ?: 'No email address' }}</span><span><i class="fa-solid fa-phone" aria-hidden="true"></i> {{ $contact->phone ?: 'No phone number' }}</span><span><i class="fa-solid fa-receipt" aria-hidden="true"></i> {{ $contact->tax_id ?: 'No tax ID' }}</span></div>
            </div>
            <div class="col-md-auto border-start px-md-4">
                <div class="section-label">{{ $kind==='vendor'?'A/P':'A/R' }} balance</div>
                <div class="profile-balance">${{ number_format($kind==='customer'?$contact->open_balance:$contact->opening_balance,2) }}</div>
            </div>
        </div>
    </div>
</div>
<div class="card">
    <div class="panel-heading">
        <h2 class="h6 mb-0">{{ $kind==='vendor'?'Purchase order history':'Sales order history' }}</h2>
    </div>
    <div class="table-responsive">
        <table class="table table-hover">
            <thead>
                <tr>
                    <th>Number</th>
                    <th>Order date</th>
                    <th>Expected</th>
                    <th class="text-end">Total</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>@forelse($orders as $order)<tr>
                    <td class="fw-semibold">@if($kind==='customer')<a href="{{ route('sales.show',$order) }}">{{ $order->number }}</a>@else{{ $order->number }}@endif</td>
                    <td>{{ $order->order_date->format('d M Y') }}</td>
                    <td>{{ $order->expected_date?->format('d M Y') ?? '-' }}</td>
                    <td class="text-end">${{ number_format($order->total,2) }}</td>
                    <td><x-status :value="$order->status" /></td>
                </tr>@empty<tr>
                    <td colspan="5" class="text-center text-secondary py-5">No orders for this {{ $kind }}.</td>
                </tr>@endforelse</tbody>
        </table>
    </div>@if($kind==='customer' && $invoices->isNotEmpty())<div class="panel-heading border-top">
        <h2 class="h6 mb-0">Invoice history</h2>
    </div>
    <div class="table-responsive">
        <table class="table table-hover">
            <thead>
                <tr>
                    <th>Invoice</th>
                    <th>Date</th>
                    <th class="text-end">Total</th>
                    <th class="text-end">Open</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>@foreach($invoices as $invoice)<tr>
                    <td>{{ $invoice->number }}</td>
                    <td>{{ $invoice->invoice_date->format('d M Y') }}</td>
                    <td class="text-end">${{ number_format($invoice->total,2) }}</td>
                    <td class="text-end">${{ number_format($invoice->open_balance,2) }}</td>
                    <td><x-status :value="$invoice->status" /></td>
                </tr>@endforeach</tbody>
        </table>
    </div>@endif
</div>
@endsection