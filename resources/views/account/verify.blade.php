@extends('layouts.app')

@section('title', __('account.verify.title'))

@section('content')
    <section class="mx-auto max-w-xl px-4 py-8">
        <h1 class="text-2xl font-bold">{{ __('account.verify.title') }}</h1>

        @if ($verified)
            <p class="mt-4 flex items-center gap-2 rounded-xl bg-green-50 p-4 text-green-900 ring-1 ring-green-200">
                {{ icon('badge-check', 'size-5 shrink-0') }} {{ __('account.verify.verified') }}
            </p>
        @elseif ($latest?->status === 'pending')
            <p class="mt-4 rounded-xl bg-brand-50 p-4 text-brand-900 ring-1 ring-brand-200">{{ __('account.verify.pending') }}</p>
        @else
            <p class="mt-2 text-stone-600">{{ __('account.verify.intro') }}</p>
            {{-- Price (PR 107): always free for people; a business (seller with a shop) pays only if the owner set a price. --}}
            <p class="mt-3 flex items-start gap-2 rounded-xl bg-white p-3 text-sm text-stone-700 ring-1 ring-stone-200" data-verify-price>
                {{ icon('badge-check', 'size-4 mt-0.5 shrink-0 text-brand-700') }}
                <span>{{ $businessPrice > 0 && $isBusiness ? __('account.verify.price_business', ['price' => '$'.number_format($businessPrice)]) : __('account.verify.free') }}</span>
            </p>
            @if ($latest?->status === 'expired')
                <p class="mt-4 rounded-xl bg-amber-50 p-4 text-sm text-amber-900 ring-1 ring-amber-200" data-verify-expired>{{ __('account.verify.expired') }}</p>
            @endif
            @if ($latest?->status === 'rejected')
                <p class="mt-4 rounded-xl bg-red-50 p-4 text-sm text-red-900 ring-1 ring-red-200">
                    {{ __('account.verify.rejected', ['reason' => $latest->reason]) }}<br>{{ __('account.verify.try_again') }}
                </p>
            @endif

            <form method="post" action="{{ lroute('verify.store') }}" enctype="multipart/form-data" class="mt-6 space-y-5 rounded-2xl bg-white p-5 shadow-sm ring-1 ring-stone-200" novalidate>
                @csrf
                <div>
                    <label for="id_type" class="form-label">{{ __('account.verify.id_type') }}</label>
                    <select id="id_type" name="id_type" required class="form-input">
                        @foreach (['national_id' => __('account.verify.national_id'), 'passport' => __('account.verify.passport'), 'driving_licence' => __('account.verify.driving_licence')] as $value => $label)
                            <option value="{{ $value }}" @selected(old('id_type') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('id_type') <p class="form-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="photo" class="form-label">{{ __('account.verify.photo') }}</label>
                    <input id="photo" name="photo" type="file" accept="image/jpeg,image/png,image/webp" required class="form-input" aria-describedby="photo-help">
                    <p id="photo-help" class="form-help">{{ __('account.verify.photo_help') }}</p>
                    @error('photo') <p class="form-error">{{ $message }}</p> @enderror
                </div>
                <label class="flex items-start gap-2 text-sm">
                    <input type="checkbox" name="agree" value="1" required class="mt-1">
                    <span>{{ __('account.verify.agree') }}</span>
                </label>
                @error('agree') <p class="form-error">{{ $message }}</p> @enderror
                <button type="submit" class="btn-sell w-full py-3">{{ __('account.verify.send') }}</button>
            </form>
        @endif

        <p class="mt-6 text-xs text-stone-500">{{ __('account.verify.privacy') }}</p>
    </section>
@endsection
