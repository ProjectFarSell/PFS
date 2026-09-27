@extends('layouts.app')

@section('title', 'Rider application · FarSell')

@section('content')
<div class="max-w-lg mx-auto">

    <div class="mb-5">
        <h1 class="text-xl font-semibold" style="color:rgb(var(--color-text-base));">Rider registration</h1>
        <p class="text-sm mt-1" style="color:rgb(var(--color-text-muted));">
            Submit your courier details for admin review. Your account stays as Buyer until approval.
        </p>
        @if ($profile)
        <p class="mt-2 text-sm">
            Current status:
            <span class="badge badge-accent ml-1">{{ ucfirst($profile->status->value) }}</span>
        </p>
        @endif
    </div>

    {{-- ══════════════════════════════════════════════════════
         MULTI-STEP FORM  (Alpine x-data)
         ══════════════════════════════════════════════════════ --}}
    <div x-data="{
        step: {{ $errors->any() ? 1 : 1 }},
        errors: { phone: '', vehicle_type: '', license_no: '', city: '' },

        validateStep1() {
            const phone = this.$el.querySelector('[name=phone]')?.value.trim() ?? '';
            this.errors.phone = phone === '' ? 'Phone number is required.' : '';
            return this.errors.phone === '';
        },
        validateStep2() {
            const vt = this.$el.querySelector('[name=vehicle_type]')?.value ?? '';
            const ln = this.$el.querySelector('[name=license_no]')?.value.trim() ?? '';
            const ci = this.$el.querySelector('[name=city]')?.value.trim() ?? '';
            this.errors.vehicle_type = vt === '' ? 'Vehicle type is required.' : '';
            this.errors.license_no   = ln === '' ? 'License / ID number is required.' : '';
            this.errors.city         = ci === '' ? 'City is required.' : '';
            return !this.errors.vehicle_type && !this.errors.license_no && !this.errors.city;
        },
        nextStep() {
            if (this.step === 1 && this.validateStep1()) this.step++;
            else if (this.step === 2 && this.validateStep2()) this.step++;
        },
        prevStep() { if (this.step > 1) this.step--; },
    }">

        {{-- ── Step indicator ─────────────────────────────────────────────── --}}
        <div class="flex items-center mb-7">
            @foreach([1 => 'Personal Info', 2 => 'Vehicle Details', 3 => 'Documents'] as $n => $label)
            <div class="flex items-center {{ $n < 3 ? 'flex-1' : '' }}">
                <div class="flex flex-col items-center">
                    <div class="w-8 h-8 rounded-full flex items-center justify-center text-sm font-bold
                                transition-colors"
                         :class="{
                             'text-white': step > {{ $n }},
                             'border-2 font-bold': step === {{ $n }} || step < {{ $n }},
                         }"
                         :style="step > {{ $n }}
                             ? 'background-color:rgb(var(--color-accent));'
                             : step === {{ $n }}
                             ? 'border-color:rgb(var(--color-accent)); color:rgb(var(--color-accent));'
                             : 'border-color:rgb(var(--color-surface-border)); color:rgb(var(--color-text-muted));'">
                        <template x-if="step > {{ $n }}">
                            {{-- Checkmark --}}
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/>
                            </svg>
                        </template>
                        <template x-if="step <= {{ $n }}">
                            <span>{{ $n }}</span>
                        </template>
                    </div>
                    <p class="text-[10px] mt-1 text-center whitespace-nowrap"
                       style="color:rgb(var(--color-text-muted));">{{ $label }}</p>
                </div>
                @if ($n < 3)
                <div class="flex-1 h-px mx-2 transition-colors"
                     :style="step > {{ $n }}
                         ? 'background-color:rgb(var(--color-accent));'
                         : 'background-color:rgb(var(--color-surface-border));'"></div>
                @endif
            </div>
            @endforeach
        </div>

        {{-- ── Single form wrapping all steps ─────────────────────────────── --}}
        <form method="post" action="{{ route('rider.register') }}"
              enctype="multipart/form-data"
              class="fs-card p-5 space-y-4">
            @csrf

            {{-- Server-side errors shown at top of step 1 --}}
            <div x-show="step === 1">
                @if ($errors->any())
                <div class="alert-error" role="alert">
                    <ul class="list-disc pl-4 space-y-0.5">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
                @endif
            </div>

            {{-- ── STEP 1: Personal Info ──────────────────────────────────── --}}
            <div x-show="step === 1" class="space-y-4">
                <div>
                    <label for="r_name" class="fs-label">Full name</label>
                    <input id="r_name" type="text" name="name"
                           value="{{ auth()->user()->name }}" readonly
                           class="fs-input opacity-60 cursor-not-allowed">
                </div>
                <div>
                    <label for="r_phone" class="fs-label">Phone number <span class="text-error">*</span></label>
                    <input id="r_phone" type="tel" name="phone"
                           value="{{ old('phone', auth()->user()->phone ?? '') }}"
                           placeholder="09XXXXXXXXX"
                           class="fs-input">
                    <p x-show="errors.phone !== ''" x-text="errors.phone"
                       class="text-xs mt-1" style="color:rgb(var(--color-error)); display:none;"></p>
                </div>
            </div>

            {{-- ── STEP 2: Vehicle Details ────────────────────────────────── --}}
            <div x-show="step === 2" style="display:none;" class="space-y-4">
                <div>
                    <label for="r_vehicle_type" class="fs-label">Vehicle type <span class="text-error">*</span></label>
                    <select id="r_vehicle_type" name="vehicle_type" class="fs-input">
                        @foreach(['motorcycle', 'bicycle', 'car', 'van'] as $vehicle)
                        <option value="{{ $vehicle }}"
                                @selected(old('vehicle_type', $profile?->vehicle_type ?? 'motorcycle') === $vehicle)>
                            {{ ucfirst($vehicle) }}
                        </option>
                        @endforeach
                    </select>
                    <p x-show="errors.vehicle_type !== ''" x-text="errors.vehicle_type"
                       class="text-xs mt-1" style="color:rgb(var(--color-error)); display:none;"></p>
                </div>
                <div>
                    <label for="r_plate" class="fs-label">Plate number</label>
                    <input id="r_plate" name="plate_number"
                           value="{{ old('plate_number', $profile?->plate_number ?? '') }}"
                           placeholder="e.g. ABC 1234"
                           class="fs-input">
                </div>
                <div>
                    <label for="r_license" class="fs-label">License / ID number <span class="text-error">*</span></label>
                    <input id="r_license" name="license_no" required
                           value="{{ old('license_no', $profile?->license_no ?? '') }}"
                           placeholder="License number"
                           class="fs-input">
                    <p x-show="errors.license_no !== ''" x-text="errors.license_no"
                       class="text-xs mt-1" style="color:rgb(var(--color-error)); display:none;"></p>
                </div>
                <div>
                    <label for="r_city" class="fs-label">City <span class="text-error">*</span></label>
                    <input id="r_city" name="city" required
                           value="{{ old('city', $profile?->city ?? '') }}"
                           placeholder="City of operation"
                           class="fs-input">
                    <p x-show="errors.city !== ''" x-text="errors.city"
                       class="text-xs mt-1" style="color:rgb(var(--color-error)); display:none;"></p>
                </div>
                <div>
                    <label for="r_bio" class="fs-label">Short bio (optional)</label>
                    <textarea id="r_bio" name="bio" rows="3"
                              placeholder="A few words about yourself as a courier"
                              class="fs-input">{{ old('bio', $profile?->bio ?? '') }}</textarea>
                </div>
            </div>

            {{-- ── STEP 3: Document Verification ─────────────────────────── --}}
            <div x-show="step === 3" style="display:none;" class="space-y-4">
                <p class="text-xs" style="color:rgb(var(--color-text-muted));">
                    Accepted: JPG, PNG, PDF · Max 5 MB each
                </p>
                @if($profile?->documents->isNotEmpty())
                    <p class="rounded-lg bg-surface-muted p-3 text-xs text-text-muted">
                        Your current documents stay on file. Choose a new file only for the document you want to replace.
                    </p>
                @endif

                @foreach([
                    ['ref' => 'licenseInput',  'name' => 'license_document',     'type' => 'license',     'label' => "Driver's license"],
                    ['ref' => 'idInput',       'name' => 'id_document',           'type' => 'id',          'label' => 'Valid government ID'],
                    ['ref' => 'vehicleInput',  'name' => 'vehicle_reg_document',  'type' => 'vehicle_reg', 'label' => 'Vehicle registration (OR/CR, if applicable)'],
                ] as $doc)
                @php($currentDocument = $profile?->documents->where('document_type', $doc['type'])->sortByDesc('id')->first())
                <div x-data="{ filename: null, dragging: false }">
                    <div class="mb-1 flex items-center justify-between gap-3">
                        <label class="fs-label mb-0">{{ $doc['label'] }}</label>
                        @if($currentDocument)
                            <span class="text-xs {{ $currentDocument->verified ? 'text-emerald-700' : 'text-amber-700' }}">
                                Current: {{ $currentDocument->verified ? 'Verified' : 'Pending review' }}
                            </span>
                        @endif
                    </div>
                    <div @dragover.prevent="dragging = true"
                         @dragleave.prevent="dragging = false"
                         @drop.prevent="
                             dragging = false;
                             const f = $event.dataTransfer.files[0];
                             if (f) { filename = f.name; $refs.{{ $doc['ref'] }}.files = $event.dataTransfer.files; }
                         "
                         @click="$refs.{{ $doc['ref'] }}.click()"
                         :style="dragging
                             ? 'border-color:rgb(var(--color-accent));'
                             : 'border-color:rgb(var(--color-surface-border));'"
                         class="rounded-xl border-2 border-dashed p-5 text-center cursor-pointer
                                transition-colors hover:border-accent">
                        <template x-if="!filename">
                            <p class="text-sm" style="color:rgb(var(--color-text-muted));">
                                {{ $currentDocument ? 'Drop a replacement here or' : 'Drop file here or' }}
                                <span style="color:rgb(var(--color-accent));" class="font-medium">browse</span>
                            </p>
                        </template>
                        <template x-if="filename">
                            <div class="flex items-center justify-center gap-2">
                                <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24"
                                     stroke="currentColor" stroke-width="2"
                                     style="color:rgb(var(--color-accent));">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                          d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z"/>
                                </svg>
                                <span class="text-sm font-medium truncate max-w-[180px]"
                                      x-text="filename"
                                      style="color:rgb(var(--color-text-base));"></span>
                                <button type="button"
                                        @click.stop="filename = null; $refs.{{ $doc['ref'] }}.value = ''"
                                        class="text-xs font-medium hover:underline"
                                        style="color:rgb(var(--color-error));">
                                    Remove
                                </button>
                            </div>
                        </template>
                        <input x-ref="{{ $doc['ref'] }}"
                               type="file" name="{{ $doc['name'] }}"
                               accept="image/*,.pdf"
                               class="sr-only"
                               @change="filename = $event.target.files[0]?.name ?? null">
                    </div>
                </div>
                @endforeach
            </div>

            {{-- ── Navigation buttons ─────────────────────────────────────── --}}
            <div class="flex items-center justify-between pt-2 gap-3">
                <button type="button" @click="prevStep()"
                        x-show="step > 1"
                        class="btn-outline"
                        style="display:none;">
                    ← Back
                </button>
                <div x-show="step < 3" class="ml-auto">
                    <button type="button" @click="nextStep()" class="btn-accent">
                        Next →
                    </button>
                </div>
                <div x-show="step === 3" style="display:none;" class="ml-auto">
                    <button type="submit" class="btn-accent">
                        {{ $profile ? 'Save application updates' : 'Submit application' }}
                    </button>
                </div>
            </div>

        </form>{{-- /form --}}
    </div>{{-- /x-data --}}
</div>
@endsection
