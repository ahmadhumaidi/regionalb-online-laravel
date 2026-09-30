<?php

namespace App\Services\Content;

use App\Models\RsmUser;
use App\Support\AreaRegionals;
use App\Support\CampusMatcher;
use Illuminate\Http\Client\Pool;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

class SocialContentSpreadsheetService
{
    private const REGIONALS = [
        'Regional 1', 'Regional 2', 'Regional 3',
        'Regional 4', 'Regional 5', 'Regional 6', 'Regional 7',
    ];

    /** @return array{rows: array<int, array<string, mixed>>, synced_at: ?string, error: ?string} */
    public static function septemberRecap(RsmUser $user): array
    {
        try {
            $data = Cache::remember('social-content-sheet-september-recap-v2', now()->addMinutes(15), function (): array {
                $feed = self::readWorkbook((string) config('services.social_content_sheets.feed_url'), 'feed');
                $story = self::readWorkbook((string) config('services.social_content_sheets.story_url'), 'story');

                return ['rows' => self::merge($feed, $story), 'synced_at' => now()->toIso8601String()];
            });

            $data['rows'] = array_values(array_filter(
                $data['rows'],
                fn (array $row): bool => self::visible($row, $user)
            ));
            $data['error'] = null;

            return $data;
        } catch (Throwable $exception) {
            report($exception);

            return ['rows' => [], 'synced_at' => null, 'error' => 'Rekap spreadsheet belum dapat dimuat. Silakan coba beberapa saat lagi.'];
        }
    }

    /** @return array<int, array<string, mixed>> */
    private static function readWorkbook(string $url, string $type): array
    {
        preg_match('~/d/([^/]+)~', $url, $matches);
        $spreadsheetId = $matches[1] ?? '';
        if ($spreadsheetId === '') {
            return [];
        }

        $responses = Http::pool(fn (Pool $pool): array => array_map(
            fn (string $regional) => $pool
                ->as($regional)
                ->timeout(20)
                ->get("https://docs.google.com/spreadsheets/d/{$spreadsheetId}/gviz/tq", [
                    'tqx' => 'out:csv',
                    'sheet' => $regional,
                    'range' => $type === 'feed' ? 'A1:DE100' : 'A1:EE100',
                ]),
            self::REGIONALS
        ));

        $rows = [];
        foreach (self::REGIONALS as $regional) {
            $response = $responses[$regional] ?? null;
            if (! $response || ! $response->successful()) {
                continue;
            }
            $csvRows = self::parseCsv($response->body());
            $startRow = $type === 'feed' ? 2 : 3;
            foreach (array_slice($csvRows, $startRow) as $csvRow) {
                $campus = trim((string) ($csvRow[1] ?? ''));
                if ($campus === '') {
                    continue;
                }

                $isFeed = $type === 'feed';
                $days = $isFeed
                    ? self::sequentialDays($csvRow, 'BZ', 30)
                    : self::storySeptemberDays($csvRow);
                $rows[] = [
                    'regional' => $regional,
                    'campus' => $campus,
                    'staff' => trim((string) ($csvRow[$isFeed ? 2 : 3] ?? '')),
                    'profile_url' => trim((string) ($csvRow[$isFeed ? 3 : 2] ?? '')),
                    $type.'_days' => $days,
                    $type.'_total' => array_sum($days),
                ];
            }
        }

        return $rows;
    }

    /** @return array<int, array<int, string|null>> */
    private static function parseCsv(string $csv): array
    {
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $csv);
        rewind($stream);
        $rows = [];
        while (($row = fgetcsv($stream, escape: '')) !== false) {
            $rows[] = $row;
        }
        fclose($stream);

        return $rows;
    }

    /** @param array<int, array<string, mixed>> $feed @param array<int, array<string, mixed>> $story */
    private static function merge(array $feed, array $story): array
    {
        $merged = [];
        foreach (array_merge($feed, $story) as $row) {
            $key = $row['regional'].'|'.Str::of($row['campus'])->lower()->ascii()->replaceMatches('/[^a-z0-9]+/', '')->value();
            $merged[$key] ??= [
                'regional' => $row['regional'],
                'campus' => $row['campus'],
                'staff' => '',
                'profile_url' => '',
                'feed_total' => 0,
                'story_total' => 0,
                'feed_days' => array_fill(1, 30, 0),
                'story_days' => array_fill(1, 30, 0),
            ];
            foreach (['staff', 'profile_url'] as $field) {
                if ($row[$field] !== '') {
                    $merged[$key][$field] = $row[$field];
                }
            }
            if (array_key_exists('feed_total', $row)) {
                $merged[$key]['feed_total'] = $row['feed_total'];
                $merged[$key]['feed_days'] = $row['feed_days'];
            }
            if (array_key_exists('story_total', $row)) {
                $merged[$key]['story_total'] = $row['story_total'];
                $merged[$key]['story_days'] = $row['story_days'];
            }
        }

        return collect($merged)
            ->sortBy(fn (array $row): string => $row['regional'].'|'.$row['campus'])
            ->values()
            ->all();
    }

    private static function visible(array $row, RsmUser $user): bool
    {
        if (! in_array($row['regional'], AreaRegionals::forArea($user->area ?: 'Regional B'), true)) {
            return false;
        }
        if ($user->role === RsmUser::ROLE_KOORDINATOR) {
            return $row['regional'] === $user->regional;
        }
        if ($user->role === RsmUser::ROLE_STAFF) {
            return $row['regional'] === $user->regional
                && CampusMatcher::matches($row['campus'], (string) $user->campus_name);
        }

        return true;
    }

    /** @param array<int, string|null> $row @return array<int, int> */
    private static function sequentialDays(array $row, string $from, int $dayCount): array
    {
        $days = [];
        $start = self::columnIndex($from);
        for ($day = 1; $day <= $dayCount; $day++) {
            $days[$day] = self::number($row[$start + $day - 1] ?? 0);
        }

        return $days;
    }

    /** @param array<int, string|null> $row @return array<int, int> */
    private static function storySeptemberDays(array $row): array
    {
        $columns = array_merge(
            self::columnsBetween('BR', 'BW'),
            self::columnsBetween('BZ', 'CE'),
            self::columnsBetween('CH', 'CN'),
            self::columnsBetween('CQ', 'CW'),
            self::columnsBetween('CZ', 'DC'),
        );
        $days = [];
        foreach (array_slice($columns, 0, 30) as $offset => $column) {
            $days[$offset + 1] = self::number($row[self::columnIndex($column)] ?? 0);
        }

        return $days;
    }

    /** @return array<int, string> */
    private static function columnsBetween(string $from, string $to): array
    {
        $columns = [];
        for ($number = self::columnIndex($from) + 1; $number <= self::columnIndex($to) + 1; $number++) {
            $value = $number;
            $letters = '';
            while ($value > 0) {
                $value--;
                $letters = chr(65 + ($value % 26)).$letters;
                $value = intdiv($value, 26);
            }
            $columns[] = $letters;
        }

        return $columns;
    }

    private static function columnIndex(string $letters): int
    {
        $number = 0;
        foreach (str_split($letters) as $letter) {
            $number = ($number * 26) + ord($letter) - 64;
        }

        return $number - 1;
    }

    private static function number(mixed $value): int
    {
        return is_numeric($value) ? (int) round((float) $value) : 0;
    }
}
