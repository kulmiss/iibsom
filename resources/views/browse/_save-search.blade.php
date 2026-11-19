{{-- "Save this search" on a list of ads (switch "saved_searches"). Sends this page's own filters. --}}
@php($more = feature('search.more_filters'))
@if ($category || filled($filters['q'] ?? null))
    @auth
        <form method="post" action="{{ lroute('searches.store') }}" class="shrink-0">
            @csrf
            @foreach (array_filter(['q' => $filters['q'] ?? null, 'min' => $filters['min'] ?? null, 'max' => $filters['max'] ?? null, 'condition' => $filters['condition'] ?? null, 'category' => $category?->slug, 'city' => $city?->slug, 'area' => $more ? ($filters['area'] ?? null) : null, 'photos' => $more && ! empty($filters['photos']) ? 1 : null, 'verified' => $more && ! empty($filters['verified']) && feature('verified_badge') ? 1 : null], fn ($v) => $v !== null && $v !== '') as $name => $value)
                <input type="hidden" name="{{ $name }}" value="{{ $value }}">
            @endforeach
            @foreach (($filters['f'] ?? []) as $key => $value)
                @if (is_string($value) && $value !== '')
                    <input type="hidden" name="f[{{ $key }}]" value="{{ $value }}">
                @endif
            @endforeach
            <button type="submit" class="chip gap-1.5 ring-stone-200">{{ icon('bell', 'size-4') }}{{ __('ads.searches.save') }}</button>
        </form>
    @else
        @feature('login.google')
            <a href="{{ lroute('login') }}" class="chip shrink-0 gap-1.5 ring-stone-200" title="{{ __('ads.searches.sign_in') }}">{{ icon('bell', 'size-4') }}{{ __('ads.searches.save') }}</a>
        @endfeature
    @endauth
@endif
