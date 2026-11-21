@extends('layouts.app')

@section('title', $welcome ? __('account.welcome.title') : __('account.profile.title'))

@section('content')
    <section class="mx-auto max-w-md px-4 py-8">
        <h1 class="text-2xl font-bold">{{ $welcome ? __('account.welcome.title') : __('account.profile.title') }}</h1>
        @if ($welcome)
            <p class="mt-1 text-stone-600">{{ __('account.welcome.intro') }}</p>
        @endif

        <form method="post" action="{{ $welcome ? lroute('welcome.save') : lroute('account.profile.save') }}" class="mt-6 space-y-5 rounded-2xl bg-white p-5 shadow-sm ring-1 ring-stone-200" novalidate>
            @csrf

            <div>
                <label for="name" class="form-label">{{ __('account.form.name') }}</label>
                <input id="name" name="name" type="text" autocomplete="name" required maxlength="80"
                       value="{{ old('name', $user->name) }}" class="form-input" aria-describedby="name-help">
                <p id="name-help" class="form-help">{{ __('account.form.name_help') }}</p>
                @error('name') <p class="form-error">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="city_id" class="form-label">{{ __('account.form.city') }}</label>
                <select id="city_id" name="city_id" required class="form-input">
                    @foreach ($cities as $city)
                        <option value="{{ $city['id'] }}" @selected((int) old('city_id', $user->city_id ?? (count($cities) === 1 ? $city['id'] : null)) === $city['id'])>{{ translated($city['name']) }}</option>
                    @endforeach
                </select>
                @error('city_id') <p class="form-error">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="phone" class="form-label">{{ __('account.form.phone') }}</label>
                <div class="flex">
                    <span class="inline-flex items-center rounded-l-lg border border-r-0 border-stone-300 bg-stone-100 px-3 text-stone-600">+252</span>
                    <input id="phone" name="phone" type="tel" inputmode="tel" autocomplete="tel-national" required maxlength="25"
                           value="{{ old('phone', $user->phone ? substr($user->phone, 4) : '') }}" placeholder="61 5123456"
                           class="form-input rounded-l-none" aria-describedby="phone-help">
                </div>
                <p id="phone-help" class="form-help">{{ __('account.form.phone_help') }}</p>
                @if (feature('sellers.phone_confirmed') && ($user->phone_confirmed_at ?? null))
                    <p class="form-help text-amber-800" data-phone-change-note>{{ __('account.phone.change_note') }}</p>
                @endif
                @error('phone') <p class="form-error">{{ $message }}</p> @enderror

                <label class="mt-3 flex items-center gap-2 text-sm">
                    <input type="hidden" name="phone_has_whatsapp" value="0">
                    <input type="checkbox" name="phone_has_whatsapp" value="1" class="size-4 rounded border-stone-300 text-brand-700"
                           @checked(old('phone_has_whatsapp', $user->phone_has_whatsapp ?? true))>
                    {{ __('account.form.whatsapp') }}
                </label>
            </div>

            @if ($welcome)
                <div>
                    <label class="flex items-start gap-2 text-sm">
                        <input type="checkbox" name="terms" value="1" required class="mt-0.5 size-4 rounded border-stone-300 text-brand-700" @checked(old('terms'))>
                        <span>{!! __('account.form.terms', [
                            'terms' => '<a href="'.e(lroute('page', ['slug' => 'terms'])).'" target="_blank" rel="noopener" class="font-medium text-brand-700 underline">'.e(__('account.form.terms_link')).'</a>',
                            'privacy' => '<a href="'.e(lroute('page', ['slug' => 'privacy'])).'" target="_blank" rel="noopener" class="font-medium text-brand-700 underline">'.e(__('account.form.privacy_link')).'</a>',
                        ]) !!}</span>
                    </label>
                    @error('terms') <p class="form-error">{{ $message }}</p> @enderror
                </div>
            @endif

            <button type="submit" class="btn-primary w-full justify-center py-3 text-base">
                {{ $welcome ? __('account.welcome.submit') : __('account.profile.submit') }}
            </button>
        </form>
    </section>
@endsection
