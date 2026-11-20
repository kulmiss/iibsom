@extends('layouts.app')

@section('title', __('account.login.forgot_title'))

@section('content')
    <section class="mx-auto max-w-md px-4 py-10">
        <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-stone-200 sm:p-8">
            <h1 class="text-2xl font-bold">{{ __('account.login.forgot_title') }}</h1>
            <p class="mt-2 text-stone-600">{{ __('account.login.forgot_intro') }}</p>
            <form method="post" action="{{ lroute('password.email') }}" class="mt-5 space-y-3" data-forgot novalidate>
                @csrf
                <div>
                    <label for="forgot-email" class="form-label">{{ __('account.login.email') }}</label>
                    <input id="forgot-email" name="email" type="email" required maxlength="120" autocomplete="email" inputmode="email" value="{{ old('email') }}" class="form-input" @error('email') aria-invalid="true" aria-describedby="forgot-email-error" @enderror>
                    @error('email')<p id="forgot-email-error" class="form-error mt-1">{{ $message }}</p>@enderror
                </div>
                <button class="btn-primary w-full justify-center">{{ __('account.login.forgot_button') }}</button>
            </form>
            <p class="mt-4 text-center text-sm"><a href="{{ lroute('login') }}" class="font-semibold text-brand-700 hover:underline">{{ __('account.login.back') }}</a></p>
        </div>
    </section>
@endsection
