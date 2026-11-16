<?php
declare(strict_types=1);

namespace Kitodo\Dlf\Pdp\Service;

use Kitodo\Dlf\Pdp\Domain\Model\AccessSubject;
use Kitodo\Dlf\Pdp\Domain\Model\AccessVerdict;
use Kitodo\Dlf\Pdp\Domain\Model\Restriction;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;

final class PolicyDecisionService implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    /**
     * @param string $restrictedPathTemplate printf template of the image-server directory that holds the restricted
     *        files of one document; %d is replaced by the document UID. Must match the image server's directory layout.
     */
    public function __construct(
        private readonly LibRmlParser $parser,
        private readonly TokenService $tokenService,
        private readonly string $restrictedPathTemplate = '/files/%d/'
    ) {}

    /**
     * Permits only if EVERY requested action is granted. On permit, a JWT is attached if (and only if) the object
     * is restricted and the subject may read it. Flags in the token are the per-action results.
     */
    public function decide(string $librml, AccessSubject $subject, array $actions, int $documentId): AccessVerdict
    {
        /** @var array<string, ?array{granted: bool, hints: array}> $evaluated each action is parsed/evaluated once */
        $evaluated = [];
        $evaluate = function (string $action) use (&$evaluated, $librml, $subject): ?array {
            if (!array_key_exists($action, $evaluated)) {
                $evaluated[$action] = $this->evaluate($librml, $action, $subject);
            }
            return $evaluated[$action];
        };

        try {
            foreach ($actions as $action) {
                $result = $evaluate($action);
                if ($result === null) {
                    return AccessVerdict::permit(); // assumption 5: no policy described -> open, no token needed
                }
                if (!$result['granted']) {
                    if ($result['hints'] === []) {
                        return AccessVerdict::denied(); // action absent, permission="false", or nothing the user could still satisfy
                    }
                    return $subject->authenticated
                        ? AccessVerdict::elevationRequired($result['hints'])
                        : AccessVerdict::loginRequired($result['hints']);
                }
            }

            // Everything requested is granted. Restricted images can only be fetched with a token, and a token
            // means "may read". No read right (e.g. only 'print' was asked for) -> no token.
            $read = $evaluate('read');
            if ($read === null || !$read['granted']) {
                return AccessVerdict::permit();
            }

            $flags = [
                'view'     => true,
                'print'    => $this->isGranted($evaluate('print')),
                'download' => $this->isGranted($evaluate('download')),
            ];
        } catch (InvalidRightsException) {
            return AccessVerdict::denied(); // fail closed
        }

        $token = $this->tokenService->issue(
            $documentId,
            $subject->uid,
            $this->buildAllowedPaths($documentId),
            $flags
        );

        $this->logger?->debug('PDP: issued JWT for user uid: ' . $subject->uid . ' on document ' . $documentId);

        return AccessVerdict::permit($token);
    }

    /**
     * @return array{granted: bool, hints: array}|null null = the LibRML describes no policy at all
     * @throws InvalidRightsException
     */
    private function evaluate(string $librml, string $action, AccessSubject $subject): ?array
    {
        $ruleTypes = $this->parser->rulesFor($librml, [$action]);
        if ($ruleTypes === null) {
            return null;
        }

        $hints = [];
        foreach ($ruleTypes as $ruleType) {
            foreach ($ruleType as $rule) {
                if (!$rule->permission) {
                    continue; // an explicit permission="false" must never grant, whatever isSatisfiedBy() says
                }
                if ($rule->isSatisfiedBy($subject)) {
                    return ['granted' => true, 'hints' => []];
                }
                $unmet = $rule->unmetRestrictions($subject);
                if ($unmet !== []) {
                    $hints[] = array_map(static fn (Restriction $r) => $r->toHint(), $unmet);
                }
            }
        }
        return ['granted' => false, 'hints' => $hints];
    }

    /** @param array{granted: bool, hints: array}|null $result null (no policy) counts as granted */
    private function isGranted(?array $result): bool
    {
        return $result === null || $result['granted'];
    }

    /** @return string[] */
    private function buildAllowedPaths(int $documentUid): array
    {
        $t = $this->restrictedPathTemplate;
        if (substr_count($t, '%') !== 1 || substr_count($t, '%d') !== 1 || !str_starts_with($t, '/') || !str_ends_with($t, '/')) {
            throw new \LogicException('restrictedPathTemplate must start and end with "/" and contain %d exactly once: ' . $t, 1731600001);
        }
        return [sprintf($t, $documentUid)];
    }
}
