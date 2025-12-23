<?php

declare(strict_types=1);

/*
 * (c) 2025 rc design visual concepts (rc-design.at)
 * _________________________________________________
 * The TYPO3 project - inspiring people to share!
 * _________________________________________________
 */

use MiniFranske\FsMediaGallery\Domain\Model\MediaAlbum;

return [
    MediaAlbum::class => [
        'tableName' => 'sys_file_collection',
    ],
];
