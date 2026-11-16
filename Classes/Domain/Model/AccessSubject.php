<?php
declare(strict_types=1);

namespace Kitodo\Dlf\Pdp\Domain\Model;

final readonly class AccessSubject
{
    public const COLUMN_AFFILIATION = 'tx_dlf_pdp_affiliation';
    public const COLUMN_LOCATION = 'tx_dlf_pdp_location';

    /** @param string[] $affiliations */
    public function __construct(
        public bool $authenticated,
        public int $uid,
        public array $affiliations = [],
        public ?string $location = null,
    ) {}

    public static function anonymous(): self
    {
        return new self(false, -1);
    }

    /** @param array<string,mixed>|null $feUserRow */
    public static function fromFeUserRow(?array $feUserRow): self
    {
        if (!$feUserRow || empty($feUserRow['uid'])) {
            return self::anonymous();
        }
        $uid = $feUserRow['uid'];
        $affiliations = preg_split('/[,\s]+/', strtolower((string)($feUserRow[self::COLUMN_AFFILIATION] ?? '')), -1, PREG_SPLIT_NO_EMPTY);
        $location = strtolower(trim((string)($feUserRow[self::COLUMN_LOCATION] ?? '')));
        return new self(true, $uid, $affiliations, $location !== '' ? $location : null);
    }

    /** Assumption 1 + 2: every logged-in user is "registered", affiliations count as groups. */
    public function groups(): array
    {
        return $this->authenticated ? array_merge(['registered'], $this->affiliations) : [];
    }
}