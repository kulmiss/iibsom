@extends('layouts.app')

@section('title', __('account.login.title'))

@section('content')
    <section class="mx-auto max-w-md px-4 py-10">
        <div class="rounded-2xl bg-white p-6 text-center shadow-sm ring-1 ring-stone-200 sm:p-8">
            <span class="mx-auto flex size-14 items-center justify-center rounded-full bg-brand-50 text-brand-700">{{ icon('user', 'size-7') }}</span>
            <h1 class="mt-4 text-2xl font-bold">{{ __('account.login.heading') }}</h1>
            <p class="mt-2 text-stone-600">{{ __('account.login.intro') }}</p>

            <a href="{{ route('auth.google', ['lang' => app()->getLocale()]) }}"
               class="mt-6 flex w-full items-center justify-center gap-3 rounded-lg border border-stone-300 bg-white px-4 py-3 font-semibold text-stone-800 shadow-sm hover:bg-stone-50 focus-visible:outline-2 focus-visible:outline-brand-700">
                <svg aria-hidden="true" viewBox="0 0 48 48" class="size-5"><path fill="#FFC107" d="M43.6 20.5H42V20H24v8h11.3C33.7 32.7 29.2 36 24 36c-6.6 0-12-5.4-12-12s5.4-12 12-12c3.1 0 5.8 1.2 7.9 3.1l5.7-5.7C34.1 6.1 29.3 4 24 4 12.9 4 4 12.9 4 24s8.9 20 20 20 20-8.9 20-20c0-1.3-.1-2.4-.4-3.5z"/><path fill="#FF3D00" d="m6.3 14.7 6.6 4.8C14.7 15.1 19 12 24 12c3.1 0 5.8 1.2 7.9 3.1l5.7-5.7C34.1 6.1 29.3 4 24 4 16.3 4 9.7 8.3 6.3 14.7z"/><path fill="#4CAF50" d="M24 44c5.2 0 9.9-2 13.4-5.2l-6.2-5.2C29.2 35.1 26.7 36 24 36c-5.2 0-9.6-3.3-11.3-8l-6.5 5C9.5 39.6 16.2 44 24 44z"/><path fill="#1976D2" d="M43.6 20.5H42V20H24v8h11.3c-.8 2.2-2.2 4.2-4.1 5.6l6.2 5.2C37 39.2 44 34 44 24c0-1.3-.1-2.4-.4-3.5z"/></svg>
                {{ __('account.login.google') }}
            </a>

            <p class="mt-4 text-xs text-stone-500">{{ __('account.login.no_password') }}</p>
            <p class="mt-1 text-xs text-stone-500">{{ __('account.login.terms_note') }}</p>
        </div>

        @if (\App\Http\Controllers\Auth\TestSignInController::available())
            {{-- Owner's own computer only (APP_ENV=local): sign in without Google keys. --}}
            <form method="post" action="{{ lroute('login.test') }}" class="mt-4 rounded-2xl border-2 border-dashed border-amber-300 bg-amber-50 p-5">
                @csrf
                <p class="font-semibold text-amber-900">{{ icon('flask-conical', 'size-4 me-1 inline-block align-[-2px]') }}{{ __('account.login.test_title') }}</p>
                <p class="mt-1 text-sm text-amber-900">{{ __('account.login.test_note') }}</p>
                <label for="test-email" class="form-label mt-3">{{ __('account.login.test_email') }}</label>
                <input id="test-email" name="email" type="email" required maxlength="120" autocomplete="email" value="{{ old('email') }}" class="form-input" @error('email') aria-invalid="true" aria-describedby="test-email-error" @enderror>
                @error('email')<p id="test-email-error" class="form-error mt-1">{{ $message }}</p>@enderror
                <button class="btn-primary mt-3 w-full justify-center">{{ __('account.login.test_button') }}</button>
            </form>
        @endif
    </section>
@endsection
