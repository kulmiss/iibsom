@extends('layouts.app')

@section('title', __('account.shop.title'))

@section('content')
    <section class="mx-auto max-w-xl px-4 py-8">
        <h1 class="text-2xl font-bold">{{ __('account.shop.title') }}</h1>
        <p class="mt-1 text-stone-600">{{ __('account.shop.intro') }}</p>

        @if ($shop->exists)
            @if ($shop->isActive())
                <p class="mt-4 rounded-xl bg-green-50 p-4 text-sm text-green-900 ring-1 ring-green-200">
                    {{ __('account.shop.active_until', ['date' => $shop->active_until->translatedFormat('j M Y')]) }}
                    <a href="{{ $shop->url() }}" class="font-semibold underline">{{ __('account.shop.view') }}</a>
                </p>
            @else
                <p class="mt-4 rounded-xl bg-amber-50 p-4 text-sm text-amber-900 ring-1 ring-amber-200">{{ __('account.shop.not_active') }}</p>
            @endif
        @endif

        <form method="post" action="{{ lroute('account.shop.save') }}" enctype="multipart/form-data" class="mt-6 space-y-5 rounded-2xl bg-white p-5 shadow-sm ring-1 ring-stone-200" novalidate>
            @csrf
            <div>
                <label for="name" class="form-label">{{ __('account.shop.name') }}</label>
                <input id="name" name="name" type="text" required maxlength="80" value="{{ old('name', $shop->name) }}" class="form-input">
                @error('name') <p class="form-error">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="slug" class="form-label">{{ __('account.shop.slug') }}</label>
                <input id="slug" name="slug" type="text" required maxlength="40" pattern="[a-z0-9]+(-[a-z0-9]+)*" autocapitalize="none" spellcheck="false"
                       value="{{ old('slug', $shop->slug) }}" class="form-input" aria-describedby="slug-help">
                <p id="slug-help" class="form-help break-all">{{ __('account.shop.slug_help', ['url' => url('/shop').'/…']) }}</p>
                @error('slug') <p class="form-error">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="city_id" class="form-label">{{ __('account.form.city') }}</label>
                <select id="city_id" name="city_id" class="form-input">
                    <option value="">—</option>
                    @foreach ($cities as $city)
                        <option value="{{ $city['id'] }}" @selected((int) old('city_id', $shop->city_id) === $city['id'])>{{ translated($city['name']) }}</option>
                    @endforeach
                </select>
                @error('city_id') <p class="form-error">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="hours" class="form-label">{{ __('account.shop.hours') }}</label>
                <input id="hours" name="hours" type="text" maxlength="100" value="{{ old('hours', $shop->hours) }}" placeholder="{{ __('account.shop.hours_placeholder') }}" class="form-input">
                @error('hours') <p class="form-error">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="description" class="form-label">{{ __('account.shop.description') }}</label>
                <textarea id="description" name="description" rows="4" maxlength="1000" class="form-input">{{ old('description', $shop->description) }}</textarea>
                @error('description') <p class="form-error">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="logo" class="form-label">{{ __('account.shop.logo') }}</label>
                @if ($shop->logoUrl())
                    <img src="{{ $shop->logoUrl() }}" alt="" width="64" height="64" class="mb-2 size-16 rounded-xl object-cover ring-1 ring-stone-200">
                @endif
                <input id="logo" name="logo" type="file" accept="image/jpeg,image/png,image/webp" class="form-input" aria-describedby="logo-help">
                <p id="logo-help" class="form-help">{{ __('account.shop.logo_help') }}</p>
                @error('logo') <p class="form-error">{{ $message }}</p> @enderror
            </div>
            <button type="submit" class="btn-sell w-full py-3">{{ __('account.shop.save') }}</button>
        </form>
    </section>
@endsection
