@props(['cancelRoute', 'saveNewRoute' => null, 'saveLabel' => 'Save', 'showSaveNew' => true])
<div class="card-footer bg-white d-flex justify-content-end gap-2">
    <a class="btn btn-light" href="{{ $cancelRoute }}">Cancel</a>
    @if($showSaveNew && $saveNewRoute)
        <button type="submit" name="action" value="save" class="btn btn-outline-primary">
            <i class="fa-solid fa-floppy-disk" aria-hidden="true"></i> {{ $saveLabel }}
        </button>
        <button type="submit" name="action" value="save_new" class="btn btn-primary">
            <i class="fa-solid fa-plus" aria-hidden="true"></i> {{ $saveLabel }} & New
        </button>
    @else
        <button type="submit" class="btn btn-primary">
            <i class="fa-solid fa-floppy-disk" aria-hidden="true"></i> {{ $saveLabel }}
        </button>
    @endif
</div>
