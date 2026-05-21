<?php

/**
 * Curated landmarks → nearby streets for local real-estate search (Kosovo cities).
 * Extend per city as Powerbook grows.
 */
return [
    'ferizaj' => [
        'gjykata' => [
            'aliases' => [
                'gjykata', 'gjykates', 'gjykatës', 'gjykaten', 'te gjykata', 'te gjykates',
                'afër gjykatës', 'afer gjykates', 'court', 'courthouse', 'gjykata themelore',
            ],
            'label_sq' => 'Gjykata Themelore e Ferizajt',
            'label_en' => 'Ferizaj District Court',
            'streets' => [
                'Adem Jashari',
                'Gjon Serreqi',
                'Ismail Qemaili',
                'Xhorxh Bush',
                'Skënderbeu',
                'Enver Maloku',
                'Ibrahim Rugova',
                'Varosh',
            ],
            'neighborhoods' => ['Qendra', 'Varosh', 'Dardania'],
        ],
        'qendra' => [
            'aliases' => ['qendra', 'qendër', 'qender', 'centar', 'center', 'city center'],
            'label_sq' => 'Qendra e Ferizajt',
            'label_en' => 'Ferizaj city center',
            'streets' => [
                'Adem Jashari',
                'Skënderbeu',
                'Gjon Serreqi',
                'Ibrahim Rugova',
            ],
            'neighborhoods' => ['Qendra'],
        ],
    ],
];
