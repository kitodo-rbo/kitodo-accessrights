<?php
declare(strict_types=1);

namespace Kitodo\Dlf\Pdp\Service;

use Kitodo\Dlf\Pdp\Domain\Model\AccessSubject;
use Kitodo\Dlf\Pdp\Domain\Model\AccessVerdict;
use Kitodo\Dlf\Pdp\Domain\Model\Restriction;

final class PolicyDecisionService
{
    public function __construct(private readonly LibRmlParser $parser) {}

    public function decide(string $librml, AccessSubject $subject, string $action = 'read'): AccessVerdict
    {
        try {
            $rules = $this->parser->rulesFor($librml, $action);
        } catch (InvalidRightsException) {
            return AccessVerdict::denied(); // fail closed
        }

        if ($rules === null) {
            return AccessVerdict::permit(); // assumption 5: no policy described
        }

        foreach ($rules as $rule) {
            if ($rule->isSatisfiedBy($subject)) {
                return AccessVerdict::permit();
            }
        }
        $alternatives = [];
        foreach ($rules as $rule) {
            if (!$rule->permission) {
                continue;
            }
            $unmet = $rule->unmetRestrictions($subject);
            if ($unmet !== []) {
                $alternatives[] = array_map(static fn (Restriction $r) => $r->toHint(), $unmet);
            }
        }

        if ($alternatives === []) {
            return AccessVerdict::denied(); // action absent, permission="false", or nothing the user could still satisfy
        }

    return $subject->authenticated
        ? AccessVerdict::elevationRequired($alternatives)
        : AccessVerdict::loginRequired($alternatives);
    }
}