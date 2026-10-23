<?php
declare(strict_types=1);

namespace Kitodo\Dlf\Pdp\Domain\Model;

final readonly class AccessVerdict
{
    /** @param array<int, array<int, array{type: string, values: string[]}>> $hints alternatives, each a list of unmet requirements (all required together) */
    private function __construct(public Decision $decision, public array $hints = []) {}

    public static function permit(): self { return new self(Decision::Permit); }
    public static function loginRequired(array $hints): self { return new self(Decision::LoginRequired, $hints); }
    public static function elevationRequired(array $hints): self { return new self(Decision::ElevationRequired, $hints); }
    public static function denied(): self { return new self(Decision::Denied); }

    public function isPermitted(): bool { return $this->decision === Decision::Permit; }
}