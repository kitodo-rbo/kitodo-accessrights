<?php
declare(strict_types=1);

namespace Kitodo\Dlf\Pdp\Domain\Model;

final readonly class Restriction
{
    /** @param string[] $values */
    public function __construct(public string $type, public array $values) {}

    public function isSatisfiedBy(AccessSubject $subject): bool
    {
        if (!$subject->authenticated) {
            return false;
        }
        return match ($this->type) {
            'group' => array_intersect($this->values, $subject->groups()) !== [],
            'location' => $subject->location !== null && in_array($subject->location, $this->values, true), // ASSUMPTION, see above
            default => false, // unknown type: fail closed
        };
    }

    /** @return array{type: string, values: string[]} */
    public function toHint(): array
    {
        return ['type' => $this->type, 'values' => $this->values];
    }
}