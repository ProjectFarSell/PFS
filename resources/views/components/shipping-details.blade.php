@props(['details' => null])
@if(is_array($details) && collect($details)->contains(fn ($value) => filled($value)))
    <div {{ $attributes->class('mt-2 space-y-1 text-xs font-normal') }}>
        @if(collect(['length_cm', 'width_cm', 'height_cm', 'weight_kg'])->contains(fn ($key) => isset($details[$key])))
            <p class="text-text-muted">
                @foreach(['length_cm' => 'Length', 'width_cm' => 'Width', 'height_cm' => 'Height', 'weight_kg' => 'Weight'] as $key => $label)
                    @if(isset($details[$key]))<span class="mr-3 inline-block">{{ $label }}: {{ $details[$key] }} {{ $key === 'weight_kg' ? 'kg' : 'cm' }}</span>@endif
                @endforeach
            </p>
        @endif
        @if(is_array($details['handling_flags'] ?? null))
            <div class="flex flex-wrap gap-1">
                @foreach($details['handling_flags'] as $value)
                    @php($flag = is_string($value) ? \App\Enums\HandlingFlag::tryFrom($value) : null)
                    @if($flag)<span class="inline-flex rounded-full border border-surface-border bg-surface-muted px-2 py-0.5">{{ $flag->label() }}</span>@endif
                @endforeach
            </div>
        @endif
    </div>
@endif
