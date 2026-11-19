<header class="sticky top-0 z-20 bg-brand-700 text-white">
    @feature('search.suggestions')
        {{-- Words for the search suggestions list (search-suggest.js). --}}
        <template id="suggest-text" data-recent="{{ __('site.search.recent') }}" data-clear="{{ __('site.search.clear_recent') }}" data-category="{{ __('site.search.category') }}"
                  data-none="{{ trans_choice('site.search.count', 0) }}" data-one="{{ trans_choice('site.search.count', 1, ['count' => ':count']) }}" data-many="{{ trans_choice('site.search.count', 2, ['count' => ':count']) }}"></template>
    @endfeature
    @php($phoneBar = $__env->hasSection('phone_bar'))
    @if ($phoneBar)
        {{-- Phones: a page's own top bar (the ad page: Back, category, Share, Save). Computers keep the normal bar. --}}
        <div class="flex items-center gap-1 px-2 py-1.5 lg:hidden" data-phone-bar>
            @yield('phone_bar')
        </div>
    @endif
    <div @class(['mx-auto max-w-6xl items-center gap-2 px-4 py-2.5 sm:gap-3', 'flex' => ! $phoneBar, 'hidden lg:flex' => $phoneBar])>
        <a href="{{ lroute('home') }}" class="text-2xl font-extrabold tracking-tight" aria-label="{{ config('app.name') }} – {{ __('site.nav.home') }}">
            {{-- Lowercase so the first "I" is not read as "l". --}}
            {{ \Illuminate\Support\Str::lower(config('app.name')) }}
        </a>

        @php($headerSearch = feature('site.header_search') && ! request()->routeIs('home', 'loc.home'))
        @if ($headerSearch)
            @include('partials.header-search', ['only' => 'desktop'])
        @endif

        <nav class="ml-auto flex items-center gap-1.5 sm:gap-2" aria-label="Main">
            @if ($headerSearch)
                @include('partials.header-search', ['only' => 'phone'])
            @endif
            @include('partials.language-switcher')

            {{-- Phase 2 features: hidden until their switch is ON. --}}
            @feature('favourites')
                <a href="{{ lroute('saved') }}" class="icon-btn relative hidden lg:flex" title="{{ __('site.nav.saved') }}">{{ icon('bookmark') }}<span class="sr-only">{{ __('site.nav.saved') }}</span>@include('partials.saved-count', ['ring' => 'ring-brand-700', 'position' => 'end-0 top-0'])</a>
            @endfeature
            @feature('chat')
                <a href="{{ lroute('chat') }}" class="icon-btn relative hidden lg:flex" title="{{ __('site.nav.messages') }}">{{ icon('message-circle') }}<span class="sr-only">{{ __('site.nav.messages') }}</span>@if (($chatUnread ?? 0) > 0)@include('partials.unread-badge', ['count' => $chatUnread, 'ring' => 'ring-brand-700', 'position' => 'end-0 top-0', 'dot' => 'bg-accent-400', 'dotRing' => 'ring-2 ring-brand-700', 'dotPosition' => 'end-1 top-1'])@endif</a>
            @endfeature
            @if (auth()->check() && feature('notifications'))
                {{-- Notifications bell (PR 66); saved searches are reached from the account page and the list. --}}
                <a href="{{ lroute('notifications') }}" class="icon-btn relative" title="{{ __('notify.bell') }}">{{ icon('bell') }}<span class="sr-only">{{ __('notify.bell') }}</span>@if (($bellUnread ?? 0) > 0)@include('partials.unread-badge', ['count' => $bellUnread, 'label' => trans_choice('notify.unread', $bellUnread, ['count' => $bellUnread]), 'ring' => 'ring-brand-700', 'position' => 'end-0 top-0', 'dot' => 'bg-accent-400', 'dotRing' => 'ring-2 ring-brand-700', 'dotPosition' => 'end-1 top-1'])@endif</a>
            @elseif (feature('saved_searches'))
                <a href="{{ lroute('searches') }}" class="icon-btn" title="{{ __('site.nav.alerts') }}">{{ icon('bell') }}<span class="sr-only">{{ __('site.nav.alerts') }}</span></a>
            @endif

            @auth
                @if (feature('site.header_icons') && feature('ads.my_ads'))
                    <a href="{{ lroute('account.ads') }}" class="icon-btn hidden lg:flex" title="{{ __('account.account.my_ads') }}">{{ icon('megaphone') }}<span class="sr-only">{{ __('account.account.my_ads') }}</span></a>
                @endif
                <a href="{{ lroute('account') }}" class="hidden size-9 items-center justify-center rounded-full bg-white font-bold text-brand-700 lg:flex" title="{{ __('site.nav.my_account') }}">
                    {{ auth()->user()->initial() }}<span class="sr-only">{{ __('site.nav.my_account') }}</span>
                </a>
            @else
                @feature('login.google')
                    <a href="{{ lroute('login') }}" class="icon-btn hidden lg:flex" title="{{ __('site.nav.sign_in') }}">{{ icon('user') }}<span class="sr-only">{{ __('site.nav.sign_in') }}</span></a>
                @endfeature
            @endauth

            @if (feature('ads.posting') && (auth()->check() || feature('login.google')))
                <a href="{{ lroute('post') }}" class="btn-sell hidden lg:inline-flex">{{ __('site.nav.sell') }}</a>
            @endif
        </nav>
    </div>
</header>
