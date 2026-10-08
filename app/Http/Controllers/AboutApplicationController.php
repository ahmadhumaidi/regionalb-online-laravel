<?php

namespace App\Http\Controllers;

use App\Models\RsmUser;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AboutApplicationController extends Controller
{
    private const REPORT_FILENAME = 'Laporan_Kontribusi_Dashboard_Regional_Ahmad_Humaidi.pdf';

    public function index(Request $request): View
    {
        $this->authorizeSuperUser($request);

        return view('about-application.index');
    }

    public function download(Request $request): BinaryFileResponse
    {
        $this->authorizeSuperUser($request);

        $path = base_path('docs/'.self::REPORT_FILENAME);
        abort_unless(is_file($path), 404, 'Dokumen laporan belum tersedia.');

        return response()->download($path, self::REPORT_FILENAME, [
            'Content-Type' => 'application/pdf',
        ]);
    }

    private function authorizeSuperUser(Request $request): void
    {
        abort_unless($request->user()?->role === RsmUser::ROLE_SUPER_USER, 403);
    }
}
