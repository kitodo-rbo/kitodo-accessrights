<?php
declare(strict_types=1);

namespace Kitodo\Dlf\Pdp\Domain\Repository;

use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;

/**
 * Reads the LibRML string of a document without Extbase, so it is usable
 * early in the frontend middleware stack (no TypoScript available there).
 */
final class AccessRightsReader
{
    public function __construct(private readonly ConnectionPool $connectionPool) {}

    /**
     * @return string|null null = document not found (or hidden/deleted), '' = found, no rights described
     */
    public function findRightsByDocumentUid(int $uid): ?string
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('tx_dlf_documents');
        $value = $queryBuilder
            ->select('accessrights')
            ->from('tx_dlf_documents')
            ->where($queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($uid, Connection::PARAM_INT)))
            ->setMaxResults(1)
            ->executeQuery()
            ->fetchOne();

        return $value === false ? null : (string)$value;
    }
}