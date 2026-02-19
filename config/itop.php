<?php

return [
    // Default driver: 'db' or 'api'
    'default_driver' => env('ITOP_DRIVER', 'db'),

    'instances' => [
        'itop_a' => ['driver'=>'db','connection'=>'itop1','table'=>'ticket'],
        'itop_b' => ['driver'=>'db','connection'=>'itop2','table'=>'ticket'],
    ],
    'flows' => [
        'a_to_b' => [
            'from'=>'itop_a','to'=>'itop_b',
            'mapping'=>[],
            'relations' => [
                'contacts' => [
                    'table' => 'contact',
                    'foreign_key' => 'ticket_id',
                    'map_to_field' => 'contact_id',
                    'unique_keys' => ['email'],
                ],
                'persons' => [
                    'table' => 'person',
                    'foreign_key' => 'ticket_id',
                    'map_to_field' => 'person_id',
                    'unique_keys' => ['email'],
                ],
            ],
        ],
    ],

    // Default table names for internal package storage
    'migrations' => [
        'mappings_table' => 'itop_sync_mappings',
        'logs_table' => 'itop_sync_logs',
    ],
];
