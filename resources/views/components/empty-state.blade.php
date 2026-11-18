{{-- One friendly "nothing here yet" box for the whole site (PR 74): a small drawing, a title, one line of help
     and (in the slot) one or two actions. Our own drawing: soft circles in the brand tints with the page's icon. --}}
@props(['icon' => 'search', 'title', 'text' => null, 'tag' => 'div', 'plain' => false])
<{{ $tag }} {{ $attributes->class(['flex flex-col items-center px-6 py-10 text-center', 'rounded-2xl border border-dashed border-stone-300 bg-white' => ! $plain]) }} data-empty-state>
    <span class="relative flex size-28 items-center justify-center" aria-hidden="true">
        <svg viewBox="0 0 112 112" class="absolute inset-0 size-full" fill="none">
            <circle cx="56" cy="56" r="52" class="fill-brand-50"/>
            <circle cx="56" cy="56" r="38" class="stroke-brand-200" stroke-width="2" stroke-dasharray="4 6"/>
            <circle cx="92" cy="22" r="5" class="fill-accent-400"/>
            <circle cx="18" cy="84" r="3.5" class="fill-brand-300"/>
            <circle cx="98" cy="80" r="2.5" class="fill-brand-200"/>
        </svg>
        <span class="relative flex size-14 items-center justify-center rounded-2xl bg-white text-brand-700 shadow-sm ring-1 ring-brand-100">{{ icon($icon, 'size-7') }}</span>
    </span>
    <p class="mt-4 text-lg font-semibold text-stone-900">{{ $title }}</p>
    @if ($text)
        <p class="mt-1 max-w-sm text-sm text-stone-600">{{ $text }}</p>
    @endif
    @if (trim((string) $slot) !== '')
        <div class="mt-5 flex flex-wrap items-center justify-center gap-2">{{ $slot }}</div>
    @endif
</{{ $tag }}>
