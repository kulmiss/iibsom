@extends('layouts.app')

@section('title', __('ads.boost.title'))

@section('content')
    <section class="mx-auto max-w-xl px-4 py-8">
        <a href="{{ lroute('account.ads') }}" class="text-sm text-brand-700 hover:underline">← {{ __('account.account.my_ads') }}</a>
        <h1 class="mt-2 text-2xl font-bold">{{ __('ads.boost.title') }}</h1>
        <p class="mt-1 truncate text-stone-600">{{ $ad->title }}</p>

        @if ($testMode)
            <p class="mt-4 rounded-xl bg-amber-100 p-4 text-sm font-semibold text-amber-950 ring-1 ring-amber-300" role="alert">{{ __('ads.boost.test_mode') }}</p>
        @endif

        @if ($ad->activeBoost())
            <p class="mt-4 rounded-xl bg-brand-50 p-4 text-sm text-brand-900 ring-1 ring-brand-200">
                {{ __('ads.boost.boosted_until', ['level' => \App\Support\Money\Boosts::label($ad->activeBoost()), 'date' => mdate($ad->boosted_until)]) }}
            </p>
        @endif

        @if ($pending)
            <p class="mt-4 rounded-xl bg-brand-50 p-4 text-brand-900 ring-1 ring-brand-200">{{ __('ads.boost.pending') }}</p>
        @elseif (! $ad->isLive() || $methods === [])
            <p class="mt-4 rounded-xl bg-stone-100 p-4 text-stone-700">{{ __('ads.boost.not_available') }}</p>
        @else
            <p class="mt-2 text-stone-600">{{ __('ads.boost.intro') }}</p>

            <form method="post" action="{{ lroute('account.boost.store', ['ad' => $ad->id]) }}" enctype="multipart/form-data" class="mt-6 space-y-6" novalidate>
                @csrf
                <fieldset>
                    <legend class="form-label">{{ __('ads.boost.package') }}</legend>
                    <div class="mt-2 grid gap-2 sm:grid-cols-3">
                        @foreach ($packages as $key => $p)
                            <label class="cursor-pointer rounded-xl bg-white p-4 ring-1 ring-stone-200 has-checked:bg-brand-50 has-checked:ring-2 has-checked:ring-brand-600">
                                <input type="radio" name="package" value="{{ $key }}" class="sr-only" @checked(old('package', 'vip') === $key) required>
                                <span class="block font-bold">{{ \App\Support\Money\Boosts::label($key) }}</span>
                                <span class="block text-lg font-bold text-brand-800">{{ \App\Models\Payment::money($p['price_cents']) }}</span>
                                <span class="block text-sm text-stone-600">{{ trans_choice('ads.boost.days', $p['days'], ['count' => $p['days']]) }}</span>
                            </label>
                        @endforeach
                    </div>
                    @error('package') <p class="form-error">{{ $message }}</p> @enderror
                </fieldset>

                <fieldset>
                    <legend class="form-label">{{ __('ads.boost.method') }}</legend>
                    <p class="text-sm text-stone-600">{{ __('ads.boost.how_to') }}</p>
                    <div class="mt-2 space-y-2">
                        @foreach ($methods as $key => $m)
                            <label class="flex cursor-pointer items-start gap-3 rounded-xl bg-white p-3 ring-1 ring-stone-200 has-checked:ring-2 has-checked:ring-brand-600">
                                <input type="radio" name="method" value="{{ $key }}" class="mt-1" @checked(old('method', array_key_first($methods)) === $key) required>
                                <span>
                                    <span class="block font-semibold">{{ $m['label'] }}</span>
                                    <span dir="ltr" class="block font-mono text-sm font-semibold tracking-wide text-stone-700">{{ $m['number'] }}</span>
                                </span>
                            </label>
                        @endforeach
                    </div>
                    @error('method') <p class="form-error">{{ $message }}</p> @enderror
                </fieldset>

                <div class="space-y-5 rounded-2xl bg-white p-5 shadow-sm ring-1 ring-stone-200">
                    <div>
                        <label for="reference" class="form-label">{{ __('ads.boost.reference') }}</label>
                        <input id="reference" name="reference" type="text" required maxlength="40" autocapitalize="characters" spellcheck="false" value="{{ old('reference') }}" class="form-input font-mono">
                        @error('reference') <p class="form-error">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="receipt" class="form-label">{{ __('ads.boost.receipt') }}</label>
                        <input id="receipt" name="receipt" type="file" accept="image/jpeg,image/png,image/webp" required class="form-input">
                        @error('receipt') <p class="form-error">{{ $message }}</p> @enderror
                    </div>
                    <button type="submit" class="btn-sell w-full py-3">{{ icon('rocket', 'size-5') }} <span class="ms-1">{{ __('ads.boost.send') }}</span></button>
                </div>
            </form>
        @endif
    </section>
@endsection
