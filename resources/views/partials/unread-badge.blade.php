{{-- Unread count on an icon (messages, notifications): the number (switch "site.header_icons"), or a dot when OFF.
     $label: words for screen readers (default: unread chats). --}}
@php($label ??= trans_choice('chat.unread', $count, ['count' => $count]))
@if (feature('site.header_icons'))
    <span class="absolute {{ $position }} flex h-5 min-w-5 items-center justify-center rounded-full bg-red-600 px-1 text-[11px] font-bold leading-none text-white ring-2 {{ $ring }}">{{ $count > 99 ? '99+' : $count }}<span class="sr-only"> {{ $label }}</span></span>
@else
    <span class="absolute {{ $dotPosition }} size-2.5 rounded-full {{ $dot }} {{ $dotRing ?? '' }}" role="img" aria-label="{{ $dotLabel ?? __('chat.new') }}"></span>
@endif
