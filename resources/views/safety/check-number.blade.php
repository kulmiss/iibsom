@extends('layouts.app')

@section('title', __('scam.title'))
@section('description', __('scam.description'))

@push('head')<meta name="robots" content="noindex">@endpush

@section('content')
    {{-- "Check a phone number" (PR 109): only "reported for scams" or "no report". Never a name. --}}
    <section class="mx-auto max-w-xl px-4 py-8">
        <h1 class="text-2xl font-bold">{{ __('scam.title') }}</h1>
        <p class="mt-1 text-stone-600">{{ __('scam.intro') }}</p>

        <form method="get" action="{{ lroute('scam.check') }}" class="mt-5 flex gap-2" role="search" data-scam-form>
            <label for="scam-phone" class="sr-only">{{ __('scam.phone') }}</label>
            <input id="scam-phone" name="phone" type="tel" inputmode="tel" maxlength="30" required value="{{ $raw }}" placeholder="61 5123456" class="form-input min-w-0 flex-1">
            <button class="btn-primary shrink-0">{{ __('scam.check') }}</button>
        </form>

        @if ($raw !== '' && $phone === null)
            <p class="mt-4 rounded-xl bg-stone-100 p-4 text-sm text-stone-700" role="status">{{ __('scam.not_a_number') }}</p>
        @elseif ($listed)
            <div class="mt-4 rounded-xl bg-red-50 p-4 text-red-900 ring-1 ring-red-200" role="alert" data-scam-warning>
                <p class="flex items-center gap-2 font-bold">{{ icon('triangle-alert', 'size-5 shrink-0') }} {{ __('scam.listed_title', ['phone' => \App\Support\Phone\SomaliPhone::format($phone)]) }}</p>
                <p class="mt-1 text-sm">{{ __('scam.listed_body') }}</p>
            </div>

            <details class="mt-6 rounded-xl bg-white p-4 ring-1 ring-stone-200" @if ($errors->any()) open @endif data-scam-appeal>
                <summary class="cursor-pointer font-medium text-brand-800">{{ __('scam.appeal_title') }}</summary>
                @if ($waiting)
                    <p class="mt-3 text-sm text-stone-700" role="status">{{ __('scam.appeal_waiting') }}</p>
                @else
                    <form method="post" action="{{ lroute('scam.appeal') }}" class="mt-3 space-y-3">
                        @csrf
                        @include('auth._honeypot', ['id' => 's'])
                        <input type="hidden" name="phone" value="{{ $phone }}">
                        <p class="text-sm text-stone-600">{{ __('scam.appeal_intro') }}</p>
                        <div>
                            <label for="appeal-message" class="form-label">{{ __('scam.appeal_message') }}</label>
                            <textarea id="appeal-message" name="message" rows="4" required minlength="10" maxlength="1000" class="form-input" @error('message') aria-invalid="true" aria-describedby="appeal-message-error" @enderror>{{ old('message') }}</textarea>
                            @error('message')<p id="appeal-message-error" class="form-error mt-1">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="appeal-contact" class="form-label">{{ __('scam.appeal_contact') }}</label>
                            <input id="appeal-contact" name="contact" maxlength="120" value="{{ old('contact') }}" class="form-input" aria-describedby="appeal-contact-help">
                            <p id="appeal-contact-help" class="form-help">{{ __('scam.appeal_contact_help') }}</p>
                        </div>
                        <button class="btn-primary w-full justify-center">{{ __('scam.appeal_send') }}</button>
                    </form>
                @endif
            </details>
        @elseif ($phone !== null)
            <div class="mt-4 rounded-xl bg-emerald-50 p-4 text-emerald-900 ring-1 ring-emerald-200" role="status" data-scam-clear>
                <p class="font-semibold">{{ __('scam.clear_title') }}</p>
                <p class="mt-1 text-sm">{{ __('scam.clear_body') }}</p>
            </div>
        @endif

        <ul class="mt-6 list-disc space-y-1 ps-5 text-sm text-stone-700">
            <li>{{ __('ads.page.safety_1') }}</li>
            <li>{{ __('ads.page.safety_2') }}</li>
            <li>{{ __('ads.page.safety_3') }}</li>
        </ul>
    </section>
@endsection
