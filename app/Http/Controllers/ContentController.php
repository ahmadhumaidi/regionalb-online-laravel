<?php

namespace App\Http\Controllers;

use App\Models\RsmUser;
use App\Models\RsmSocialAccount;
use App\Models\RsmSocialPost;
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

    public function storeAccount(Request $request)
    {
        $user = $request->user();
        $data = $request->validate(['wilayah'=>'required|string|max:120','unit_name'=>'required|string|max:180','instagram_username'=>'required|string|max:180','pic_name'=>'nullable|string|max:180','pic_phone'=>'nullable|string|max:80']);
        RsmSocialAccount::updateOrCreate(['area'=>$user->area ?: 'Regional','unit_name'=>$data['unit_name'],'instagram_username'=>ltrim($data['instagram_username'],'@')], $data + ['created_by_user_id'=>$user->id,'created_by_name'=>$user->name,'is_active'=>true,'connection_status'=>'Manual']);
        return back()->with('status','Akun Instagram berhasil disimpan.');
    }

    public function storePost(Request $request)
    {
        $user = $request->user();
        $data = $request->validate(['account_id'=>'required|integer|exists:rsm_social_accounts,id','post_date'=>'required|date','media_type'=>['required',\Illuminate\Validation\Rule::in(['no_post','feed','reels','story'])],'caption'=>'nullable|string|max:5000','post_url'=>'nullable|url|max:500','keyword_match'=>'nullable|boolean','reach_count'=>'nullable|integer|min:0','like_count'=>'nullable|integer|min:0','comment_count'=>'nullable|integer|min:0']);
        $account = RsmSocialAccount::where('id',$data['account_id'])->where('area',$user->area ?: 'Regional')->firstOrFail();
        $score = ['feed'=>10,'reels'=>15,'story'=>5,'no_post'=>0][$data['media_type']] + (!empty($data['keyword_match']) ? 5 : 0);
        RsmSocialPost::create($data + ['area'=>$user->area ?: 'Regional','score'=>$score,'source_name'=>'Manual','created_by_user_id'=>$user->id,'created_by_name'=>$user->name]);
        return back()->with('status','Post konten berhasil dicatat.');
    }
}
