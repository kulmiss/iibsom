@extends('layouts.app')

@section('title', __('account.login.register_title'))

@section('content')
    <section class="mx-auto max-w-md px-4 py-10">
        <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-stone-200 sm:p-8">
            <h1 class="text-2xl font-bold">{{ __('account.login.register_title') }}</h1>
            <p class="mt-2 text-stone-600">{{ __('account.login.register_intro') }}</p>

            @if (\App\Support\Auth\SignIn::googleReady())
                <a href="{{ route('auth.google', ['lang' => app()->getLocale()]) }}" data-google-button class="mt-5 flex w-full items-center justify-center gap-2 rounded-lg border border-stone-300 bg-white px-4 py-2.5 font-semibold text-stone-800 hover:bg-stone-50">{{ __('account.login.google') }}</a>
                <p class="my-5 flex items-center gap-3 text-xs uppercase text-stone-500" aria-hidden="true"><span class="h-px flex-1 bg-stone-200"></span>{{ __('account.login.or') }}<span class="h-px flex-1 bg-stone-200"></span></p>
            @endif

            <form method="post" action="{{ lroute('register.store') }}" class="mt-5 space-y-3" data-register novalidate>
                @csrf
                @include('auth._honeypot', ['id' => 'r'])
                @foreach ([['name', 'text', 'name', 80], ['email', 'email', 'email', 120]] as [$field, $type, $auto, $max])
                    <div>
                        <label for="reg-{{ $field }}" class="form-label">{{ __('account.login.'.$field) }}</label>
                        <input id="reg-{{ $field }}" name="{{ $field }}" type="{{ $type }}" required maxlength="{{ $max }}" autocomplete="{{ $auto }}" value="{{ old($field) }}" class="form-input" @error($field) aria-invalid="true" aria-describedby="reg-{{ $field }}-error" @enderror>
                        @error($field)<p id="reg-{{ $field }}-error" class="form-error mt-1">{{ $message }}</p>@enderror
                    </div>
                @endforeach
                <div>
                    <label for="reg-password" class="form-label">{{ __('account.login.password') }}</label>
                    <input id="reg-password" name="password" type="password" required minlength="8" maxlength="100" autocomplete="new-password" class="form-input" aria-describedby="reg-password-hint @error('password') reg-password-error @enderror" @error('password') aria-invalid="true" @enderror>
                    <p id="reg-password-hint" class="mt-1 text-xs text-stone-500">{{ __('account.login.password_hint') }}</p>
                    @error('password')<p id="reg-password-error" class="form-error mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="reg-password-confirm" class="form-label">{{ __('account.login.password_confirm') }}</label>
                    <input id="reg-password-confirm" name="password_confirmation" type="password" required maxlength="100" autocomplete="new-password" class="form-input">
                </div>
                <button class="btn-primary w-full justify-center">{{ __('account.login.register_button') }}</button>
            </form>
            <p class="mt-4 text-center text-xs text-stone-500">{{ __('account.login.terms_note') }}</p>
            <p class="mt-3 text-center text-sm text-stone-600">{{ __('account.login.have_account') }} <a href="{{ lroute('login') }}" class="font-semibold text-brand-700 hover:underline">{{ __('account.login.sign_in') }}</a></p>
        </div>
    </section>
@endsection
