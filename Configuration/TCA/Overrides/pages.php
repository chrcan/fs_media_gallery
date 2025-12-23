<?php

declare(strict_types=1);

/*
 * (c) 2025 rc design visual concepts (rc-design.at)
 * _________________________________________________
 * The TYPO3 project - inspiring people to share!
 * _________________________________________________
 */

defined('TYPO3') || die('not TYPO3 env');

$GLOBALS['TCA']['pages']['ctrl']['typeicon_classes']['contains-mediagal'] =
    'apps-pagetree-folder-contains-mediagal';

// Add module icon for Folder (page-contains)
$GLOBALS['TCA']['pages']['columns']['module']['config']['items'][] = [
    'MediaGalleries',
    'mediagal',
    'apps-pagetree-folder-contains-mediagal',
];
