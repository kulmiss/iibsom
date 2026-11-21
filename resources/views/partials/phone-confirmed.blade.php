{{-- "Phone confirmed" tick (PR 108) with an explanation that works without JavaScript. --}}
<details class="group/phone relative inline-block" data-phone-tick>
    <summary class="inline-flex cursor-pointer list-none items-center gap-1 rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-800 ring-1 ring-emerald-200 [&::-webkit-details-marker]:hidden">
        {{ icon('phone', 'size-3.5') }} {{ __('account.phone.tick') }}
        <span class="sr-only">· {{ __('account.phone.what_title') }}</span>{{ icon('circle-help', 'size-3.5 opacity-70') }}
    </summary>
    <p class="mt-1.5 max-w-xs rounded-lg bg-white p-2.5 text-xs font-normal text-stone-700 shadow-sm ring-1 ring-stone-200">{{ __('account.phone.what_body') }}</p>
</details>
