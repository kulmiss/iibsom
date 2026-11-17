{{-- Phone bottom bar (like a mobile app). Items follow their feature switches. --}}
<nav data-bottom-nav class="fixed inset-x-0 bottom-0 z-30 border-t border-stone-200 bg-white pb-[env(safe-area-inset-bottom)] lg:hidden" aria-label="Bottom">
    <ul class="mx-auto flex max-w-md items-stretch justify-around text-[11px] font-medium text-stone-600">
        <li>
            <a href="{{ lroute('home') }}" class="bottom-nav-item {{ request()->routeIs('home', 'loc.home') ? 'text-brand-700' : '' }}" @if (request()->routeIs('home', 'loc.home')) aria-current="page" @endif>
                {{ icon('house', 'size-6') }}<span>{{ __('site.nav.home') }}</span>
            </a>
        </li>
        @feature('favourites')
            <li><a href="{{ lroute('saved') }}" class="bottom-nav-item {{ request()->routeIs('saved', 'loc.saved') ? 'text-brand-700' : '' }}">{{ icon('bookmark', 'size-6') }}<span>{{ __('site.nav.saved') }}</span></a></li>
        @endfeature
        @if (feature('ads.posting') && (auth()->check() || feature('login.google')))
            <li><a href="{{ lroute('post') }}" class="bottom-nav-item">{{ icon('square-plus', 'size-6') }}<span>{{ __('site.nav.sell') }}</span></a></li>
        @endif
        @feature('chat')
            <li><a href="{{ lroute('chat') }}" class="bottom-nav-item relative {{ request()->routeIs('chat', 'loc.chat', 'chat.show', 'loc.chat.show') ? 'text-brand-700' : '' }}">{{ icon('message-circle', 'size-6') }}<span>{{ __('site.nav.messages') }}</span>@if (($chatUnread ?? 0) > 0)<span class="absolute end-1/4 top-1 size-2.5 rounded-full bg-red-600" aria-label="{{ __('chat.new') }}"></span>@endif</a></li>
        @endfeature
        @auth
            <li><a href="{{ lroute('account') }}" class="bottom-nav-item {{ request()->routeIs('account*', 'loc.account*') ? 'text-brand-700' : '' }}">{{ icon('user', 'size-6') }}<span>{{ __('site.nav.profile') }}</span></a></li>
        @else
            @feature('login.google')
                <li><a href="{{ lroute('login') }}" class="bottom-nav-item {{ request()->routeIs('login', 'loc.login') ? 'text-brand-700' : '' }}">{{ icon('user', 'size-6') }}<span>{{ __('site.nav.profile') }}</span></a></li>
            @endfeature
        @endauth
    </ul>
</nav>
