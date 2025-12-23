<?php

declare(strict_types=1);

/*
 * (c) 2025 rc design visual concepts (rc-design.at)
 * _________________________________________________
 * The TYPO3 project - inspiring people to share!
 * _________________________________________________
 */

namespace MiniFranske\FsMediaGallery\ContextMenu\ItemProviders;

use MiniFranske\FsMediaGallery\Service\Utility;
use TYPO3\CMS\Backend\ContextMenu\ItemProviders\AbstractProvider;
use TYPO3\CMS\Core\Resource\Folder;
use TYPO3\CMS\Core\Resource\FolderInterface;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * This provider add a new item to the context menu of folders to turn them into a gallery.
 */
class FsMediaGalleryProvider extends AbstractProvider
{
    protected $itemsConfiguration = [];

    protected ?Folder $folder = null;

    /**
     * @return bool
     */
    public function canHandle(): bool
    {
        return $this->table === 'sys_file';
    }

    /**
     * @return int
     */
    public function getPriority(): int
    {
        return 90;
    }

    /**
     * Initialize file object.
     */
    protected function initialize(): void
    {
        parent::initialize();
        $resource = GeneralUtility::makeInstance(ResourceFactory::class)
            ->retrieveFileOrFolderObject($this->identifier);
        if ($resource instanceof Folder
            && in_array(
                $resource->getRole(),
                [FolderInterface::ROLE_DEFAULT, FolderInterface::ROLE_USERUPLOAD],
                true
            )
        ) {
            $this->folder = $resource;
        }
    }

    /**
     * Adds the media album add/edit menu items.
     *
     * @param array $items
     *
     * @return array
     */
    public function addItems(array $items): array
    {
        $this->initialize();
        if (!($this->folder instanceof Folder)) {
            return $items;
        }

        $utility = GeneralUtility::makeInstance(Utility::class);
        $mediaFolders = $utility->getStorageFolders();

        if ((is_countable($mediaFolders) ? count($mediaFolders) : 0) === 0) {
            $this->itemsConfiguration['add-media-gallery'] = [
                'label' => $this->sL('module.buttons.createAlbum'),
                'iconIdentifier' => 'action-add-album',
                'callbackAction' => 'missingMediaFolder',
                'title' => $this->sL('module.alerts.firstCreateStorageFolder'),
            ];
        }

        if ((is_countable($mediaFolders) ? count($mediaFolders) : 0) > 0) {
            $collections = $utility->findFileCollectionRecordsForFolder(
                $this->folder->getStorage()->getUid(),
                $this->folder->getIdentifier(),
                array_keys($mediaFolders)
            );

            foreach ($collections as $collection) {
                $this->itemsConfiguration['edit-media-gallery-' . $collection['uid']] = [
                    'label' => sprintf($this->sL('module.buttons.editAlbum'), $collection['title']),
                    'iconIdentifier' => 'action-edit-album',
                    'callbackAction' => 'mediaAlbum',
                    'uid' => $collection['uid'],
                ];
            }

            if (!count((array)$collections)) {
                foreach ($mediaFolders as $uid => $title) {
                    // Find parent album for auto setting parent album
                    $parentUid = 0;
                    $parents = $utility->findFileCollectionRecordsForFolder(
                        $this->folder->getStorage()->getUid(),
                        $this->folder->getParentFolder()->getIdentifier(),
                        [$uid]
                    );

                    // If parent(s) found we take the first one
                    if (count((array)$parents)) {
                        $parentUid = $parents[0]['uid'];
                    }

                    $this->itemsConfiguration['add-media-gallery-' . $uid] = [
                        'label' => sprintf($this->sL('module.buttons.createAlbumIn'), $title),
                        'iconIdentifier' => 'action-add-album',
                        'callbackAction' => 'mediaAlbum',
                        'pid' => $uid,
                        'parentUid' => $parentUid,
                    ];
                }
            }
        }

        if ($this->itemsConfiguration !== []) {
            $items += $this->prepareItems(
                ['media_gallery_divider' => ['type' => 'divider']]
                + $this->itemsConfiguration
            );
        }

        return $items;
    }

    /**
     * @param string $itemName
     *
     * @return array
     */
    protected function getAdditionalAttributes(string $itemName): array
    {
        $itemInfo = $this->itemsConfiguration[$itemName] ?? [];

        return [
            'data-callback-module' => 'TYPO3/CMS/FsMediaGallery/ContextMenuActions',
            'data-album-record-uid' => $itemInfo['uid'] ?? 0,
            'data-pid' => $itemInfo['pid'] ?? 0,
            'data-parent-uid' => $itemInfo['parentUid'] ?? 0,
            'data-title' => $itemInfo['title'] ?? ucfirst(trim(str_replace('_', ' ', $this->folder->getName()))),
            'data-storage' => $this->folder->getStorage()->getUid(),
            'data-folder' => $this->folder->getIdentifier(),
        ];
    }

    /**
     * Get language string.
     *
     * @return string
     */
    protected function sL(string $key, string $languageFile = 'LLL:EXT:fs_media_gallery/Resources/Private/Language/locallang_be.xlf'): string
    {
        return $this->languageService->sL($languageFile . ':' . $key);
    }
}
