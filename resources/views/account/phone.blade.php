@extends('layouts.app')

@section('title', __('account.phone.title'))

@section('content')
    {{-- "Confirm my number" (PR 108): no SMS. The seller sends a code by WhatsApp; staff tick it in the admin. --}}
    <section class="mx-auto max-w-xl px-4 py-8">
        <h1 class="text-2xl font-bold">{{ __('account.phone.title') }}</h1>
        <p class="mt-1 text-stone-600">{{ \App\Support\Phone\SomaliPhone::format((string) $user->phone) }}</p>

        @if ($user->phone_confirmed_at !== null)
            <p class="mt-4 flex items-center gap-2 rounded-xl bg-emerald-50 p-4 text-emerald-900 ring-1 ring-emerald-200" data-phone-confirmed>
                {{ icon('phone', 'size-5 shrink-0') }} {{ __('account.phone.done') }}
            </p>
            <p class="mt-3 text-sm text-stone-600">{{ __('account.phone.change_note') }}</p>
        @else
            <p class="mt-3 text-stone-700">{{ __('account.phone.intro') }}</p>

            @if ($code)
                <div class="mt-5 rounded-2xl bg-white p-5 text-center shadow-sm ring-1 ring-stone-200" data-phone-code>
                    <p class="text-sm text-stone-600">{{ __('account.phone.your_code') }}</p>
                    <p class="mt-1 select-all font-mono text-3xl font-bold tracking-widest text-brand-800">{{ $code }}</p>
                    @if ($whatsapp)
                        @php($text = __('account.phone.whatsapp_text', ['code' => $code]))
                        <a href="https://wa.me/{{ $whatsapp }}?text={{ rawurlencode($text) }}" target="_blank" rel="noopener" class="btn-primary mt-4 w-full justify-center" data-phone-whatsapp>
                            {{ icon('message-circle', 'size-5') }} {{ __('account.phone.open_whatsapp') }}
                        </a>
                        <p class="mt-3 text-xs text-stone-500">{{ __('account.phone.whatsapp_note') }}</p>
                    @else
                        <p class="mt-3 text-sm text-stone-600">{{ __('account.phone.no_whatsapp') }}</p>
                    @endif
                </div>
            @else
                <form method="post" action="{{ lroute('account.phone.code') }}" class="mt-5">
                    @csrf
                    <button class="btn-primary w-full justify-center py-3" data-phone-get-code>{{ __('account.phone.get_code') }}</button>
                </form>
            @endif
        @endif

        <details class="mt-6 rounded-xl bg-white p-4 text-sm ring-1 ring-stone-200">
            <summary class="cursor-pointer font-medium text-brand-800">{{ __('account.phone.what_title') }}</summary>
            <p class="mt-2 text-stone-700">{{ __('account.phone.what_body') }}</p>
        </details>
    </section>
@endsection
