<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'Redirects Health Check',
    'description' => 'It provides a Health check command for redirects',
    'category' => 'misc',
    'author' => 'Christoph Lehmann',
    'author_email' => 'typo3@networkteam.com',
    'author_company' => 'networkteam GmbH',
    'state' => 'beta',
    'clearCacheOnLoad' => 0,
    'version' => '0.0.3',
    'constraints' => [
        'depends' => [
            'typo3' => '10.4.0-10.4.99',
            'redirects' => '*'
        ],
        'conflicts' => [
        ],
        'suggests' => [
        ]
    ]
];
