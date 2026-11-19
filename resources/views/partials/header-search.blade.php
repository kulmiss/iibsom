{{--
  Search at the top of every page except home (switch "site.header_search"); home has its own big search.
  On a category page it searches inside that category.
  $only = 'desktop': a box in the header (big screens). $only = 'phone': a magnifier that opens the box under the
  header (a plain <details>, so it also works without JavaScript).
  Suggestions while typing come from search-suggest.js (switch "search.suggestions").
--}}
@php
    $scope = isset($category) && $category instanceof \App\Models\Category ? $category : null;
    $action = $scope ? lroute('browse.category', ['category' => $scope->slug]) : lroute('search');
    $placeholder = $scope ? __('site.search.in_category', ['category' => $scope->label]) : __('site.home.search_placeholder');
    $typed = request()->routeIs('search', 'loc.search') || $scope ? (string) request()->query('q', '') : '';
    $field = 'hq-'.$only;
@endphp
@if ($only === 'phone')
<details class="lg:hidden" data-header-search>
    <summary class="icon-btn cursor-pointer list-none [&::-webkit-details-marker]:hidden" title="{{ __('site.search.open') }}">{{ icon('search') }}<span class="sr-only">{{ __('site.search.open') }}</span></summary>
    <div class="absolute inset-x-0 top-full bg-brand-700 px-4 pb-3 shadow-md">
@endif
<form role="search" method="get" action="{{ $action }}" class="relative {{ $only === 'desktop' ? 'hidden max-w-md flex-1 lg:block' : '' }}" data-search-form>
    <label for="{{ $field }}" class="sr-only">{{ $placeholder }}</label>
    <input id="{{ $field }}" name="q" type="search" maxlength="100" autocomplete="off" placeholder="{{ $placeholder }}" value="{{ $typed }}"
           class="w-full rounded-lg border-0 bg-white py-2 ps-3 pe-10 text-sm text-stone-900 placeholder:text-stone-500 focus:ring-2 focus:ring-accent-400"
           @feature('search.suggestions') data-suggest="{{ lroute('search.suggest') }}" data-scope="{{ $scope?->slug }}" @endfeature>
    <button type="submit" class="absolute inset-y-0 end-0 flex w-10 items-center justify-center text-stone-500 hover:text-brand-700">{{ icon('search', 'size-4') }}<span class="sr-only">{{ __('site.home.search_button') }}</span></button>
</form>
@if ($only === 'phone')
    </div>
</details>
@endif
