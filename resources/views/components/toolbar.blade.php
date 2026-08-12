@props(['placeholder'=>'Search records...','createLabel'=>null,'createUrl'=>null,'filters'=>[],'importUrl'=>null,'importLabel'=>'Import CSV'])
<div class="compact-toolbar d-flex flex-wrap align-items-center gap-2">
    <form class="input-group input-group-sm search-control" method="get" action="{{ url()->current() }}" role="search">
        <span class="input-group-text"><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i></span>
        <input class="form-control" type="search" name="q" value="{{ request('q') }}" placeholder="{{ $placeholder }}" aria-label="{{ $placeholder }}">
        <button class="btn btn-outline-secondary" type="submit">Search</button>
        @if(request()->filled('q'))<a class="btn btn-light" href="{{ url()->current() }}" aria-label="Clear search"><i class="fa-solid fa-xmark" aria-hidden="true"></i></a>@endif
    </form>
    @if(is_array($filters))
        @foreach($filters as $name => $filter)
            @if(is_array($filter) && isset($filter['options']))
                <form method="get" action="{{ url()->current() }}" class="toolbar-filter-form">
                    @if(request()->filled('q'))<input type="hidden" name="q" value="{{ request('q') }}">@endif
                    <label class="visually-hidden" for="filter-{{ $name }}">{{ $filter['label'] ?? $name }}</label>
                    <select id="filter-{{ $name }}" class="form-select form-select-sm" name="{{ $name }}" onchange="this.form.submit()">
                        @foreach($filter['options'] as $value => $label)<option value="{{ $value }}" @selected(request($name, 'all') === (string) $value)>{{ $label }}</option>@endforeach
                    </select>
                </form>
            @endif
        @endforeach
    @endif
    <div class="toolbar-actions">
        <button class="toolbar-action" type="button" data-export-table title="Download the visible table rows as CSV"><i class="fa-solid fa-file-csv" aria-hidden="true"></i>Export</button>
        <button class="toolbar-action" type="button" data-print-page title="Print this page"><i class="fa-solid fa-print" aria-hidden="true"></i>Print</button>
        <button class="toolbar-action" type="button" data-table-density title="Toggle compact table rows"><i class="fa-solid fa-list" aria-hidden="true"></i>Compact view</button>
        @if($importUrl && auth()->user()->role !== 'viewer')<button class="toolbar-action" type="button" data-bs-toggle="modal" data-bs-target="#csvImportModal"><i class="fa-solid fa-file-arrow-up" aria-hidden="true"></i>{{ $importLabel }}</button>@endif
        @if($createLabel && auth()->user()->role !== 'viewer')
            @if($createUrl)<a class="toolbar-action" href="{{ $createUrl }}"><i class="fa-solid fa-circle-plus" aria-hidden="true"></i>{{ $createLabel }}</a>
            @else<button type="button" class="toolbar-action" data-bs-toggle="modal" data-bs-target="#recordModal"><i class="fa-solid fa-circle-plus" aria-hidden="true"></i>{{ $createLabel }}</button>@endif
        @endif
    </div>
</div>
