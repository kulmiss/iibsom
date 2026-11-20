@extends('layouts.app')

@section('title', __('account.login.reset_title'))

@section('content')
    {{-- No index, no cache: the address holds a one-time token. --}}
    @push('head')<meta name="robots" content="noindex">@endpush
    <section class="mx-auto max-w-md px-4 py-10">
        <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-stone-200 sm:p-8">
            <h1 class="text-2xl font-bold">{{ __('account.login.reset_title') }}</h1>
            <form method="post" action="{{ route('password.update') }}" class="mt-5 space-y-3" data-reset novalidate>
                @csrf
                <input type="hidden" name="token" value="{{ $token }}">
                <input type="hidden" name="lang" value="{{ app()->getLocale() }}">
                <div>
                    <label for="reset-email" class="form-label">{{ __('account.login.email') }}</label>
                    <input id="reset-email" name="email" type="email" required maxlength="120" autocomplete="email" value="{{ old('email', $email) }}" class="form-input" @error('email') aria-invalid="true" aria-describedby="reset-email-error" @enderror>
                    @error('email')<p id="reset-email-error" class="form-error mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="reset-password" class="form-label">{{ __('account.login.password') }}</label>
                    <input id="reset-password" name="password" type="password" required minlength="8" maxlength="100" autocomplete="new-password" class="form-input" aria-describedby="reset-password-hint @error('password') reset-password-error @enderror" @error('password') aria-invalid="true" @enderror>
                    <p id="reset-password-hint" class="mt-1 text-xs text-stone-500">{{ __('account.login.password_hint') }}</p>
                    @error('password')<p id="reset-password-error" class="form-error mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="reset-password-confirm" class="form-label">{{ __('account.login.password_confirm') }}</label>
                    <input id="reset-password-confirm" name="password_confirmation" type="password" required maxlength="100" autocomplete="new-password" class="form-input">
                </div>
                <button class="btn-primary w-full justify-center">{{ __('account.login.reset_button') }}</button>
            </form>
        </div>
    </section>
@endsection
