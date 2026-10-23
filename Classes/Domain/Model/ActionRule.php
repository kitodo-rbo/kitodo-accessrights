<?php
declare(strict_types=1);

namespace Kitodo\Dlf\Pdp\Domain\Model;

final readonly class ActionRule
{
    /** @param Restriction[] $restrictions */
    public function __construct(public bool $permission, public array $restrictions) {}

    public function isUnrestricted(): bool
    {
        return $this->permission && $this->restrictions === [];
    }

    public function isSatisfiedBy(AccessSubject $subject): bool
{
    if (!$this->permission) {
        return false;
    }
    foreach ($this->restrictions as $restriction) {
        if (!$restriction->isSatisfiedBy($subject)) {
            return false; // AND: one unmet restriction is enough to fail
        }
    }
    return true; // also true for "no restrictions" = public
}

    /** @return Restriction[] */
    public function unmetRestrictions(AccessSubject $subject): array
    {
        return array_values(array_filter(
            $this->restrictions,
            static fn (Restriction $r) => !$r->isSatisfiedBy($subject),
        ));
    }
}