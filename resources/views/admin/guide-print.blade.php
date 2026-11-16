{{-- Stand-alone page to print or save as PDF (browser: Print → Save as PDF). Used by tools/make-guide-pdf. --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <meta name="guide-version" content="{{ $version }}">
    <title>iibsom admin guide</title>
    <style>
        @page { size: A4; margin: 18mm 16mm; }
        body { font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; color: #1c1917; line-height: 1.55; max-width: 46rem; margin: 2rem auto; padding: 0 1rem; }
        .cover { text-align: center; padding: 4rem 0 2rem; }
        .brand { font-size: 2.6rem; font-weight: 800; color: #1f6fd1; margin: 0; }
        .note { color: #57534e; font-size: .9rem; }
        nav ol { padding-left: 1.4rem; }
        nav a, section a { color: #1f6fd1; }
        section { margin-top: 2.2rem; }
        section:first-of-type { break-before: page; margin-top: 0; }
        h1, h2 { break-after: avoid; }
        li, p { break-inside: avoid; }
        h1 { font-size: 1.6rem; margin: 0 0 .8rem; color: #1a5bab; }
        h2 { font-size: 1.15rem; margin: 1.4rem 0 .4rem; }
        code { background: #f5f5f4; padding: 0 .25rem; border-radius: .2rem; }
        footer { margin-top: 3rem; color: #78716c; font-size: .8rem; }
        @media screen { .print-tip { background: #eef5fd; border: 1px solid #bcd6f6; padding: .7rem 1rem; border-radius: .5rem; } }
        @media print { .print-tip { display: none; } }
    </style>
</head>
<body>
<div class="cover">
    <p class="brand">iibsom</p>
    <p style="font-size:1.4rem;font-weight:700;margin:.4rem 0">Admin guide</p>
    <p class="note">For the Super Admin, moderators and support staff.</p>
</div>
<p class="print-tip">To save this guide as a PDF: open your browser's <strong>Print</strong> and choose <strong>Save as PDF</strong>.</p>
<nav aria-label="Contents">
    <h2>Contents</h2>
    <ol>
        @foreach ($sections as $section)
            <li><a href="#{{ $section['slug'] }}">{{ $section['title'] }}</a></li>
        @endforeach
    </ol>
</nav>
@foreach ($sections as $section)
    <section id="{{ $section['slug'] }}">
        <h1>{{ $section['title'] }}</h1>
        {!! $section['html'] !!}
    </section>
@endforeach
<footer>iibsom admin guide · version {{ $version }}</footer>
</body>
</html>
