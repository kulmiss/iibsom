@php
    $change = function (int $now, int $before): string {
        if ($before === 0) {
            return $now > 0 ? 'new' : '–';
        }
        $pct = (int) round(($now - $before) / $before * 100);

        return ($pct > 0 ? '+' : '').$pct.'%';
    };
    $rows = ['new_ads' => 'New ads', 'approved' => 'Ads approved', 'rejected' => 'Ads rejected', 'new_users' => 'New users', 'reports' => 'Reports by users'];
@endphp
<x-mail::message>
# Your week on iibsom

{{ $s['from']->format('l j M') }} – {{ $s['to']->subDay()->format('l j M Y') }} (Mogadishu time)

<x-mail::table>
| | This week | Week before | Change |
|:--|--:|--:|--:|
@foreach ($rows as $key => $label)
| {{ $label }} | {{ number_format($s['week'][$key]) }} | {{ number_format($s['previous'][$key]) }} | {{ $change($s['week'][$key], $s['previous'][$key]) }} |
@endforeach
</x-mail::table>

**Right now:** {{ number_format($s['live_now']) }} ads live · {{ number_format($s['waiting_now']) }} waiting for approval · {{ number_format($s['reports_waiting']) }} reports to check

@if ($s['top_categories'] !== [])
**Most new ads this week**

@foreach ($s['top_categories'] as $i => $c)
{{ $i + 1 }}. {{ $c['name'] }} ({{ number_format($c['total']) }})
@endforeach
@endif

<x-mail::button :url="url('/admin')">
Open the admin
</x-mail::button>

@if ($s['includes_demo'])
*These numbers include demo data ("Show demo data" is ON).*
@endif

You get this email because your address is in Admin → Settings → General → Weekly summary.
</x-mail::message>
