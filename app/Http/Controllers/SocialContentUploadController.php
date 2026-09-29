<?php

namespace App\Http\Controllers;

use App\Models\RsmSocialAccount;
use App\Models\RsmSocialPost;
use App\Models\RsmUser;
use App\Models\PartnerCampus;
use App\Services\Content\SocialScope;
use App\Services\Content\SocialContentSpreadsheetService;
use App\Services\Dashboard\ReferenceOptionsService;
use App\Support\CampusMatcher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SocialContentUploadController extends Controller
{
    private const MEDIA_LABELS = [
        'feed' => 'Feed',
        'reels' => 'Reels',
        'story' => 'Story',
    ];

    public function index(Request $request): View
    {
        /** @var RsmUser $user */
        $user = $request->user();
        $area = $user->area ?: 'Regional B';
        $dateFrom = (string) $request->query('date_from', now('Asia/Jakarta')->startOfMonth()->toDateString());
        $dateTo = (string) $request->query('date_to', now('Asia/Jakarta')->toDateString());
        $wilayah = trim((string) $request->query('wilayah'));
        $unitName = trim((string) $request->query('unit_name'));

        $query = RsmSocialPost::query()
            ->with('account')
            ->where('rsm_social_posts.area', $area)
            ->whereBetween('post_date', [$dateFrom, $dateTo])
            ->join('rsm_social_accounts', 'rsm_social_accounts.id', '=', 'rsm_social_posts.account_id')
            ->select('rsm_social_posts.*');

        SocialScope::apply($query, $user, 'rsm_social_accounts');
        if ($wilayah !== '') {
            $query->where('rsm_social_accounts.wilayah', $wilayah);
        }
        if ($unitName !== '') {
            $query->where('rsm_social_accounts.unit_name', $unitName);
        }
        if ($user->role === RsmUser::ROLE_STAFF) {
            $query->where('rsm_social_posts.created_by_user_id', $user->id);
        }

        $counts = (clone $query)
            ->select('rsm_social_posts.media_type')
            ->selectRaw('COUNT(*) as aggregate')
            ->groupBy('rsm_social_posts.media_type')
            ->pluck('aggregate', 'media_type');
        $posts = $query
            ->orderByDesc('rsm_social_posts.post_date')
            ->orderByDesc('rsm_social_posts.id')
            ->paginate(20)
            ->withQueryString();

        $referenceOptions = ReferenceOptionsService::build($area, $user);
        $campusProfiles = PartnerCampus::query()
            ->get(['id', 'name', 'display_name', 'wilayah', 'instagram_username', 'instagram_url'])
            ->map(fn (PartnerCampus $campus): array => [
                'id' => $campus->id,
                'label' => $campus->display_name ?: $campus->name,
                'wilayah' => $campus->wilayah ?: '',
                'username' => $campus->instagram_username ?: '',
                'url' => $campus->instagram_url ?: '',
            ]);
        $referenceOptions['campuses'] = collect($referenceOptions['campuses'])->map(function (array $option) use ($campusProfiles): array {
            $profile = $campusProfiles->first(fn (array $campus): bool =>
                ($option['id'] && (int) $option['id'] === (int) $campus['id'])
                || CampusMatcher::matches($option['label'], $campus['label'])
            );

            return $option + [
                'wilayah' => $profile['wilayah'] ?? '',
                'instagram_username' => $profile['username'] ?? '',
                'instagram_url' => $profile['url'] ?? '',
            ];
        })->all();

        return view('social-content-upload.index', [
            'active' => 'upload-konten-sosmed',
            'sheetUrls' => [
                'feed' => trim((string) config('services.social_content_sheets.feed_url')),
                'story' => trim((string) config('services.social_content_sheets.story_url')),
            ],
            'referenceOptions' => $referenceOptions,
            'posts' => $posts,
            'mediaLabels' => self::MEDIA_LABELS,
            'summaryCards' => [
                ['label' => 'Total Konten', 'value' => $counts->sum(), 'tone' => 'blue', 'note' => 'Pada periode terpilih'],
                ['label' => 'Feed', 'value' => (int) $counts->get('feed', 0), 'tone' => 'cyan', 'note' => 'Posting feed tercatat'],
                ['label' => 'Reels', 'value' => (int) $counts->get('reels', 0), 'tone' => 'purple', 'note' => 'Reels tercatat'],
                ['label' => 'Story', 'value' => (int) $counts->get('story', 0), 'tone' => 'amber', 'note' => 'Story tercatat'],
            ],
            'filters' => compact('dateFrom', 'dateTo', 'wilayah', 'unitName'),
            'spreadsheetRecap' => SocialContentSpreadsheetService::septemberRecap($user),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        /** @var RsmUser $user */
        $user = $request->user();
        $data = $request->validate($this->storeRules($user), [
            'media_types.required' => 'Pilih minimal satu jenis konten.',
        ]);
        $this->assertIdentityAllowed($user, $data['wilayah'], $data['unit_name']);
        $username = $this->instagramUsername($data['instagram_username'] ?? null, $data['instagram_url'] ?? null);

        DB::transaction(function () use ($data, $user, $username): void {
            $campus = PartnerCampus::query()->get()->first(fn (PartnerCampus $campus): bool =>
                CampusMatcher::matches($data['unit_name'], $campus->display_name ?: $campus->name)
            );
            $campus?->update([
                'instagram_username' => $username,
                'instagram_url' => $data['instagram_url'] ?? 'https://www.instagram.com/'.$username.'/',
            ]);

            $account = RsmSocialAccount::firstOrCreate(
                [
                    'area' => $user->area ?: 'Regional B',
                    'unit_name' => $data['unit_name'],
                    'instagram_username' => $username,
                ],
                [
                    'wilayah' => $data['wilayah'],
                    'connection_status' => 'Manual',
                    'is_active' => true,
                    'created_by_user_id' => $user->id,
                    'created_by_name' => $user->name,
                ]
            );

            foreach ($data['media_types'] as $mediaType) {
                RsmSocialPost::updateOrCreate(
                    [
                        'account_id' => $account->id,
                        'post_date' => $data['post_date'],
                        'media_type' => $mediaType,
                        'created_by_user_id' => $user->id,
                    ],
                    [
                        'area' => $user->area ?: 'Regional B',
                        'caption' => $data['caption'] ?? null,
                        'post_url' => $data['post_urls'][$mediaType] ?? null,
                        'keyword_match' => (bool) ($data['keyword_match'] ?? false),
                        'score' => $this->score($mediaType, (bool) ($data['keyword_match'] ?? false)),
                        'source_name' => 'Upload Konten Sosmed',
                        'created_by_name' => $user->name,
                    ]
                );
            }
        });

        return back()->with('status', 'Konten berhasil disimpan dan otomatis masuk ke Monitoring Konten Kampus.');
    }

    public function update(Request $request, RsmSocialPost $post): RedirectResponse
    {
        $post->load('account');
        $this->authorizePost($request->user(), $post);
        $data = $request->validate([
            'post_date' => ['required', 'date'],
            'media_type' => ['required', Rule::in(array_keys(self::MEDIA_LABELS))],
            'post_url' => ['nullable', 'url', 'max:500'],
            'caption' => ['nullable', 'string', 'max:5000'],
            'keyword_match' => ['nullable', 'boolean'],
        ]);
        $keywordMatch = (bool) ($data['keyword_match'] ?? false);
        $post->update($data + ['keyword_match' => $keywordMatch, 'score' => $this->score($data['media_type'], $keywordMatch)]);

        return back()->with('status', 'Data konten berhasil diperbarui.');
    }

    public function destroy(Request $request, RsmSocialPost $post): RedirectResponse
    {
        $post->load('account');
        $this->authorizePost($request->user(), $post);
        $post->delete();

        return back()->with('status', 'Data konten berhasil dihapus.');
    }

    private function storeRules(RsmUser $user): array
    {
        return [
            'post_date' => ['required', 'date'],
            'wilayah' => ['required', 'string', 'max:120'],
            'unit_name' => ['required', 'string', 'max:180'],
            'instagram_username' => ['nullable', 'string', 'max:180', 'required_without:instagram_url'],
            'instagram_url' => ['nullable', 'url', 'max:500', 'required_without:instagram_username'],
            'media_types' => ['required', 'array', 'min:1'],
            'media_types.*' => ['required', 'distinct', Rule::in(array_keys(self::MEDIA_LABELS))],
            'post_urls' => ['nullable', 'array'],
            'post_urls.*' => ['nullable', 'url', 'max:500'],
            'caption' => ['nullable', 'string', 'max:5000'],
            'keyword_match' => ['nullable', 'boolean'],
        ];
    }

    private function assertIdentityAllowed(RsmUser $user, string $wilayah, string $unitName): void
    {
        if ($user->role === RsmUser::ROLE_KOORDINATOR) {
            abort_unless(trim((string) $user->regional) !== '' && $wilayah === $user->regional, 403);
        }
        if ($user->role === RsmUser::ROLE_STAFF) {
            abort_unless($wilayah === $user->regional && CampusMatcher::matches($unitName, (string) $user->campus_name), 403);
        }
    }

    private function authorizePost(RsmUser $user, RsmSocialPost $post): void
    {
        abort_unless($post->area === ($user->area ?: 'Regional B'), 404);
        if ($user->role === RsmUser::ROLE_STAFF) {
            abort_unless((int) $post->created_by_user_id === (int) $user->id, 403);
        }
        if ($user->role === RsmUser::ROLE_KOORDINATOR) {
            abort_unless($post->account && $post->account->wilayah === $user->regional, 403);
        }
    }

    private function score(string $mediaType, bool $keywordMatch): int
    {
        return ['feed' => 10, 'reels' => 15, 'story' => 5][$mediaType] + ($keywordMatch ? 5 : 0);
    }

    private function instagramUsername(?string $username, ?string $url): string
    {
        $username = ltrim(trim((string) $username), '@');
        if ($username !== '') {
            return $username;
        }

        $path = trim((string) parse_url((string) $url, PHP_URL_PATH), '/');
        abort_if($path === '', 422, 'Link Instagram tidak memuat username yang valid.');

        return explode('/', $path)[0];
    }
}
