@extends('layouts.app')

@section('title', ($ad ? __('ads.mine.edit_title') : __('ads.post.title')).' · '.$category->label)

@push('scripts')
    @vite('resources/js/post-ad.js')
@endpush

@section('content')
    <section class="mx-auto max-w-2xl px-4 py-6">
        @if ($ad)
            <p class="text-sm font-medium text-brand-700">{{ __('ads.mine.edit_title') }}</p>
            <h1 class="text-2xl font-bold">{{ $category->label }}</h1>
            @feature('ads.approval')
                <p class="mt-2 rounded-lg bg-accent-400/20 px-3 py-2 text-sm">{{ __('ads.mine.edit_note') }}</p>
            @endfeature
        @else
            <p class="text-sm font-medium text-brand-700">{{ __('ads.post.step', ['step' => 2]) }}</p>
            <div class="flex items-center gap-2">
                <h1 class="text-2xl font-bold">{{ $category->label }}</h1>
                <a href="{{ lroute('post') }}" class="text-sm font-medium text-brand-700 underline">{{ __('ads.post.change_category') }}</a>
            </div>
        @endif

        <p data-draft-restored class="mt-3 hidden rounded-lg bg-accent-400/20 px-3 py-2 text-sm">
            {{ __('ads.post.draft_restored') }}
            <button type="button" data-draft-clear class="ml-1 font-semibold underline">{{ __('ads.post.clear_draft') }}</button>
        </p>

        <form method="post" action="{{ $ad ? lroute('account.ads.update', ['ad' => $ad->id]) : lroute('post.store', ['category' => $category->slug]) }}"
              class="mt-5 space-y-6" novalidate
              data-post-form
              data-draft-key="{{ $ad ? '' : 'iibsom-draft-'.$category->slug }}"
              data-photos='@json($photos)'
              data-upload-url="{{ lroute('post.photos.store') }}"
              data-delete-url="{{ lroute('post.photos.store') }}"
              data-max-photos="{{ $maxPhotos }}">
            @csrf
            @if ($ad) @method('put') @endif

            {{-- Bot trap: hidden from people (and screen readers); robots fill it. --}}
            @feature('safety.honeypot')
                <div class="hidden" aria-hidden="true">
                    <label for="website">Website</label>
                    <input id="website" name="website" type="text" tabindex="-1" autocomplete="off">
                </div>
            @endfeature

            {{-- Photos --}}
            <fieldset class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-stone-200">
                <legend class="sr-only">{{ __('ads.form.photos') }}</legend>
                <p class="form-label">{{ __('ads.form.photos') }}</p>
                <p class="form-help">{{ __('ads.form.photos_help', ['max' => $maxPhotos]) }}</p>

                <ul data-photo-list class="mt-3 grid grid-cols-3 gap-2 sm:grid-cols-4" aria-live="polite"></ul>

                <label class="mt-3 flex cursor-pointer items-center justify-center gap-2 rounded-xl border-2 border-dashed border-brand-200 bg-brand-50 px-4 py-4 font-semibold text-brand-700 hover:bg-brand-50/60">
                    {{ icon('camera', 'size-5') }} {{ __('ads.form.add_photos') }}
                    <input type="file" accept="image/jpeg,image/png,image/webp" multiple class="sr-only" data-photo-input>
                </label>
                <p data-photo-limit class="form-error hidden">{{ __('ads.photos.limit', ['max' => $maxPhotos]) }}</p>
                @error('photos') <p class="form-error">{{ $message }}</p> @enderror
                @error('photos.*') <p class="form-error">{{ $message }}</p> @enderror
            </fieldset>

            {{-- Main details --}}
            <fieldset class="space-y-5 rounded-2xl bg-white p-5 shadow-sm ring-1 ring-stone-200">
                <div>
                    <label for="title" class="form-label">{{ __('ads.form.title') }}</label>
                    <input id="title" name="title" type="text" required minlength="5" maxlength="80" value="{{ old('title', $ad?->title) }}" class="form-input" aria-describedby="title-help">
                    <p id="title-help" class="form-help">{{ __('ads.form.title_help') }}</p>
                    @error('title') <p class="form-error">{{ $message }}</p> @enderror
                </div>

                {{-- Custom fields for this category (cars: brand, year...) --}}
                @if ($fields->isNotEmpty())
                    <div class="grid gap-4 sm:grid-cols-2">
                        @foreach ($fields as $field)
                            @php($name = "fields[{$field->key}]")
                            @php($id = "field-{$field->key}")
                            @php($value = (string) old("fields.{$field->key}", isset($ad->fields[$field->key]) ? (is_bool($ad->fields[$field->key]) ? (int) $ad->fields[$field->key] : $ad->fields[$field->key]) : null))
                            <div>
                                <label for="{{ $id }}" class="form-label">{{ $field->label_text }}@if ($field->unit) <span class="font-normal text-stone-500">({{ $field->unit }})</span>@endif @if ($field->required)<span class="text-red-700" aria-hidden="true">*</span>@endif</label>
                                @switch($field->type)
                                    @case('select')
                                        <select id="{{ $id }}" name="{{ $name }}" class="form-input" @required($field->required)>
                                            <option value="">{{ __('ads.form.choose') }}</option>
                                            @foreach ($field->optionList() as $optValue => $optLabel)
                                                <option value="{{ $optValue }}" @selected($value === $optValue)>{{ $optLabel }}</option>
                                            @endforeach
                                        </select>
                                        @break
                                    @case('number')
                                        <input id="{{ $id }}" name="{{ $name }}" type="number" inputmode="numeric" min="0" value="{{ $value }}" class="form-input" @required($field->required)>
                                        @break
                                    @case('boolean')
                                        <select id="{{ $id }}" name="{{ $name }}" class="form-input">
                                            <option value="">{{ __('ads.form.choose') }}</option>
                                            <option value="1" @selected($value === '1')>{{ __('ads.form.yes') }}</option>
                                            <option value="0" @selected($value === '0')>{{ __('ads.form.no') }}</option>
                                        </select>
                                        @break
                                    @default
                                        <input id="{{ $id }}" name="{{ $name }}" type="text" maxlength="100" value="{{ $value }}" class="form-input" @required($field->required)>
                                @endswitch
                                @error("fields.{$field->key}") <p class="form-error">{{ $message }}</p> @enderror
                            </div>
                        @endforeach
                    </div>
                @endif

                @if ($category->has_condition)
                    <div>
                        <p class="form-label">{{ __('ads.form.condition') }}</p>
                        <div class="grid grid-cols-2 gap-2">
                            @foreach (['new', 'used'] as $condition)
                                <label class="flex cursor-pointer items-center justify-center rounded-lg border border-stone-300 px-3 py-2.5 font-medium has-checked:border-brand-700 has-checked:bg-brand-50 has-checked:text-brand-800">
                                    <input type="radio" name="condition" value="{{ $condition }}" class="sr-only" @checked(old('condition', $ad?->condition) === $condition)>
                                    {{ __('ads.form.'.$condition) }}
                                </label>
                            @endforeach
                        </div>
                        @error('condition') <p class="form-error">{{ $message }}</p> @enderror
                    </div>
                @endif

                <div>
                    <label for="description" class="form-label">{{ __('ads.form.description') }}</label>
                    <textarea id="description" name="description" rows="5" required minlength="20" maxlength="3000" class="form-input" aria-describedby="description-help">{{ old('description', $ad?->description) }}</textarea>
                    <p id="description-help" class="form-help">{{ __('ads.form.description_help') }}</p>
                    @error('description') <p class="form-error">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="price" class="form-label">{{ __('ads.form.price') }}</label>
                    <div class="flex items-center gap-3">
                        <div class="relative flex-1">
                            <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-stone-500">$</span>
                            <input id="price" name="price" type="number" inputmode="numeric" min="0" value="{{ old('price', $ad?->price) }}" class="form-input pl-7">
                        </div>
                        <label class="flex items-center gap-2 text-sm font-medium">
                            <input type="hidden" name="price_negotiable" value="0">
                            <input type="checkbox" name="price_negotiable" value="1" class="size-4 rounded border-stone-300 text-brand-700" @checked(old('price_negotiable', $ad?->price_negotiable))>
                            {{ __('ads.form.negotiable') }}
                        </label>
                    </div>
                    @error('price') <p class="form-error">{{ $message }}</p> @enderror
                    @feature('price_suggestions')
                        <div data-price-hint data-url="{{ lroute('post.price_hint', ['category' => $category->slug]) }}" @if ($ad) data-except="{{ $ad->id }}" @endif
                             class="mt-3 hidden rounded-xl bg-brand-50 px-4 py-3 text-sm text-brand-900 ring-1 ring-brand-200" aria-live="polite">
                            <p class="flex items-center gap-2 font-semibold">{{ icon('chart-column', 'size-4 shrink-0') }} {{ __('ads.price_hint.title') }}</p>
                            <p data-price-hint-range class="mt-1"></p>
                            <p data-price-hint-detail class="mt-0.5 text-xs text-brand-800"></p>
                        </div>
                    @endfeature
                </div>
            </fieldset>

            {{-- Location --}}
            <fieldset class="grid gap-4 rounded-2xl bg-white p-5 shadow-sm ring-1 ring-stone-200 sm:grid-cols-2">
                <div>
                    <label for="city_id" class="form-label">{{ __('ads.form.city') }}</label>
                    <select id="city_id" name="city_id" class="form-input" required data-city-select>
                        @foreach ($cities as $city)
                            <option value="{{ $city['id'] }}" @selected((int) old('city_id', $ad?->city_id ?? auth()->user()->city_id) === $city['id'])>{{ translated($city['name']) }}</option>
                        @endforeach
                    </select>
                    @error('city_id') <p class="form-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="area_id" class="form-label">{{ __('ads.form.area') }}</label>
                    <select id="area_id" name="area_id" class="form-input" data-area-select data-areas='@json($areas)' data-selected="{{ old('area_id', $ad?->area_id) }}">
                        <option value="">{{ __('ads.form.area_any') }}</option>
                    </select>
                    @error('area_id') <p class="form-error">{{ $message }}</p> @enderror
                </div>
            </fieldset>

            <div class="sticky bottom-16 z-10 -mx-4 bg-stone-50/95 px-4 py-3 backdrop-blur lg:bottom-0">
                <button type="submit" class="btn-sell w-full py-3 text-base">{{ $ad ? __('ads.mine.save') : __('ads.post.submit') }}</button>
                <p data-draft-status class="mt-1 hidden text-center text-xs text-stone-500">{{ __('ads.post.draft_saved') }}</p>
            </div>
        </form>

        {{-- Texts for the script --}}
        <template data-i18n
                  data-uploading="{{ __('ads.photos.uploading') }}"
                  data-failed="{{ __('ads.photos.failed') }}"
                  data-remove="{{ __('ads.photos.remove') }}"
                  data-left="{{ __('ads.photos.move_left') }}"
                  data-right="{{ __('ads.photos.move_right') }}"
                  data-cover="{{ __('ads.photos.cover') }}"></template>
    </section>
@endsection
