<?php

return [
    'indicators' => [
        'reg' => [
            'label' => 'Reg',
            'group' => 'Hasil PMB',
            'default_weight' => 10,
            'metric_key' => 'registrasi_personal',
        ],
        'herreg' => [
            'label' => 'Herreg',
            'group' => 'Hasil PMB',
            'default_weight' => 15,
            'metric_key' => 'herregistrasi_personal',
        ],
        'reg_kampus' => [
            'label' => 'Reg Kampus',
            'group' => 'Hasil Kampus',
            'default_weight' => 8,
            'metric_key' => 'registrasi_kampus',
        ],
        'herreg_kampus' => [
            'label' => 'Herreg Kampus',
            'group' => 'Hasil Kampus',
            'default_weight' => 12,
            'metric_key' => 'herregistrasi_kampus',
        ],
        'lap_iklan' => [
            'label' => 'Lap. Iklan',
            'group' => 'Iklan',
            'default_weight' => 5,
            'metric_key' => 'laporan_iklan',
        ],
        'realisasi_iklan' => [
            'label' => 'Realisasi Iklan',
            'group' => 'Iklan',
            'default_weight' => 7,
            'metric_key' => 'realisasi_iklan',
            'step' => '0.01',
        ],
        'fu' => [
            'label' => 'FU',
            'group' => 'Aktivitas',
            'default_weight' => 10,
            'metric_key' => 'follow_up_total',
        ],
        'leads' => [
            'label' => 'Leads',
            'group' => 'Iklan',
            'default_weight' => 8,
            'metric_key' => 'leads_total',
        ],
        'total_lap' => [
            'label' => 'Total Lap.',
            'group' => 'Aktivitas',
            'default_weight' => 5,
            'metric_key' => 'laporan_total',
        ],
        'aktif' => [
            'label' => 'Aktif',
            'group' => 'Aktivitas',
            'default_weight' => 5,
            'metric_key' => 'hari_aktif',
        ],
        'share_fb' => [
            'label' => 'Share FB',
            'group' => 'Digital',
            'default_weight' => 4,
            'metric_key' => 'share_fb_group',
        ],
        'live_stream' => [
            'label' => 'Live Stream',
            'group' => 'Digital',
            'default_weight' => 6,
            'metric_key' => 'live_streaming',
        ],
        'aff_mhs' => [
            'label' => 'Aff. Mhs',
            'group' => 'Afiliasi',
            'default_weight' => 3,
            'metric_key' => 'affiliator_mahasiswa',
        ],
        'aff_non_mhs' => [
            'label' => 'Aff. Non Mhs',
            'group' => 'Afiliasi',
            'default_weight' => 2,
            'metric_key' => 'affiliator_non_mahasiswa',
        ],
    ],
];
