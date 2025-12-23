<?php

declare(strict_types=1);

/*
 * (c) 2025 rc design visual concepts (rc-design.at)
 * _________________________________________________
 * The TYPO3 project - inspiring people to share!
 * _________________________________________________
 */

namespace MiniFranske\FsMediaGallery\Hooks;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2014 Frans Saris <franssaris@gmail.com>
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\DataHandling\DataHandler;

/**
 * Hooks called after sys_file_collection is added/updated/deleted.
 */
class ProcessDatamapHook
{
    /**
     * Trigger updateFolderTree after change in sys_file_collection.
     *
     * @param string $status
     * @param string $table
     * @param        $id
     */
    public function processDatamap_afterDatabaseOperations(
        $status,
        $table,
        $id,
        array $fieldArray,
        DataHandler $dataHandler
    ): void {
        if ($table === 'sys_file_collection') {
            BackendUtility::setUpdateSignal('updateFolderTree');
        }
    }

    /**
     * Trigger updateFolderTree after a sys_file_collection record is deleted.
     *
     * @param string $command
     * @param string $table
     * @param int    $id
     */
    public function processCmdmap_postProcess(
        $command,
        $table,
        $id,
        mixed $value,
        DataHandler $dataHandler,
        mixed $pasteUpdate,
        array $pasteDatamap
    ): void {
        if ($table === 'sys_file_collection') {
            BackendUtility::setUpdateSignal('updateFolderTree');
        }
    }
}
