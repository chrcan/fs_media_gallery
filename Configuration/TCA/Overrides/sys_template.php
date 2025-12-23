<?php

declare(strict_types=1);

/*
 * (c) 2025 rc design visual concepts (rc-design.at)
 * _________________________________________________
 * The TYPO3 project - inspiring people to share!
 * _________________________________________________
 */

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

defined('TYPO3') || die('not TYPO3 env');

// Media Gellery typoscript
ExtensionManagementUtility::addStaticFile(
    'fs_media_gallery',
    'Configuration/TypoScript',
    'Media Gallery'
);
// Add Theme 'Bootstrap5'
ExtensionManagementUtility::addStaticFile(
    'fs_media_gallery',
    'Configuration/TypoScript/Themes/Bootstrap5',
    'Media Gallery Theme \'Bootstrap5\''
);
