<footer class="mt-12 border-t border-stone-200 bg-white">
    <div class="mx-auto max-w-6xl px-4 py-6 text-sm text-stone-600">
        <ul class="flex flex-wrap gap-x-5 gap-y-2">
            <li><a href="{{ lroute('page', ['slug' => 'about']) }}" class="hover:text-brand-700 hover:underline">{{ __('site.footer.about') }}</a></li>
            <li><a href="{{ lroute('page', ['slug' => 'safety']) }}" class="hover:text-brand-700 hover:underline">{{ __('site.footer.safety') }}</a></li>
            <li><a href="{{ lroute('page', ['slug' => 'terms']) }}" class="hover:text-brand-700 hover:underline">{{ __('site.footer.terms') }}</a></li>
            <li><a href="{{ lroute('page', ['slug' => 'privacy']) }}" class="hover:text-brand-700 hover:underline">{{ __('site.footer.privacy') }}</a></li>
        </ul>
        <p class="mt-4">© {{ now()->year }} {{ \Illuminate\Support\Str::lower(config('app.name')) }}. {{ __('site.footer.rights') }}</p>
    </div>
</footer>
