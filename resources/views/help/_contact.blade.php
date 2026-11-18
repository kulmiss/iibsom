@php($supportEmail = setting('site.support_email'))
@if ($supportEmail)
    <div class="mt-8 rounded-2xl bg-brand-50 p-5 ring-1 ring-brand-200">
        <h2 class="font-bold">{{ __('site.help.still_need') }}</h2>
        <p class="mt-1 text-stone-700">{{ __('site.help.write_to') }} <a href="mailto:{{ $supportEmail }}" class="font-semibold text-brand-800 underline">{{ $supportEmail }}</a></p>
    </div>
@endif
