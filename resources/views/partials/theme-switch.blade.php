{{-- Light / dark (switch "site.dark_mode"): Auto follows the phone or computer; the choice is kept on this device only. --}}
@feature('site.dark_mode')
    <div role="group" aria-label="{{ __('site.theme.label') }}" class="inline-flex items-center gap-0.5 rounded-lg bg-white/10 p-0.5" data-theme-switch hidden>
        @foreach (['auto' => 'sun-moon', 'light' => 'sun', 'dark' => 'moon'] as $mode => $iconName)
            <button type="button" data-theme-choice="{{ $mode }}" aria-pressed="false"
                    class="flex items-center gap-1 rounded-md px-2 py-1 text-xs font-medium text-white/80 hover:bg-white/15 aria-pressed:bg-white aria-pressed:text-[#14467f]">
                {{ icon($iconName, 'size-3.5') }}<span>{{ __('site.theme.'.$mode) }}</span>
            </button>
        @endforeach
    </div>
@endfeature
