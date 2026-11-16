{{-- Phone bottom bar (like a mobile app). Items follow their feature switches. --}}
<nav class="fixed inset-x-0 bottom-0 z-30 border-t border-stone-200 bg-white pb-[env(safe-area-inset-bottom)] lg:hidden" aria-label="Bottom">
    <ul class="mx-auto flex max-w-md items-stretch justify-around text-[11px] font-medium text-stone-600">
        <li>
            <a href="{{ lroute('home') }}" class="bottom-nav-item {{ request()->routeIs('home', 'loc.home') ? 'text-brand-700' : '' }}" @if (request()->routeIs('home', 'loc.home')) aria-current="page" @endif>
                {{ icon('house', 'size-6') }}<span>{{ __('site.nav.home') }}</span>
            </a>
        </li>
        @feature('favourites')
            <li><a href="#" class="bottom-nav-item">{{ icon('bookmark', 'size-6') }}<span>{{ __('site.nav.saved') }}</span></a></li>
        @endfeature
        @if (feature('ads.posting') && (auth()->check() || feature('login.google')))
            <li><a href="{{ auth()->check() ? '#' : lroute('login') }}" class="bottom-nav-item">{{ icon('square-plus', 'size-6') }}<span>{{ __('site.nav.sell') }}</span></a></li>
        @endif
        @feature('chat')
            <li><a href="#" class="bottom-nav-item">{{ icon('message-circle', 'size-6') }}<span>{{ __('site.nav.messages') }}</span></a></li>
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
