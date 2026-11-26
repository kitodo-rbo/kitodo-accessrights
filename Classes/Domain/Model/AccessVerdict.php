<?php
declare(strict_types=1);

namespace Kitodo\Dlf\Pdp\Domain\Model;

/**
 * Outcome of a policy decision.
 *
 * NOTE: a permit may carry the JWT for the image server. Treat the verdict like a credential:
 * never log it, never var_dump it into a response.
 */
final readonly class AccessVerdict
{
    /** @param array<int, array<int, array{type: string, values: string[]}>> $hints alternatives, each a list of unmet requirements (all required together) */
    private function __construct(public Decision $decision, public array $hints = [], public ?string $token = null) {}

    /** @param ?string $token JWT for the image server; null when the object is unrestricted and no token is needed */
    public static function permit(?string $token = null): self { return new self(Decision::Permit, [], $token); }
    public static function loginRequired(array $hints): self { return new self(Decision::LoginRequired, $hints); }
    public static function elevationRequired(array $hints): self { return new self(Decision::ElevationRequired, $hints); }
    public static function denied(): self { return new self(Decision::Denied); }

    public function isPermitted(): bool { return $this->decision === Decision::Permit; }
}
