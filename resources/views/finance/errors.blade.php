@if($errors->any())
    <div role="alert" class="fs-card mb-4 border border-red-400 p-4">
        <ul class="list-disc pl-5">@foreach($errors->all() as $message)<li>{{ $message }}</li>@endforeach</ul>
    </div>
@endif
