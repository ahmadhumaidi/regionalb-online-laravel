<?php

namespace App\Http\Controllers;

use App\Models\RsmUser;
use App\Services\Content\ContentSummaryService;
use App\Services\Dashboard\DashboardFilters;
use App\Services\Dashboard\ReferenceOptionsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Read-only pass of "Monitoring Konten Kampus" (dashboard.php:1107-1138).
 * Account registration, daily post logging, and the Instagram/Meta OAuth
 * connect flow are out of scope — see ContentSummaryService's docblock.
 */
class ContentController extends Controller
{
    public function index(Request $request): View
    {
        /** @var RsmUser $user */
        $user = Auth::user();
        $area = $user->area ?: 'Regional';

        $filters = DashboardFilters::fromRequest($request, 'konten');
        $summary = ContentSummaryService::build($area, $filters, $user);
        $referenceOptions = ReferenceOptionsService::build($area, $user);

        $summaryCards = [
            ['label' => 'Akun Kampus', 'value' => number_format($summary['totals']['accounts'], 0, ',', '.'), 'tone' => 'blue', 'note' => 'Akun Instagram terdaftar & aktif'],
            ['label' => 'Feed', 'value' => number_format($summary['totals']['feed'], 0, ',', '.'), 'tone' => 'cyan', 'note' => 'Post feed pada periode/filter ini'],
            ['label' => 'Reels', 'value' => number_format($summary['totals']['reels'], 0, ',', '.'), 'tone' => 'purple', 'note' => 'Post reels pada periode/filter ini'],
            ['label' => 'Story', 'value' => number_format($summary['totals']['story'], 0, ',', '.'), 'tone' => 'amber', 'note' => 'Post story pada periode/filter ini'],
            ['label' => 'Poin Konten', 'value' => number_format($summary['totals']['score'], 0, ',', '.'), 'tone' => 'green', 'note' => 'Feed +10, reels +15, story +5, keyword PMB +5'],
        ];

        return view('konten.index', [
            'active' => 'konten',
            'filters' => $filters,
            'referenceOptions' => $referenceOptions,
            'summaryCards' => $summaryCards,
            'regionals' => $summary['regionals'],
            'posts' => $summary['posts'],
        ]);
    }
}
