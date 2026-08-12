@extends('layouts.app')
@section('title','Stock valuation')
@section('content')
<x-page-header eyebrow="Reporting" title="Stock valuation" description="Analyze quantity, average cost, and value by item."/>
<div class="card">
    <x-toolbar placeholder="Filter report rows..." :filters="['As of '.date('M d, Y'),'All warehouses']" :create-label="null"/>
    <div class="table-responsive"><table class="table table-hover"><thead><tr><th>SKU</th><th>Item</th><th>Unit</th><th class="text-end">Quantity</th><th class="text-end">Average cost</th><th class="text-end">Stock value</th><th>State</th></tr></thead><tbody>@foreach($items as $row)<tr><td>{{ $row->sku }}</td><td class="fw-semibold">{{ $row->name }}</td><td>{{ $row->unit }}</td><td class="text-end">{{ $row->quantity }}</td><td class="text-end">${{ number_format($row->cost,2) }}</td><td class="text-end">${{ number_format($row->quantity*$row->cost,2) }}</td><td><x-status :value="$row->quantity<=$row->reorder_level?'low':'healthy'"/></td></tr>@endforeach</tbody><tfoot><tr class="report-total fw-bold"><td colspan="5">Total inventory value</td><td class="text-end">${{ number_format($items->sum(fn($i)=>$i->quantity*$i->cost),2) }}</td><td></td></tr></tfoot></table></div>
</div>
@endsection
