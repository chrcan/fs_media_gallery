<?php

declare(strict_types=1);

namespace MiniFranske\FsMediaGallery\Service;

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
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\DataHandling\SlugHelper;
use TYPO3\CMS\Core\Resource\Folder;
use TYPO3\CMS\Core\Resource\FolderInterface;
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Utility class
 */
class Utility implements SingletonInterface
{
    public function __construct(
        private readonly ConnectionPool $connectionPool
    )
    {
    }
    /**
     * Get storage folders marked as media gallery
     *
     * @return array
     */
    public function getStorageFolders()
    {
        $pages = [];

        if ($this->getBeUser()) {
            $q = $this->getDatabaseConnection()->createQueryBuilder();

            $q->getRestrictions()
                ->removeAll()
                ->add(GeneralUtility::makeInstance(DeletedRestriction::class));

            $quotedIdentifiers = $q->createNamedParameter(['mediagal'], Connection::PARAM_STR_ARRAY);

            $q->select('uid', 'title')
                ->from('pages')
                ->where(
                    $q->expr()->and(
                        $q->expr()->eq('doktype', 254),
                        $q->expr()->in('module', $quotedIdentifiers)
                    )
                )
                ->orderBy('title');

            $statement = $q->executeQuery();
            while ($row = $statement->fetchAssociative()) {
                if (BackendUtility::readPageAccess($row['uid'], $this->getBeUser()->getPagePermsClause(1))) {
                    $pages[$row['uid']] = $row['title'];
                }
            }
        }

        return $pages;
    }

    /**
     * Clear pageCache defined at the storage of the collection/album
     */
    public function clearMediaGalleryPageCache(FolderInterface $folder): void
    {
        $collections = $this->findFileCollectionRecordsForFolder(
            $folder->getStorage()->getUid(),
            $folder->getIdentifier(),
            array_keys($this->getStorageFolders())
        );

        if (!$collections) {
            return;
        }

        /** @var DataHandler $tce */
        $tce = GeneralUtility::makeInstance(DataHandler::class);
        $tce->start([], []);

        foreach ($collections as $collection) {
            $pageConfig = BackendUtility::getPagesTSconfig($collection['pid']);
            if (!empty($pageConfig['TCEMAIN.']['clearCacheCmd'])) {
                $clearCacheCommands = GeneralUtility::trimExplode(',', $pageConfig['TCEMAIN.']['clearCacheCmd'], true);
                $clearCacheCommands = array_unique($clearCacheCommands);
                foreach ($clearCacheCommands as $clearCacheCommand) {
                    $tce->clear_cacheCmd($clearCacheCommand);
                }
            }
        }
    }

    /**
     * Gets the first parentCollections of the given folder and mediaFolderUid(storagepid)
     */
    public function getFirstParentCollections(Folder $folder, int $mediaFolderUid): array
    {
        $parentCollection = [];
        $evalPermissions = $folder->getStorage()->getEvaluatePermissions();
        $folder->getStorage()->setEvaluatePermissions(false);

        // If not root folder (for root folder parent === folder)
        $parentFolder = $folder->getParentFolder();
        if ($parentFolder->getIdentifier() !== $folder->getIdentifier()) {
            $parentCollection = $this->findFileCollectionRecordsForFolder(
                $folder->getStorage()->getUid(),
                $parentFolder->getIdentifier(),
                [$mediaFolderUid]
            );
            if (!count((array)$parentCollection) && $parentFolder instanceof Folder) {
                $parentCollection = $this->getFirstParentCollections($parentFolder, $mediaFolderUid);
            }
        }
        $folder->getStorage()->setEvaluatePermissions($evalPermissions);

        return $parentCollection;
    }

    /**
     * Update file_collection record after move/rename folder
     */
    public function updateFolderRecord(int $oldStorageUid, string $oldIdentifier, int $newStorageUid, string $newIdentifier, ?int $newParentAlbum = 0): void
    {
        $updatedData = [
            //'storage' => $newStorageUid,
            'folder_identifier' => $newStorageUid . ':' . $newIdentifier,
            //'folder' => $newIdentifier
        ];

        if ($newParentAlbum !== null) {
            $updatedData['parentalbum'] = $newParentAlbum;
        }

        $this->getDatabaseConnection()->update(
            'sys_file_collection',
            $updatedData,
            [
                //'storage' => $oldStorageUid,
                'folder_identifier' => $oldStorageUid . ':' . $oldIdentifier,
                //'folder' => $oldIdentifier,
            ]
        );
    }

    /**
     * Delete file_collection when folder is deleted
     *
     * @param int $storageUid
     * @param string $identifier
     */
    public function deleteFolderRecord($storageUid, $identifier): void
    {
        $this->getDatabaseConnection()->update(
            'sys_file_collection',
            ['deleted' => 1],
            [
                //'folder' => $identifier,
                //'storage' => $storageUid
                'folder_identifier' => $storageUid . ':' . $identifier,
            ]
        );
    }

    /**
     * Creates a folderRecord (sys_file_collection)
     *
     * @param string $title The title of the folder(album_name)
     * @param int $collectionStoragePid The pid of the collection/mediaStorage
     * @param int $storageUid The uid of the storage (fileStorage)
     * @param string $identifier The identifier of the folder
     * @param int $parentAlbum The uid of the parentAlbum
     */
    public function createFolderRecord($title, $collectionStoragePid, $storageUid, $identifier, $parentAlbum = 0): void
    {
        $folderRecord = [
            'pid' => (int)$collectionStoragePid,
            'deleted' => 0,
            'hidden' => 0,
            'type' => 'folder',
            'folder_identifier' => (int)$storageUid . ':' . $identifier,
            //'storage' => (int)$storageUid,
            //'folder' => $identifier,
            'title' => $title,
            'parentalbum' => (int)$parentAlbum,
            'webdescription' => '',
        ];

        // Create slug
        $slugTCAConfig = $GLOBALS['TCA']['sys_file_collection']['columns']['slug']['config'];
        /** @var SlugHelper $slugHelper */
        $slugHelper = GeneralUtility::makeInstance(
            SlugHelper::class,
            'sys_file_collection',
            'slug',
            $slugTCAConfig
        );
        $slug = $slugHelper->generate($folderRecord, $collectionStoragePid);
        $folderRecord['slug'] = $slug;

        $this->getDatabaseConnection()->insert('sys_file_collection', $folderRecord);
    }

    /**
     * @param int[]|null $pids
     */
    public function findFileCollectionRecordsForFolder(int $storageUid, string $folder, ?array $pids = null): ?array
    {
        $q = $this->getDatabaseConnection()->createQueryBuilder();

        $q->getRestrictions()
            ->removeAll()
            ->add(GeneralUtility::makeInstance(DeletedRestriction::class));

        $q->select('uid', 'pid', 'title', 'type', 'hidden', 'parentalbum')
            ->from('sys_file_collection')
            ->where(
                $q->expr()->and(
                    // $q->expr()->eq('storage', $q->createNamedParameter($storageUid, Connection::PARAM_INT)),
                    $q->expr()->eq('folder_identifier', $q->createNamedParameter($storageUid . ':' . $folder, Connection::PARAM_STR)),
                    // $q->expr()->eq('folder', $q->createNamedParameter($folder))
                )
            );

        if (is_array($pids) && count($pids) > 0) {
            $q->andWhere(
                $q->expr()->in('pid', $pids)
            );
        }

        return $q->executeQuery()->fetchAllAssociative();
    }

    protected function getDatabaseConnection(string $table = 'sys_file_collection'): Connection
    {
        return $this->connectionPool->getConnectionForTable($table);
    }

    protected function getBeUser(): ?BackendUserAuthentication
    {
        return $GLOBALS['BE_USER'] ?? null;
    }
}
