{{-- Buyers abroad (switch "diaspora"): shown only by diaspora.js when the phone's time zone is outside Somalia. Nothing is stored. --}}
@php($rates = array_filter((array) setting('diaspora.rates'), fn ($r) => (float) $r > 0))
<div data-diaspora hidden
     data-usd="{{ $ad->price ?? '' }}"
     data-rates='@json((object) $rates)'
     data-locale="{{ app()->getLocale() === 'so' ? 'so-SO' : 'en-GB' }}">
    @if ($ad->price !== null && $rates !== [])
        <p class="mt-1 flex flex-wrap items-center gap-x-2 text-sm text-stone-700" data-diaspora-price hidden>
            <span class="font-semibold" data-diaspora-amount></span>
            <label class="sr-only" for="diaspora-currency">{{ __('ads.diaspora.money') }}</label>
            <select id="diaspora-currency" class="rounded-md border-stone-300 py-0.5 ps-2 pe-7 text-xs" data-diaspora-currency>
                @foreach (config('iibsom.diaspora_currencies') as $code => $name)
                    @if (isset($rates[$code]))
                        <option value="{{ $code }}">{{ $code }}</option>
                    @endif
                @endforeach
            </select>
            <span class="text-xs text-stone-500">{{ __('ads.diaspora.approx', ['date' => setting('diaspora.rates_date') ? mdate(\Illuminate\Support\Carbon::parse(setting('diaspora.rates_date'))) : '']) }}</span>
        </p>
    @endif
    <div class="mt-3 rounded-xl bg-sky-50 p-3 text-sm text-sky-950 ring-1 ring-sky-200">
        <p class="flex items-center gap-2 font-semibold">{{ icon('globe', 'size-4 shrink-0') }} {{ __('ads.diaspora.title') }}</p>
        <ul class="mt-1 list-disc space-y-0.5 ps-5">
            <li>{{ __('ads.diaspora.tip_see', ['city' => $ad->city->label]) }}</li>
            <li>{{ __('ads.diaspora.tip_money') }}</li>
            <li>{{ __('ads.diaspora.tip_chat') }}</li>
        </ul>
    </div>
</div>
