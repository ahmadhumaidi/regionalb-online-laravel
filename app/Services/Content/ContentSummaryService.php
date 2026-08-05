<?php

namespace App\Services\Content;

use App\Models\RsmSocialAccount;
use App\Models\RsmSocialPost;
use App\Models\RsmUser;
use Illuminate\Support\Collection;

/**
 * Ports rsm_social_summary() (rsm_db.php:2430) for "Monitoring Konten
 * Kampus". Read-only pass: the two legacy POST forms ("Akun Instagram
 * Kampus" account registration, "Catat Posting Hari Ini" daily post log)
 * and the Meta/Instagram OAuth connect flow are not ported — the OAuth
 * piece needs real Meta app credentials that aren't configured in this
 * Laravel app yet, and the create forms are a separate mutation pass like
 * Anggaran's approve/reject/revise was.
 */
class ContentSummaryService
{
    /** @return array{totals: array, regionals: list<array>, posts: list<array>} */
    public static function build(string $area, array $filters, RsmUser $user): array
    {
        $accounts = SocialScope::apply(
            RsmSocialAccount::query()->where('area', $area)->where('is_active', true),
            $user
        )->get();

        $postsQuery = RsmSocialPost::query()
            ->join('rsm_social_accounts', 'rsm_social_accounts.id', '=', 'rsm_social_posts.account_id')
            ->where('rsm_social_posts.area', $area)
            ->whereBetween('rsm_social_posts.post_date', [$filters['date_from'], $filters['date_to']]);

        if ($filters['wilayah'] !== '') {
            $postsQuery->where('rsm_social_accounts.wilayah', $filters['wilayah']);
        }
        if ($filters['unit_name'] !== '') {
            $postsQuery->where('rsm_social_accounts.unit_name', $filters['unit_name']);
        }

        $posts = SocialScope::apply($postsQuery, $user, 'rsm_social_accounts')
            ->orderByDesc('rsm_social_posts.post_date')
            ->orderByDesc('rsm_social_posts.post_time')
            ->orderByDesc('rsm_social_posts.id')
            ->limit(120)
            ->get([
                'rsm_social_posts.*',
                'rsm_social_accounts.wilayah as account_wilayah',
                'rsm_social_accounts.unit_name as account_unit_name',
                'rsm_social_accounts.instagram_username as account_username',
            ]);

        $totals = [
            'accounts' => $accounts->count(),
            'feed' => (int) $posts->where('media_type', 'feed')->count(),
            'reels' => (int) $posts->where('media_type', 'reels')->count(),
            'story' => (int) $posts->where('media_type', 'story')->count(),
            'score' => (int) $posts->sum('score'),
            'posted_units' => $posts->map(fn ($p) => $p->account_wilayah.'|'.$p->account_unit_name)->unique()->count(),
        ];

        $regionals = $posts->groupBy(fn ($p) => $p->account_wilayah ?: '-')
            ->map(function (Collection $rows, string $wilayah) {
                return [
                    'wilayah' => $wilayah,
                    'feed' => (int) $rows->where('media_type', 'feed')->count(),
                    'reels' => (int) $rows->where('media_type', 'reels')->count(),
                    'story' => (int) $rows->where('media_type', 'story')->count(),
                    'posts' => $rows->count(),
                    'score' => (int) $rows->sum('score'),
                ];
            })
            ->sortBy('wilayah', SORT_NATURAL | SORT_FLAG_CASE)
            ->values()
            ->all();

        $postRows = $posts->map(fn ($p) => [
            'post_date' => optional($p->post_date)->format('d M Y') ?? '-',
            'post_time' => $p->post_time,
            'wilayah' => $p->account_wilayah,
            'unit_name' => $p->account_unit_name,
            'username' => $p->account_username,
            'media_type' => $p->media_type,
            'caption' => $p->caption,
            'score' => (int) $p->score,
        ])->all();

        return ['totals' => $totals, 'regionals' => $regionals, 'posts' => $postRows];
    }
}
