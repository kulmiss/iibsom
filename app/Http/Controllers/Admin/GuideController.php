<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\Help\AdminGuide;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** Super Admin guide: for every staff member (any role). */
class GuideController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'sections' => AdminGuide::sections(),
            'pdf' => is_file(resource_path(AdminGuide::PDF)),
        ]);
    }

    /** A plain page to print or "Save as PDF" from the browser. */
    public function print(): View
    {
        return view('admin.guide-print', ['sections' => AdminGuide::sections(), 'version' => AdminGuide::version()]);
    }

    public function pdf(): BinaryFileResponse
    {
        $path = resource_path(AdminGuide::PDF);
        abort_unless(is_file($path), 404);

        return response()->download($path, 'iibsom-admin-guide.pdf', ['Content-Type' => 'application/pdf']);
    }
}
