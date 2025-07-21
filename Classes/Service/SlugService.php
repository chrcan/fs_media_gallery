<?php

declare(strict_types=1);

namespace MiniFranske\FsMediaGallery\Service;

use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\DataHandling\SlugHelper;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Class SlugService
 */
class SlugService
{
    protected string $tableName = 'sys_file_collection';
    protected string $slugFieldName = 'slug';
    public function __construct(
        private readonly ConnectionPool $connectionPool,
        private readonly Context $context
    ) {}

    /**
     * @return bool
     */
    public function typo3SupportsSlugs(): bool
    {
        return class_exists(SlugHelper::class);
    }

    public function countOfSlugUpdates(): int
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable($this->tableName);
        $queryBuilder->getRestrictions()->removeAll();
        return $queryBuilder->count('uid')
            ->from($this->tableName)
            ->where(
                $queryBuilder->expr()->or(
                    $queryBuilder->expr()->eq(
                        $this->slugFieldName,
                        $queryBuilder->createNamedParameter('')
                    ),
                    $queryBuilder->expr()->isNull($this->slugFieldName)
                )
            )
            ->executeQuery()->fetchOne();
    }

    public function performUpdateSlugs(): array
    {
        $databaseQueries = [];

        $connection = $this->connectionPool->getConnectionForTable($this->tableName);
        $queryBuilder = $connection->createQueryBuilder();
        $queryBuilder->getRestrictions()->removeAll();
        $fieldConfig = $GLOBALS['TCA'][$this->tableName]['columns'][$this->slugFieldName]['config'];
        /** @var SlugHelper $slugHelper */
        $slugHelper = GeneralUtility::makeInstance(
            SlugHelper::class,
            $this->tableName,
            $this->slugFieldName,
            $fieldConfig
        );

        $statement = $queryBuilder->select('*')
            ->from($this->tableName)
            ->where(
                $queryBuilder->expr()->or(
                    $queryBuilder->expr()->eq(
                        $this->slugFieldName,
                        $queryBuilder->createNamedParameter('')
                    ),
                    $queryBuilder->expr()->isNull($this->slugFieldName)
                )
            )
            ->executeQuery();

        while ($record = $statement->fetchAssociative()) {
            //Use the core slughelper which is also used in the BE form
            $slug = $slugHelper->generate($record, $record['pid']);
            $queryBuilder = $connection->createQueryBuilder();
            $queryBuilder->update($this->tableName)
                ->where(
                    $queryBuilder->expr()->eq(
                        'uid',
                        $queryBuilder->createNamedParameter($record['uid'], Connection::PARAM_INT)
                    )
                )
                ->set($this->slugFieldName, $slug);
            $databaseQueries[] = $queryBuilder->getSQL();
            $queryBuilder->executeQuery();
        }

        return $databaseQueries;
    }

    /**
     * Count valid entries from EXT:realurl table tx_realurl_uniqalias which can be migrated
     * Checks also for existance of third party extension table 'tx_realurl_uniqalias'
     * EXT:realurl requires not to be installed
     *
     * @return int
     */
    public function countOfRealurlAliasMigrations(): int
    {
        $elementCount = 0;
        // Check if table 'tx_realurl_uniqalias' exists
        $queryBuilder = $this->connectionPool
            ->getQueryBuilderForTable('tx_realurl_uniqalias');
        $schemaManager = $queryBuilder->getConnection()->getSchemaManager();
        if ($schemaManager->tablesExist(['tx_realurl_uniqalias']) === true) {
            // Count valid aliases
            $queryBuilder->getRestrictions()->removeAll();
            $elementCount = $queryBuilder->selectLiteral('COUNT(DISTINCT ' . $this->tableName . '.uid)')
                ->from('tx_realurl_uniqalias')
                ->join(
                    'tx_realurl_uniqalias',
                    $this->tableName,
                    $this->tableName,
                    $queryBuilder->expr()->eq(
                        'tx_realurl_uniqalias.value_id',
                        $queryBuilder->quoteIdentifier($this->tableName . '.uid')
                    )
                )
                ->where(
                    $queryBuilder->expr()->and(
                        $queryBuilder->expr()->or(
                            $queryBuilder->expr()->eq(
                                $this->tableName . '.' . $this->slugFieldName,
                                $queryBuilder->createNamedParameter('')
                            ),
                            $queryBuilder->expr()->isNull($this->tableName . '.' . $this->slugFieldName)
                        ),
                        $queryBuilder->expr()->eq(
                            $this->tableName . '.sys_language_uid',
                            'tx_realurl_uniqalias.lang'
                        ),
                        $queryBuilder->expr()->eq(
                            'tx_realurl_uniqalias.tablename',
                            $queryBuilder->createNamedParameter($this->tableName)
                        ),
                        $queryBuilder->expr()->or(
                            $queryBuilder->expr()->eq(
                                'tx_realurl_uniqalias.expire',
                                $queryBuilder->createNamedParameter(0, Connection::PARAM_INT)
                            ),
                            $queryBuilder->expr()->gte(
                                'tx_realurl_uniqalias.expire',
                                $queryBuilder->createNamedParameter($this->context->getPropertyFromAspect('date', 'timestamp'), Connection::PARAM_INT)
                            )
                        )
                    )
                )
                ->executeQuery()->fetchOne();
        }
        return $elementCount;
    }

    /**
     * Perform migration of EXT:realurl unique alias into fs_media_gallery slugs
     *
     * @return array
     */
    public function performRealurlAliasMigration(): array
    {
        $databaseQueries = [];

        // Check if table 'tx_realurl_uniqalias' exists
        $queryBuilderForRealurl = $this->connectionPool
            ->getQueryBuilderForTable('tx_realurl_uniqalias');
        $schemaManager = $queryBuilderForRealurl->getConnection()->getSchemaManager();
        if ($schemaManager->tablesExist(['tx_realurl_uniqalias']) === true) {
            $connection = $this->connectionPool
                ->getConnectionForTable($this->tableName);
            $queryBuilder = $connection->createQueryBuilder();

            // Get entries to update
            $statement = $queryBuilder
                ->selectLiteral(
                    'DISTINCT ' . $this->tableName . '.uid, tx_realurl_uniqalias.value_alias, ' . $this->tableName . '.uid'
                )
                ->from($this->tableName)
                ->join(
                    $this->tableName,
                    'tx_realurl_uniqalias',
                    'tx_realurl_uniqalias',
                    $queryBuilder->expr()->eq(
                        $this->tableName . '.uid',
                        $queryBuilder->quoteIdentifier('tx_realurl_uniqalias.value_id')
                    )
                )
                ->where(
                    $queryBuilder->expr()->and(
                        $queryBuilder->expr()->or(
                            $queryBuilder->expr()->eq(
                                $this->tableName . '.' . $this->slugFieldName,
                                $queryBuilder->createNamedParameter('')
                            ),
                            $queryBuilder->expr()->isNull($this->tableName . '.' . $this->slugFieldName)
                        ),
                        $queryBuilder->expr()->eq(
                            $this->tableName . '.sys_language_uid',
                            'tx_realurl_uniqalias.lang'
                        ),
                        $queryBuilder->expr()->eq(
                            'tx_realurl_uniqalias.tablename',
                            $queryBuilder->createNamedParameter($this->tableName)
                        ),
                        $queryBuilder->expr()->or(
                            $queryBuilder->expr()->eq(
                                'tx_realurl_uniqalias.expire',
                                $queryBuilder->createNamedParameter(0, Connection::PARAM_INT)
                            ),
                            $queryBuilder->expr()->gte(
                                'tx_realurl_uniqalias.expire',
                                $queryBuilder->createNamedParameter($this->context->getPropertyFromAspect('date', 'timestamp'), Connection::PARAM_INT)
                            )
                        )
                    )
                )
                ->executeQuery();

            // Update entries
            while ($record = $statement->fetchAssociative()) {
                $slug = (string)$record['value_alias'];
                $queryBuilder = $connection->createQueryBuilder();
                $queryBuilder->update($this->tableName)
                    ->where(
                        $queryBuilder->expr()->eq(
                            'uid',
                            $queryBuilder->createNamedParameter($record['uid'], Connection::PARAM_INT)
                        )
                    )
                    ->set($this->slugFieldName, $slug);
                $databaseQueries[] = $queryBuilder->getSQL();
                $queryBuilder->executeQuery();
            }
        }

        return $databaseQueries;
    }
}
