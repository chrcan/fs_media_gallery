<?php

declare(strict_types=1);

namespace MiniFranske\FsMediaGallery\Controller;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2014 Frans Saris <franssaris@gmail.com>
 *
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 3 of the License, or
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

use GeorgRinger\NumberedPagination\NumberedPagination;
use MiniFranske\FsMediaGallery\Domain\Model\MediaAlbum;
use MiniFranske\FsMediaGallery\Domain\Repository\MediaAlbumRepository;
use MiniFranske\FsMediaGallery\Pagination\ExtendedArrayPaginator;
use MiniFranske\FsMediaGallery\Utility\TypoScriptUtility;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Error\Http\PageNotFoundException;
use TYPO3\CMS\Core\Http\ImmediateResponseException;
use TYPO3\CMS\Core\Pagination\ArrayPaginator;
use TYPO3\CMS\Core\Pagination\SimplePagination;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Annotation\IgnoreValidation;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface;
use TYPO3\CMS\Extbase\Http\ForwardResponse;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Extbase\Mvc\Controller\Arguments;
use TYPO3\CMS\Extbase\Persistence\Exception\InvalidQueryException;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;
use TYPO3\CMS\Frontend\Controller\ErrorController;

/**
 * MediaAlbumController
 */
class MediaAlbumController extends ActionController
{
    protected MediaAlbumRepository $mediaAlbumRepository;

    /**
     * Injects the Configuration Manager
     *
     * @param ConfigurationManagerInterface $configurationManager Instance of the Configuration Manager
     */
    public function injectConfigurationManager(ConfigurationManagerInterface $configurationManager): void
    {
        $this->configurationManager = $configurationManager;
    }

    /**
     * Injects the MediaAlbumRepository
     */
    public function injectMediaAlbumRepository(MediaAlbumRepository $mediaAlbumRepository): void
    {
        $this->mediaAlbumRepository = $mediaAlbumRepository;
    }

    public function __construct()
    {
        $this->arguments = GeneralUtility::makeInstance(Arguments::class);
    }

    protected function initializeAction(): void
    {
        // Settings MediaAlbumRepository
        if (!empty($this->settings['allowedAssetMimeTypes'])) {
            $this->mediaAlbumRepository->setAllowedAssetMimeTypes(GeneralUtility::trimExplode(
                ',',
                $this->settings['allowedAssetMimeTypes']
            ));
        }
        if (isset($this->settings['album']['assets']['orderBy'])) {
            $this->mediaAlbumRepository->setAssetsOrderBy($this->settings['album']['assets']['orderBy']);
        }
        if (isset($this->settings['album']['assets']['orderDirection'])) {
            $this->mediaAlbumRepository->setAssetsOrderDirection($this->settings['album']['assets']['orderDirection']);
        }

        // Settings
        $frameworkSettings = $this->configurationManager->getConfiguration(
            ConfigurationManagerInterface::CONFIGURATION_TYPE_FRAMEWORK,
            'fsmediagallery',
            'fsmediagallery_mediagallery'
        );
        $flexformSettings = $this->configurationManager->getConfiguration(
            ConfigurationManagerInterface::CONFIGURATION_TYPE_SETTINGS
        );

        // merge Framework (TypoScript) and Flexform settings
        if (isset($frameworkSettings['settings']['overrideFlexformSettingsIfEmpty'])) {
            $typoScriptUtility = GeneralUtility::makeInstance(TypoScriptUtility::class);
            $mergedSettings = $typoScriptUtility->override($flexformSettings, $frameworkSettings);
            $this->settings = $mergedSettings;
        } else {
            $this->settings = $flexformSettings;
        }

        /*
         * sync persistence.storagePid=settings.startingpoint and persistence.recursive=settings.recursive
         */
        // overwrite persistence.storagePid if settings.startingpoint is defined in flexform
        if (!empty($this->settings['startingpoint'])) {
            $frameworkSettings['persistence']['storagePid'] = $this->settings['startingpoint'];
            // if settings.startingpoint is not set in flexform, use persistence.storagePid from TS
        } elseif (!empty($frameworkSettings['persistence']['storagePid'])) {
            $this->settings['startingpoint'] = $frameworkSettings['persistence']['storagePid'];
            // startingpoint/storagePid is not set via TS nor flexforms > fallback to current pid
        } else {
            // $this->settings['startingpoint'] = $frameworkSettings['persistence']['storagePid'] = $GLOBALS['TSFE']->id;
            // $GLOBALS['TSFE'] is deprecated in TYPO3 v12
            $this->settings['startingpoint'] = $frameworkSettings['persistence']['storagePid'] = $GLOBALS['TYPO3_REQUEST']->getQueryParams()['id'] ?? $frameworkSettings['persistence']['storagePid'];
        }

        // set persistence.recursive if settings.recursive is defined in flexform
        if (!empty($this->settings['recursive'])) {
            $frameworkSettings['persistence']['recursive'] = $this->settings['recursive'];
            // if settings.recursive is not set in flexform, use persistence.recursive from TS
        } elseif (!empty($frameworkSettings['persistence']['recursive'])) {
            $this->settings['recursive'] = $frameworkSettings['persistence']['recursive'];
            // recursive is not set via TS nor flexforms
        } else {
            $this->settings['recursive'] = $frameworkSettings['persistence']['recursive'] = 0;
        }

        // write back altered configuration
        $this->configurationManager->setConfiguration($frameworkSettings);

        // check some settings
        if (!isset($this->settings['list']['pagination']['itemsPerPage']) || $this->settings['list']['pagination']['itemsPerPage'] < 1) {
            $this->settings['list']['pagination']['itemsPerPage'] = 12;
        }
        if (!isset($this->settings['album']['pagination']['itemsPerPage']) || $this->settings['album']['pagination']['itemsPerPage'] < 1) {
            $this->settings['album']['pagination']['itemsPerPage'] = 12;
        }
        // correct resizeMode 's' set in flexforms (flexforms value '' is used for inherit/definition by TS)
        if (isset($this->settings['list']['thumb']['resizeMode']) && $this->settings['list']['thumb']['resizeMode'] == 's') {
            $this->settings['list']['thumb']['resizeMode'] = '';
        }
        if (isset($this->settings['album']['thumb']['resizeMode']) && $this->settings['album']['thumb']['resizeMode'] == 's') {
            $this->settings['album']['thumb']['resizeMode'] = '';
        }
        if (isset($this->settings['detail']['asset']['resizeMode']) && $this->settings['detail']['asset']['resizeMode'] == 's') {
            $this->settings['detail']['asset']['resizeMode'] = '';
        }
        if (isset($this->settings['random']['thumb']['resizeMode']) && $this->settings['random']['thumb']['resizeMode'] == 's') {
            $this->settings['random']['thumb']['resizeMode'] = '';
        }

    }

    /**
     * Set album uid restrictions as defined in settings
     * By setting this in the repository also the MediaAlbum::getAlbums()
     * and MediaAlbum::getRandomAlbum() is restricted to these uids.
     */
    protected function setAlbumUidRestrictions(): void
    {
        $mediaAlbumsUids = GeneralUtility::trimExplode(',', $this->settings['mediaAlbumsUids'], true);
        $this->mediaAlbumRepository->setAlbumUids($mediaAlbumsUids);
        $this->mediaAlbumRepository->setUseAlbumUidsAsExclude(!empty($this->settings['useAlbumFilterAsExclude']));
    }

    /**
     * NestedList Action
     * Displays a (nested) list of albums; default/show action in fs_media_gallery <= 1.0.0
     *
     * @param int $mediaAlbum (this is not directly mapped to an object to handle 404 on our own)
     * @throws InvalidQueryException
     */
    public function nestedListAction(int $mediaAlbum = 0): ResponseInterface
    {
        $mediaAlbumId = $mediaAlbum ?: null;
        $showBackLink = true;

        $this->setAlbumUidRestrictions();

        // Single view
        if ($mediaAlbumId) {
            /** @var MediaAlbum $mediaAlbum */
            $mediaAlbum = $this->mediaAlbumRepository->findByUid($mediaAlbumId);
            if (!$mediaAlbum) {
                return $this->pageNotFound(LocalizationUtility::translate('no_album_found', 'FsMediaGallery'));
            }
        }

        /**
         * No album selected and album restriction set, find all "root" albums
         * Albums without parent or with parent not selected as allowed
         */
        if ($mediaAlbumId === null && $this->mediaAlbumRepository->getAlbumUids() !== []) {
            $mediaAlbums = [];
            $all = $this->mediaAlbumRepository->findAll((bool)$this->settings['list']['hideEmptyAlbums'], $this->settings['list']['orderBy'], $this->settings['list']['orderDirection']);
            foreach ($all as $album) {
                $parent = $album->getParentalbum();
                if (
                    $parent === null
                    || (!$this->mediaAlbumRepository->getUseAlbumUidsAsExclude() && !in_array(
                        $parent->getUid(),
                        $this->mediaAlbumRepository->getAlbumUids()
                    ))
                    || ($this->mediaAlbumRepository->getUseAlbumUidsAsExclude() && in_array(
                        $parent->getUid(),
                        $this->mediaAlbumRepository->getAlbumUids()
                    ))
                ) {
                    $mediaAlbums[] = $album;
                }
            }
        } else {
            $mediaAlbums = $this->mediaAlbumRepository->findByParentAlbum(
                $mediaAlbum,
                (bool)$this->settings['list']['hideEmptyAlbums'],
                $this->settings['list']['orderBy'],
                $this->settings['list']['orderDirection']
            );
        }

        // when only 1 album skip gallery view
        if ($mediaAlbumId === null && !empty($this->settings['list']['skipListWhenOnlyOneAlbum']) && count($mediaAlbums) === 1) {
            $mediaAlbums = $this->mediaAlbumRepository->findByParentAlbum(
                $mediaAlbums[0],
                (bool)$this->settings['list']['hideEmptyAlbums'],
                $this->settings['list']['orderBy'],
                $this->settings['list']['orderDirection']
            );
            $showBackLink = false;
        }

        if (
            $mediaAlbum && $mediaAlbum->getParentalbum() && (
                $this->mediaAlbumRepository->getAlbumUids() === []
                ||
                (!$this->mediaAlbumRepository->getUseAlbumUidsAsExclude() && in_array(
                    $mediaAlbum->getParentalbum()->getUid(),
                    $this->mediaAlbumRepository->getAlbumUids()
                ))
                ||
                ($this->mediaAlbumRepository->getUseAlbumUidsAsExclude() && !in_array(
                    $mediaAlbum->getParentalbum()->getUid(),
                    $this->mediaAlbumRepository->getAlbumUids()
                ))
            )
        ) {
            $this->view->assign('parentAlbum', $mediaAlbum->getParentalbum());
        }

        $this->view->assign('showBackLink', $showBackLink);
        $this->view->assign('mediaAlbums', $mediaAlbums);
        $this->view->assign('mediaAlbum', $mediaAlbum);

        if ($mediaAlbums) {
            $this->view->assign('mediaAlbumsPagination', $this->getAlbumsPagination($mediaAlbums));
        }
        if ($mediaAlbum) {
            $this->view->assign('mediaAlbumPagination', $this->getAlbumPagination($mediaAlbum));
        }

        return $this->htmlResponse();
    }

    /**
     * FlatList Action
     * Displays a (one-dimensional, flattened) list of albums
     *
     * @param int $mediaAlbum (this is not directly mapped to an object to handle 404 on our own)
     */
    public function flatListAction(int $mediaAlbum = 0): ResponseInterface
    {
        $mediaAlbums = null;
        $showBackLink = true;
        if ($mediaAlbum) {
            // if an album is given, display it
            $mediaAlbum = $this->mediaAlbumRepository->findByUid($mediaAlbum);
            if (!$mediaAlbum) {
                return $this->pageNotFound(LocalizationUtility::translate('no_album_found', 'FsMediaGallery'));
            }
            $this->view->assign('displayMode', 'album');
            $this->view->assign('mediaAlbum', $mediaAlbum);
        } else {
            // display the album list
            $mediaAlbums = $this->mediaAlbumRepository->findAll(
                $this->settings['list']['hideEmptyAlbums'],
                $this->settings['list']['orderBy'],
                $this->settings['list']['orderDirection']
            );
            $this->view->assign('displayMode', 'flatList');
            $this->view->assign('mediaAlbums', $mediaAlbums);
            $showBackLink = false;
        }
        $this->view->assign('showBackLink', $showBackLink);

        if ($mediaAlbums) {
            $this->view->assign('mediaAlbumsPagination', $this->getAlbumsPagination($mediaAlbums));
        }
        if ($mediaAlbum) {
            $this->view->assign('mediaAlbumPagination', $this->getAlbumPagination($mediaAlbum));
        }

        return $this->htmlResponse();
    }

    /**
     * Show single Album (defined in FlexForm/TS) Action
     * As showAlbumAction() displays any album by the given param this function gets its value from TS/Felxform
     * This could be merged with showAlbumAction() if there is a way to determine which switchableControllerActions is defined in Felxform.
     */
    public function showAlbumByConfigAction(): ResponseInterface
    {
        // get all request arguments (e.g. pagination widget)
        $arguments = $this->request->getArguments();
        // set album id from settings
        $arguments['mediaAlbum'] = $this->settings['mediaAlbum'] ?? null;

        return (new ForwardResponse('showAlbum'))->withArguments($arguments);
    }

    /**
     * Show single Album Action
     *
     * @param int|null $mediaAlbum (this is not directly mapped to an object to handle 404 on our own)
     * @return ResponseInterface
     * @throws PageNotFoundException
     */
    public function showAlbumAction(?int $mediaAlbum = null): ResponseInterface
    {
        $albumUid = $mediaAlbum ?: (int)($this->settings['mediaAlbum'] ?? 0);

        if ($albumUid <= 0) {
            // UID not found → custom 404
            throw new PageNotFoundException('MediaAlbum UID missing', 1646232872);
        }

        $respectStorage = ($albumUid !== (int)($this->settings['mediaAlbum'] ?? 0));

        $mediaAlbumObject = $this->mediaAlbumRepository->findByUid($albumUid, $respectStorage);

        if (!$mediaAlbumObject instanceof MediaAlbum) {
            // UID does not exist or access denied → explicit 404
            throw new PageNotFoundException('MediaAlbum not found', 1646232873);
        }

        $this->view->assignMultiple([
            'mediaAlbum' => $mediaAlbum,
            'mediaAlbumPagination' => $this->getAlbumPagination($mediaAlbumObject),
            'showBackLink' => false,
        ]);

        return $this->htmlResponse();
    }

    /**
     * Show single media asset from album
     *
     * @throws ImmediateResponseException
     */
    #[IgnoreValidation(['value' => ''])]
    public function showAssetAction(MediaAlbum $mediaAlbum, int $mediaAssetUid): ResponseInterface
    {
        if (isset($this->settings['album']['assets']['orderBy'])) {
            $mediaAlbum->setAssetsOrderBy($this->settings['album']['assets']['orderBy']);
        }

        if (isset($this->settings['album']['assets']['orderDirection'])) {
            $mediaAlbum->setAssetsOrderDirection($this->settings['album']['assets']['orderDirection']);
        }

        [$previousAsset, $mediaAsset, $nextAsset] = $mediaAlbum->getPreviousCurrentAndNext($mediaAssetUid);
        if (!$mediaAsset) {
            $message = LocalizationUtility::translate('asset_not_found', 'FsMediaGallery');
            return $this->pageNotFound((empty($message) ? 'Asset not found.' : $message));
        }
        $this->view->assign('previousAsset', $previousAsset);
        $this->view->assign('nextAsset', $nextAsset);
        $this->view->assign('mediaAsset', $mediaAsset);
        $this->view->assign('mediaAlbum', $mediaAlbum);

        return $this->htmlResponse();
    }

    /**
     * Show random media asset
     */
    public function randomAssetAction(): ResponseInterface
    {
        $this->setAlbumUidRestrictions();

        $mediaAlbum = $this->mediaAlbumRepository->findRandom();
        $this->view->assign('mediaAlbum', $mediaAlbum);

        return $this->htmlResponse();
    }

    /**
     * If there were validation errors, we don't want to write details like
     * "An error occurred while trying to call Tx_Community_Controller_UserController->updateAction()"
     *
     * @return string|bool The flash message or FALSE if no flash message should be set
     */
    protected function getErrorFlashMessage(): bool
    {
        return false;
    }

    /**
     * Page not found wrapper
     *
     * @throws ImmediateResponseException
     */
    protected function pageNotFound(string $message): ResponseInterface
    {
        if (!empty($GLOBALS['TSFE'])) {
            $response = GeneralUtility::makeInstance(ErrorController::class)->pageNotFoundAction($GLOBALS['TYPO3_REQUEST'], $message);
            throw new ImmediateResponseException($response, 2946443965);
        }

        return $this->htmlResponse($message);
    }

    private function getAlbumPagination(MediaAlbum $album): array
    {
        $paginationConfiguration = $this->settings['album']['pagination'] ?? [];

        $itemsPerPage = (int)($paginationConfiguration['itemsPerPage'] ?? 10);
        $maximumNumberOfLinks = (int)($paginationConfiguration['maximumNumberOfLinks'] ?? 0);

        $currentPage = $this->request->hasArgument('currentPage') ? (int)$this->request->getArgument('currentPage') : 1;
        $paginator = GeneralUtility::makeInstance(ExtendedArrayPaginator::class, $album->getAssets(), $currentPage, $itemsPerPage);
        $paginationClass = $paginationConfiguration['class'] ?? SimplePagination::class;
        if ($paginationClass === NumberedPagination::class && $maximumNumberOfLinks) {
            $pagination = GeneralUtility::makeInstance(NumberedPagination::class, $paginator, $maximumNumberOfLinks);
        } else {
            $pagination = GeneralUtility::makeInstance(SimplePagination::class, $paginator);
        }

        return [
            'currentPage' => $currentPage,
            'paginator' => $paginator,
            'pagination' => $pagination,
        ];
    }

    private function getAlbumsPagination(array $albums): array
    {
        $paginationConfiguration = $this->settings['list']['pagination'] ?? [];

        $itemsPerPage = (int)($paginationConfiguration['itemsPerPage'] ?? 10);
        $maximumNumberOfLinks = (int)($paginationConfiguration['maximumNumberOfLinks'] ?? 0);

        $currentPage = $this->request->hasArgument('currentAlbumPage') ? (int)$this->request->getArgument('currentAlbumPage') : 1;
        $paginator = GeneralUtility::makeInstance(ArrayPaginator::class, $albums, $currentPage, $itemsPerPage);
        $paginationClass = $paginationConfiguration['class'] ?? SimplePagination::class;
        if ($paginationClass === NumberedPagination::class && $maximumNumberOfLinks) {
            $pagination = GeneralUtility::makeInstance(NumberedPagination::class, $paginator, $maximumNumberOfLinks);
        } else {
            $pagination = GeneralUtility::makeInstance(SimplePagination::class, $paginator);
        }

        return [
            'currentPage' => $currentPage,
            'paginator' => $paginator,
            'pagination' => $pagination,
        ];
    }
}
