{{-- Place picker for the home search (PR 87, switch "search.location_picker"; resources/js/place-picker.js).
     Cities A–Z → their districts A–Z, each with the number of live ads, and a search box over both.
     $locations comes from HomeSections::locations() (only when there is a choice). --}}
@php($total = array_sum(array_column($locations, 'count')))
@php($single = count($locations) === 1 ? $locations[0]['slug'] : null)
<dialog id="place-picker" aria-labelledby="place-picker-title" data-loc-dialog @if ($single) data-loc-start="{{ $single }}" @endif
        class="m-auto w-[min(40rem,calc(100vw-1.5rem))] max-h-[min(40rem,calc(100dvh-2rem))] overflow-hidden rounded-2xl bg-white p-0 text-stone-900 shadow-xl backdrop:bg-stone-950/50">
    <div class="flex max-h-[inherit] flex-col">
        <div class="flex items-center gap-2 border-b border-stone-200 p-3">
            <button type="button" class="icon-btn hidden size-9 text-stone-700 hover:bg-stone-100" data-loc-back aria-label="{{ __('site.home.place_back') }}">{{ icon('chevron-left', 'size-5 rtl:rotate-180') }}</button>
            <h2 id="place-picker-title" class="flex-1 text-base font-semibold">{{ __('site.home.place_title') }}</h2>
            <button type="button" class="icon-btn size-9 text-stone-700 hover:bg-stone-100" data-loc-close aria-label="{{ __('site.home.place_close') }}">{{ icon('x', 'size-5') }}</button>
        </div>
        <div class="border-b border-stone-200 p-3">
            <label for="place-search" class="sr-only">{{ __('site.home.place_search') }}</label>
            <div class="relative">
                <span class="pointer-events-none absolute inset-y-0 start-3 flex items-center text-stone-500">{{ icon('search', 'size-4') }}</span>
                <input id="place-search" type="search" autocomplete="off" class="form-input ps-9" placeholder="{{ __('site.home.place_search') }}" data-loc-search>
            </div>
        </div>

        <div class="min-h-0 flex-1 overflow-y-auto overscroll-contain p-2">
            {{-- Level 1: all cities --}}
            <ul data-loc-level="cities">
                <li><button type="button" class="loc-item font-semibold text-brand-700" data-loc-pick data-city="" data-area="" data-label="{{ __('site.home.all_somalia') }}">
                    <span class="w-5 shrink-0"></span><span class="flex-1">{{ __('site.home.all_somalia') }}</span><span class="text-sm font-normal text-stone-500">{{ trans_choice('site.ads_count', $total, ['count' => number_format($total)]) }}</span>
                </button></li>
                @php($letter = null)
                @foreach ($locations as $city)
                    @php($first = mb_strtoupper(mb_substr($city['label'], 0, 1)))
                    <li><button type="button" class="loc-item"
                        @if ($city['areas']) data-loc-goto="{{ $city['slug'] }}" aria-label="{{ __('site.home.place_open', ['city' => $city['label']]) }}"
                        @else data-loc-pick data-city="{{ $city['slug'] }}" data-area="" data-label="{{ $city['label'] }}" @endif>
                        <span class="w-5 shrink-0 text-sm font-bold text-stone-500" aria-hidden="true">{{ $first !== $letter ? $first : '' }}</span>
                        <span class="min-w-0 flex-1"><span class="block">{{ $city['label'] }}</span><span class="block text-xs text-stone-500">{{ $city['region'] }}</span></span>
                        <span class="text-sm text-stone-500">{{ trans_choice('site.ads_count', $city['count'], ['count' => number_format($city['count'])]) }}</span>
                        @if ($city['areas']){{ icon('chevron-right', 'size-4 shrink-0 text-stone-400 rtl:rotate-180') }}@endif
                    </button></li>
                    @php($letter = $first)
                @endforeach
            </ul>

            {{-- Level 2: one city's districts --}}
            @foreach ($locations as $city)
                @if ($city['areas'])
                    <ul data-loc-level="{{ $city['slug'] }}" hidden>
                        <li><button type="button" class="loc-item font-semibold text-brand-700" data-loc-pick data-city="{{ $city['slug'] }}" data-area="" data-label="{{ $city['label'] }}">
                            <span class="w-5 shrink-0"></span><span class="flex-1">{{ __('site.home.place_all_in', ['city' => $city['label']]) }}</span><span class="text-sm font-normal text-stone-500">{{ trans_choice('site.ads_count', $city['count'], ['count' => number_format($city['count'])]) }}</span>
                        </button></li>
                        @php($letter = null)
                        @foreach ($city['areas'] as $area)
                            @php($first = mb_strtoupper(mb_substr($area['label'], 0, 1)))
                            <li><button type="button" class="loc-item" data-loc-pick data-city="{{ $city['slug'] }}" data-area="{{ $area['id'] }}" data-label="{{ $area['label'] }}, {{ $city['label'] }}">
                                <span class="w-5 shrink-0 text-sm font-bold text-stone-500" aria-hidden="true">{{ $first !== $letter ? $first : '' }}</span>
                                <span class="min-w-0 flex-1"><span class="block">{{ $area['label'] }}</span><span class="block text-xs text-stone-500">{{ $city['label'] }}</span></span>
                                <span class="text-sm text-stone-500">{{ trans_choice('site.ads_count', $area['count'], ['count' => number_format($area['count'])]) }}</span>
                            </button></li>
                            @php($letter = $first)
                        @endforeach
                    </ul>
                @endif
            @endforeach

            {{-- Search: every city and district in one list --}}
            <ul data-loc-level="search" hidden>
                @foreach ($locations as $city)
                    <li data-loc-names="{{ mb_strtolower($city['label'].' '.$city['region']) }}"><button type="button" class="loc-item" data-loc-pick data-city="{{ $city['slug'] }}" data-area="" data-label="{{ $city['label'] }}">
                        <span class="min-w-0 flex-1"><span class="block">{{ $city['label'] }}</span><span class="block text-xs text-stone-500">{{ $city['region'] }}</span></span>
                        <span class="text-sm text-stone-500">{{ trans_choice('site.ads_count', $city['count'], ['count' => number_format($city['count'])]) }}</span>
                    </button></li>
                    @foreach ($city['areas'] as $area)
                        <li data-loc-names="{{ mb_strtolower($area['label'].' '.$city['label']) }}"><button type="button" class="loc-item" data-loc-pick data-city="{{ $city['slug'] }}" data-area="{{ $area['id'] }}" data-label="{{ $area['label'] }}, {{ $city['label'] }}">
                            <span class="min-w-0 flex-1"><span class="block">{{ $area['label'] }}</span><span class="block text-xs text-stone-500">{{ $city['label'] }}</span></span>
                            <span class="text-sm text-stone-500">{{ trans_choice('site.ads_count', $area['count'], ['count' => number_format($area['count'])]) }}</span>
                        </button></li>
                    @endforeach
                @endforeach
                <li hidden class="p-4 text-center text-sm text-stone-600" data-loc-none>{{ __('site.home.place_none') }}</li>
            </ul>
        </div>
    </div>
</dialog>
