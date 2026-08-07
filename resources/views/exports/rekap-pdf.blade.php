<!doctype html>
<html>
<head>
<meta charset="utf-8">
<style>
    body { font-family: sans-serif; font-size: 10px; color: #111827; }
    h1 { font-size: 14px; margin: 0 0 4px; }
    .meta { margin-bottom: 10px; color: #4b5563; }
    .meta span { margin-right: 16px; }
    table { width: 100%; border-collapse: collapse; }
    th, td { border: 1px solid #d1d5db; padding: 4px 6px; text-align: left; }
    th { background: #e5e7eb; }
    td.num { text-align: right; }
    tfoot td { font-weight: bold; background: #f3f4f6; }
</style>
</head>
<body>
    <h1>Laporan &amp; Rekap</h1>
    <div class="meta">
        <span>Area: {{ $area }}</span>
        <span>Periode: {{ $dateFrom }} s/d {{ $dateTo }}</span>
        <span>Jenis: {{ $type }}</span>
    </div>
    <table>
        <thead>
            <tr>
                <th>Tanggal</th><th>Jenis</th><th>Wilayah</th><th>Unit/Kampus</th><th>Staff</th>
                <th>Judul/Campaign</th><th>Leads</th><th>Closing</th><th>Anggaran</th><th>Realisasi</th><th>Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    <td>{{ $row['report_date'] }}</td>
                    <td>{{ $row['report_type'] }}</td>
                    <td>{{ $row['wilayah'] ?: '-' }}</td>
                    <td>{{ $row['unit_name'] ?: '-' }}</td>
                    <td>{{ $row['staff_name'] ?: '-' }}</td>
                    <td>{{ $row['title'] ?: '-' }}</td>
                    <td class="num">{{ number_format($row['leads_count'], 0, ',', '.') }}</td>
                    <td class="num">{{ number_format($row['closing_count'], 0, ',', '.') }}</td>
                    <td class="num">Rp {{ number_format($row['budget_requested'], 0, ',', '.') }}</td>
                    <td class="num">Rp {{ number_format($row['realization_amount'], 0, ',', '.') }}</td>
                    <td>{{ $row['status'] ?: '-' }}</td>
                </tr>
            @empty
                <tr><td colspan="11">Belum ada data pada filter ini.</td></tr>
            @endforelse
        </tbody>
        @if ($rows !== [])
            <tfoot>
                <tr>
                    <td colspan="6">TOTAL — {{ count($rows) }} laporan</td>
                    <td class="num">{{ number_format(array_sum(array_column($rows, 'leads_count')), 0, ',', '.') }}</td>
                    <td class="num">{{ number_format(array_sum(array_column($rows, 'closing_count')), 0, ',', '.') }}</td>
                    <td class="num">Rp {{ number_format(array_sum(array_column($rows, 'budget_requested')), 0, ',', '.') }}</td>
                    <td class="num">Rp {{ number_format(array_sum(array_column($rows, 'realization_amount')), 0, ',', '.') }}</td>
                    <td></td>
                </tr>
            </tfoot>
        @endif
    </table>
</body>
</html>
