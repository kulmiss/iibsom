@extends('layouts.app')

@section('title', __('notify.settings.title'))

@section('content')
    <section class="mx-auto max-w-md px-4 py-8">
        <a href="{{ lroute('notifications') }}" class="text-sm font-medium text-brand-700">← {{ __('notify.title') }}</a>
        <h1 class="mt-2 text-2xl font-bold">{{ __('notify.settings.title') }}</h1>
        <p class="mt-1 text-sm text-stone-600">{{ __('notify.settings.intro') }}</p>

        <form method="post" action="{{ lroute('notifications.settings.save') }}" class="mt-5 space-y-5">
            @csrf
            @foreach (['bell', 'email'] as $channel)
                <fieldset class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-stone-200">
                    <legend class="px-1 font-semibold">{{ __('notify.settings.'.$channel) }}</legend>
                    @foreach ($settings as $key => $on)
                        @continue (! str_starts_with($key, $channel.'.'))
                        @continue ($key === 'bell.price_drop' && ! (feature('favourites') && feature('favourites.price_drop')))
                        @continue (in_array($key, ['bell.call_back', 'email.call_back'], true) && ! feature('contact.call_back'))
                        @continue (in_array($key, ['bell.saved_search', 'email.saved_search'], true) && ! feature('saved_searches'))
                        @continue ($key === 'bell.followed_ad' && ! (feature('sellers.page') && feature('sellers.follow')))
                        @continue ($key === 'bell.ad_hidden' && ! (feature('reports.auto_hide') && feature('ads.reports')))
                        <input type="hidden" name="shown[]" value="{{ $key }}">
                        <label class="flex items-start gap-3 py-2">
                            <input type="checkbox" name="on[]" value="{{ $key }}" @checked($on) class="mt-0.5 size-5 rounded border-stone-300 text-brand-700">
                            <span class="text-sm">{{ __('notify.settings.'.str_replace('.', '_', $key)) }}</span>
                        </label>
                    @endforeach
                </fieldset>
            @endforeach
            <button type="submit" class="btn-primary w-full">{{ __('notify.settings.save') }}</button>
        </form>
    </section>
@endsection
