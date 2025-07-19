<?php

declare(strict_types=1);

namespace MiniFranske\FsMediaGallery\Hooks;

/*
 * This source file is proprietary property of Beech Applications B.V.
 * Date: 28-09-2016
 * All code (c) Beech Applications B.V. all rights reserved
 */

use TYPO3\CMS\Backend\Utility\BackendUtility as BackendUtilityCore;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Class PageLayoutView
 */
class PageLayoutView
{
    /**
     * @var string
     */
    final public const LLPATH = 'LLL:EXT:fs_media_gallery/Resources/Private/Language/locallang_be.xlf:';

    private array $summaryData = [];

    /**
     * @var array
     */
    private $flexFormData = [];

    public function __construct(private readonly ConnectionPool $connectionPool)
    {
    }

    public function getExtensionSummary(array $params): string
    {
        /** @var \TYPO3\CMS\Backend\View\PageLayoutView $pageLayoutView */
        $pageLayoutView = $params['pObj'];
        $row = $params['row'];
        $this->flexFormData = GeneralUtility::xml2array($row['pi_flexform']);
        $action = $this->getAction();

        $result = '<strong>' . $pageLayoutView->linkEditContent($this->getLanguageService()->sL(self::LLPATH . 'mediagallery.title'), $row) . '</strong><br>';
        $result .= $this->getDisplayMode($action);

        $result .= '<hr>';

        if (in_array($action, ['showAlbum', 'showAlbumByConfig'], true)) {
            $this->addSelectedAlbumToSettingsSummary();
        } else {
            $this->addSelectedAlbumsToSettingsSummary();
        }
        $this->addStartingPointToSettingsSummary();

        $result .= $this->renderSettingsAsTable();

        return $result;
    }

    private function getAction(): string
    {
        // if flexForm data is found
        $actions = $this->getFieldFromFlexform('switchableControllerActions');
        $action = '';

        if (!empty($actions)) {
            $actionList = GeneralUtility::trimExplode(';', $actions);

            // translate the first action into its translation
            $action = str_replace('MediaAlbum->', '', (string)$actionList[0]);
        }
        return $action;
    }

    private function getDisplayMode(string $action): string
    {
        $actionTranslationKey = match ($action) {
            'showalbum' => 'showAlbumByParam',
            default => $action,
        };
        return $this->getLanguageService()->sL(self::LLPATH . 'flexforms.mediagallery.switchableControllerActions.I.' . $actionTranslationKey);
    }

    /**
     * Get field value from flexform configuration,
     * including checks if flexform configuration is available
     *
     * @param string $key name of the key
     * @param string $sheet name of the sheet
     * @return string|null if nothing found, value if found
     */
    private function getFieldFromFlexform(string $key, string $sheet = 'general'): ?string
    {
        return $this->flexFormData['data'][$sheet]['lDEF'][$key]['vDEF'] ?? null;
    }

    private function addSelectedAlbumToSettingsSummary(): void
    {
        $albumUid = (int)$this->getFieldFromFlexform('settings.mediaAlbum');
        if ($albumUid > 0) {
            // Album record
            $rowSysFileCollectionRecords = $this->getDatabaseConnection()->select(['*'], 'sys_file_collection', [
                'uid' => $albumUid,
                'deleted' => 0,
            ])->fetchAllAssociative();

            $albums = [];
            foreach ($rowSysFileCollectionRecords as $record) {
                $albums[] = htmlspecialchars(BackendUtilityCore::getRecordTitle('sys_file_collection', $record));
            }

            $this->summaryData[] = [
                $this->getLanguageService()->sL(self::LLPATH . 'flexforms.mediagallery.mediaAlbum') .
                    '<br />',
                implode(', ', $albums),
            ];
        }
    }

    private function addSelectedAlbumsToSettingsSummary(): void
    {
        $filterMode = '';
        $albums = [];

        $albumUids = GeneralUtility::intExplode(',', $this->getFieldFromFlexform('settings.mediaAlbumsUids'), true);
        if ((is_countable($albumUids) ? count($albumUids) : 0) > 0) {
            // Filter mode
            $selectedFilerMode = $this->getFieldFromFlexform('settings.useAlbumFilterAsExclude');

            if ($selectedFilerMode !== '') {
                $filterMode = $this->getLanguageService()->sL(self::LLPATH . 'flexforms.general.I.inherit');
                $filterMode = '<span style="font-weight:normal;font-style:italic">(' . htmlspecialchars($filterMode) . ')</span>';
            }

            // Album records
            $q = $this->getDatabaseConnection()->createQueryBuilder();

            $q->getRestrictions()
                ->removeAll()
                ->add(GeneralUtility::makeInstance(DeletedRestriction::class));

            $quotedIdentifiers = $q->createNamedParameter($albumUids, Connection::PARAM_INT_ARRAY);
            $q->select('*')
                ->from('sys_file_collection')
                ->where(
                    $q->expr()->in('uid', $quotedIdentifiers)
                );

            $rowSysFileCollectionRecords = $q->executeQuery()->fetchAllAssociative();

            foreach ($rowSysFileCollectionRecords as $record) {
                $albums[] = htmlspecialchars(BackendUtilityCore::getRecordTitle('sys_file_collection', $record));
            }

            $this->summaryData[] = [
                $this->getLanguageService()->sL(self::LLPATH . 'flexforms.mediagallery.mediaAlbumsUids') .
                    '<br />' . $filterMode,
                implode(', ', $albums),
            ];
        }
    }

    private function addStartingPointToSettingsSummary(): void
    {
        $value = $this->getFieldFromFlexform('settings.startingpoint');

        if (!empty($value)) {
            $pagesOut = [];

            $q = $this->getDatabaseConnection('pages')->createQueryBuilder();
            $quotedIdentifiers = $q->createNamedParameter(GeneralUtility::intExplode(',', $value, true), Connection::PARAM_INT_ARRAY);

            $q->select('*')
                ->from('pages')
                ->where(
                    $q->expr()->in('uid', $quotedIdentifiers)
                );

            $rawPagesRecords = $q->executeQuery()->fetchAllAssociative();

            foreach ($rawPagesRecords as $page) {
                $pagesOut[] = htmlspecialchars(BackendUtilityCore::getRecordTitle(
                    'pages',
                    $page
                )) . ' (' . $page['uid'] . ')';
            }

            $recursiveLevel = (int)$this->getFieldFromFlexform('settings.recursive');
            $recursiveLevelText = '';
            if ($recursiveLevel === 250) {
                $recursiveLevelText = $this->getLanguageService()->sL('LLL:EXT:frontend/Resources/Private/Language/locallang_ttc.xlf:recursive.I.5');
            } elseif ($recursiveLevel > 0) {
                $recursiveLevelText = $this->getLanguageService()->sL('LLL:EXT:frontend/Resources/Private/Language/locallang_ttc.xlf:recursive.I.' . $recursiveLevel);
            }

            if (!empty($recursiveLevelText)) {
                $recursiveLevelText = '<br />' .
                    $this->getLanguageService()->sL('LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.recursive') . ' ' .
                    $recursiveLevelText;
            }

            $this->summaryData[] = [
                $this->getLanguageService()->sL('LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.startingpoint'),
                implode(', ', $pagesOut) . $recursiveLevelText,
            ];
        }
    }

    /**
     * Render the settings as table for Web>Page module
     *
     * System settings are displayed in mono font
     */
    private function renderSettingsAsTable(): string
    {
        if (count($this->summaryData) == 0) {
            return '';
        }

        $content = '';
        foreach ($this->summaryData as $line) {
            $content .= '<strong>' . $line[0] . '</strong>' . ' ' . $line[1] . '<br />';
        }

        return '<pre style="white-space:normal">' . $content . '</pre>';
    }

    private function getLanguageService(): LanguageService
    {
        return $GLOBALS['LANG'];
    }

    private function getDatabaseConnection(string $table = 'sys_file_collection'): Connection
    {
        return $this->connectionPool->getConnectionForTable($table);
    }
}
