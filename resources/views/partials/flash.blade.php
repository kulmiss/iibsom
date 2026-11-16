@foreach (['status' => 'bg-emerald-50 text-emerald-800 ring-emerald-200', 'error' => 'bg-red-50 text-red-800 ring-red-200'] as $key => $style)
    @if (session($key))
        <div class="mx-auto mt-4 max-w-6xl px-4">
            <p role="{{ $key === 'error' ? 'alert' : 'status' }}" class="rounded-lg px-4 py-3 text-sm ring-1 {{ $style }}">{{ session($key) }}</p>
        </div>
    @endif
@endforeach
