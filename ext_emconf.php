<?php

declare(strict_types=1);

/*
 * (c) 2025 rc design visual concepts (rc-design.at)
 * _________________________________________________
 * The TYPO3 project - inspiring people to share!
 * _________________________________________________
 */

$EM_CONF[$_EXTKEY] = [
    'title' => 'Media Gallery',
    'description' => 'A media gallery based on the FAL integration of TYPO3.
Show your media assets from your local or remote storage as a gallery of albums.',
    'category' => 'plugin',
    'author' => 'Frans Saris',
    'author_email' => 'franssaris@gmail.com',
    'author_company' => '',
    'state' => 'stable',
    'version' => '6.0.0',
    'constraints' => [
        'depends' => [
            'typo3' => '13.0.0-14.9.99',
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
];
