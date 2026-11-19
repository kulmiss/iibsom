{{-- How many ads the signed-in person saved (switch "account.saved_count"; $savedCount comes from the layout,
     null = not shown). Kept in the page when 0 (hidden) so saved-count.js can show it after a Save. --}}
@if (($savedCount ?? null) !== null)
    <span @class(['absolute flex h-5 min-w-5 items-center justify-center rounded-full bg-accent-400 px-1 text-[11px] font-bold leading-none text-stone-900 ring-2', $ring, $position, 'hidden' => $savedCount === 0]) data-saved-count="{{ $savedCount }}">{{ $savedCount > 99 ? '99+' : $savedCount }}</span>
@endif
