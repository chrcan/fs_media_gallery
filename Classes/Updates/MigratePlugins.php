<?php

declare(strict_types=1);

/*
 * (c) 2025 rc design visual concepts (rc-design.at)
 * _________________________________________________
 * The TYPO3 project - inspiring people to share!
 * _________________________________________________
 */

namespace MiniFranske\FsMediaGallery\Updates;

use Doctrine\DBAL\Exception;
use TYPO3\CMS\Core\Configuration\FlexForm\FlexFormTools;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Install\Attribute\UpgradeWizard;
use TYPO3\CMS\Install\Updates\UpgradeWizardInterface;

#[UpgradeWizard('fsMediaGallery_migratePlugins')]
final class MigratePlugins implements UpgradeWizardInterface
{
    public function __construct(
        private readonly ConnectionPool $connectionPool,
    ) {}

    public function getTitle(): string
    {
        return 'EXT:fs_media_gallery: Migrate plugins';
    }

    public function getDescription(): string
    {
        $description = 'The old plugin (list type "fsmediagallery_mediagallery") using switchableControllerActions ';
        $description .= 'has been split into separate plugins. This update wizard migrates all existing plugin settings ';
        $description .= 'and changes the plugin to use the new plugins available. ';
        $description .= 'Count of plugins: ' . $this->getContentElementCount();
        return $description;
    }

    public function executeUpdate(): bool
    {
        $contentElements = $this->getContentElements();
        $count = count($contentElements);

        if ($count > 0) {
            foreach ($contentElements as $contentElement) {
                $flexFormArray = GeneralUtility::xml2array($contentElement['pi_flexform']);
                $pluginName = $this->getFirstControllerActionFromFlexform($flexFormArray);

                if ($pluginName) {
                    $pluginSignature = 'fsmediagallery_' . strtolower($pluginName);
                    unset($flexFormArray['data']['general']['lDEF']['switchableControllerActions']);

                    $flexFormTools = new FlexFormTools();
                    $flexFormString = $flexFormTools->flexArray2Xml($flexFormArray, addPrologue: true);

                    if (!$this->updateContentElement($contentElement['uid'], $pluginSignature, $flexFormString)) {
                        return false;
                    }

                } else {
                    return false;
                }
            }
        }

        return true;
    }

    public function updateNecessary(): bool
    {
        $count = $this->getContentElementCount();
        return $count > 0;
    }

    public function getPrerequisites(): array
    {
        return [];
    }

    private function getContentElementCount(): int
    {
        try {
            $queryBuilder = $this->connectionPool->getQueryBuilderForTable('tt_content');
            $queryBuilder->getRestrictions()->removeAll()->add(GeneralUtility::makeInstance(DeletedRestriction::class));
            return $queryBuilder
                ->count('uid')
                ->from('tt_content')
                ->where(
                    $queryBuilder->expr()->eq('CType', $queryBuilder->createNamedParameter('list')),
                    $queryBuilder->expr()->eq('list_type', $queryBuilder->createNamedParameter('fsmediagallery_mediagallery'))
                )
                ->executeQuery()
                ->fetchOne();
        } catch (Exception) {
        }
        return 0;
    }

    private function getContentElements(): array
    {
        try {
            $queryBuilder = $this->connectionPool->getQueryBuilderForTable('tt_content');
            $queryBuilder->getRestrictions()->removeAll()->add(GeneralUtility::makeInstance(DeletedRestriction::class));
            return $queryBuilder
                ->select('uid', 'pi_flexform')
                ->from('tt_content')
                ->where(
                    $queryBuilder->expr()->eq('CType', $queryBuilder->createNamedParameter('list')),
                    $queryBuilder->expr()->eq('list_type', $queryBuilder->createNamedParameter('fsmediagallery_mediagallery'))
                )
                ->executeQuery()
                ->fetchAllAssociative();
        } catch (Exception) {
        }
        return [];
    }

    private function getFirstControllerActionFromFlexform(array $flexFormArray): string
    {
        $controllerActions = explode(';', $flexFormArray['data']['general']['lDEF']['switchableControllerActions']['vDEF'] ?? '');
        if (is_array($controllerActions)) {
            [, $action] = explode('->', $controllerActions[0]);

            return $action;
        }
        return '';
    }

    private function updateContentElement(int $uid, string $listType, string $piFlexform): bool
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('tt_content');
        $count = $queryBuilder
            ->update('tt_content')
            ->where(
                $queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($uid, Connection::PARAM_INT))
            )
            ->set('list_type', $listType)
            ->set('pi_flexform', $piFlexform)
            ->executeStatement();

        return $count > 0;
    }
}
