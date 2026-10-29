<?php
declare(strict_types=1);

namespace Kitodo\Dlf\Pdp\EventListener;

use Causal\Oidc\Event\AuthenticationGetUserGroupsEvent;
use TYPO3\CMS\Core\Attribute\AsEventListener;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Restriction\EndTimeRestriction;
use TYPO3\CMS\Core\Database\Query\Restriction\HiddenRestriction;
use TYPO3\CMS\Core\Database\Query\Restriction\StartTimeRestriction;

#[AsEventListener(identifier: 'dlf-pdp/affiliation-groups')]
final class AffiliationGroupListener
{
    /** The OIDC claim you map to fe_users.tx_dlf_pdp_affiliation. Check it in the debugger. */
    private const AFFILIATION_CLAIM = 'affiliation';

    public function __construct(
        private readonly ConnectionPool $connectionPool,
    ) {}

    public function __invoke(AuthenticationGetUserGroupsEvent $event): void
    {
        if ($event->getGroupTable() !== 'fe_groups') {
            return; // OIDC can also authenticate backend users
        }

        $claim = $event->getResource()[self::AFFILIATION_CLAIM] ?? '';
        $userAffiliations = self::splitList(is_array($claim) ? implode(';', $claim) : (string)$claim);

        $managedUids = [];
        $matchingUids = [];
        foreach ($this->findManagedGroups() as $row) {
            $uid = (int)$row['uid'];
            $managedUids[] = $uid;
            $accepted = self::splitList((string)$row['tx_dlf_pdp_affiliation']);
            if (array_intersect($accepted, $userAffiliations) !== []) {
                $matchingUids[] = $uid;
            }
        }

        // 1. Revoke: remove every affiliation-controlled group, including stale ones from earlier logins.
        $groups = array_diff(array_map('intval', $event->getUserGroups()), $managedUids);
        // 2. Grant: add the groups that match the affiliation delivered right now.
        $event->setUserGroups(array_values(array_unique(array_merge($groups, $matchingUids))));
    }

    /** @return list<array{uid: int|string, tx_dlf_pdp_affiliation: string}> */
    private function findManagedGroups(): array
    {
        $qb = $this->connectionPool->getQueryBuilderForTable('fe_groups');
        // A hidden or time-limited group is still "managed". Otherwise it could never be revoked.
        $qb->getRestrictions()
            ->removeByType(HiddenRestriction::class)
            ->removeByType(StartTimeRestriction::class)
            ->removeByType(EndTimeRestriction::class);

        return $qb->select('uid', 'tx_dlf_pdp_affiliation')
            ->from('fe_groups')
            ->where($qb->expr()->neq('tx_dlf_pdp_affiliation', $qb->createNamedParameter('')))
            ->executeQuery()
            ->fetchAllAssociative();
    }
    
    /**
    * Turns "Staff; member ,Student" into ['staff', 'member', 'student'].
    *
    * @return string[] normalised, non-empty values
    */
    private static function splitList(string $value): array
    {
        $parts = preg_split('/[;,]/', $value) ?: [];
        $parts = array_map(static fn(string $v): string => mb_strtolower(trim($v)), $parts);

        return array_values(array_filter($parts, static fn(string $v): bool => $v !== ''));
    }
}