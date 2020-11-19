<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'Redirects Healthcheck',
    'description' => 'Healthcheck for redirects',
    'category' => 'misc',
    'author' => 'Christoph Lehmann',
    'author_email' => 'typo3@networkteam.com',
    'author_company' => 'networkteam GmbH',
    'state' => 'beta',
    'clearCacheOnLoad' => 0,
    'version' => '0.0.1',
    'constraints' => [
        'depends' => [
            'typo3' => '9.5.0-10.4.99',
            'redirects' => '*'
        ],
        'conflicts' => [
        ],
        'suggests' => [
        ]
    ]
];
