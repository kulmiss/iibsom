@extends('layouts.app')

@section('title', __('site.contact.title'))

@php($sent = request()->query('sent'))
@php($errors ??= new \Illuminate\Support\ViewErrorBag)

@section('content')
    <section class="mx-auto max-w-xl px-4 py-8">
        <h1 class="text-2xl font-bold">{{ __('site.contact.title') }}</h1>
        <p class="mt-1 text-stone-600">{{ __('site.contact.intro') }}</p>
        @feature('help.centre')
            <p class="mt-2 text-sm">{{ __('site.contact.help_first') }} <a href="{{ lroute('help') }}" class="font-medium text-brand-700 underline">{{ __('site.contact.help_link') }}</a></p>
        @endfeature

        @if ($sent === 'thanks')
            <p role="status" class="mt-5 rounded-lg bg-emerald-50 px-4 py-3 text-sm text-emerald-800 ring-1 ring-emerald-200" data-contact-sent>{{ __('site.contact.thanks') }}</p>
        @endif

        <form method="post" action="{{ lroute('contact.send') }}" class="mt-5 space-y-4 rounded-2xl bg-white p-5 shadow-sm ring-1 ring-stone-200" data-guest-report="{{ lroute('ad.report_token') }}" data-contact-form novalidate>
            @if ($errors->any())
                <p role="alert" class="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-800 ring-1 ring-red-200">{{ __('site.contact.fix') }}</p>
            @endif
            <div>
                <label for="contact-name" class="form-label">{{ __('site.contact.name') }}</label>
                <input id="contact-name" name="name" maxlength="60" required autocomplete="name" value="{{ $old['name'] ?? '' }}" class="form-input" @error('name') aria-invalid="true" aria-describedby="contact-name-error" @enderror>
                @error('name')<p id="contact-name-error" class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="contact-email" class="form-label">{{ __('site.contact.email') }}</label>
                <input id="contact-email" name="email" type="email" maxlength="120" required autocomplete="email" value="{{ $old['email'] ?? '' }}" class="form-input" aria-describedby="contact-email-help @error('email') contact-email-error @enderror" @error('email') aria-invalid="true" @enderror>
                <p id="contact-email-help" class="form-help">{{ __('site.contact.email_help') }}</p>
                @error('email')<p id="contact-email-error" class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="contact-topic" class="form-label">{{ __('site.contact.topic') }}</label>
                <select id="contact-topic" name="topic" required class="form-input">
                    @foreach (\App\Models\ContactMessage::TOPICS as $topic)
                        <option value="{{ $topic }}" @selected(($old['topic'] ?? '') === $topic)>{{ __('site.contact.topics.'.$topic) }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="contact-message" class="form-label">{{ __('site.contact.message') }}</label>
                <textarea id="contact-message" name="message" rows="6" minlength="10" maxlength="2000" required class="form-input" @error('message') aria-invalid="true" aria-describedby="contact-message-error" @enderror>{{ $old['message'] ?? '' }}</textarea>
                @error('message')<p id="contact-message-error" class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
            </div>
            @if ($check || \App\Support\Safety\GuestReports::underAttack())
                @php([$sumA, $sumB] = \App\Support\Safety\GuestReports::sum($token))
                <div>
                    <label for="report-sum" class="form-label">{{ __('ads.page.report_sum', ['a' => $sumA, 'b' => $sumB]) }}</label>
                    <input id="report-sum" name="sum" type="text" inputmode="numeric" maxlength="2" required class="form-input w-20">
                    @error('sum')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
                </div>
            @endif
            <input type="hidden" name="t" value="{{ $token }}">
            {{-- Honeypot: hidden from people, bots fill it --}}
            <input type="text" name="website" tabindex="-1" autocomplete="off" class="hidden" aria-hidden="true">
            <button type="submit" class="btn-primary w-full">{{ __('site.contact.send') }}</button>
            <p class="text-xs text-stone-500">{{ __('site.contact.privacy') }}</p>
        </form>
    </section>
@endsection
