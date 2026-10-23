<?php
declare(strict_types=1);

namespace Kitodo\Dlf\Pdp\Middleware;

use Kitodo\Dlf\Pdp\Domain\Model\AccessSubject;
use Kitodo\Dlf\Pdp\Domain\Repository\AccessRightsReader;
use Kitodo\Dlf\Pdp\Service\PolicyDecisionService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Http\RedirectResponse;

final class AccessEnforcementMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly AccessRightsReader $rights,
        private readonly PolicyDecisionService $pdp,
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $documentId = $request->getQueryParams()['tx_dlf']['id'] ?? null;
        // DEMO ONLY: non-numeric ids (METS URLs) bypass the check. Must be resolved or denied before production.
        if ($documentId === null || !ctype_digit((string)$documentId)) {
            return $handler->handle($request);
        }

        $site = $request->getAttribute('site');
        $gatePid = (int)($site->getSettings()->get('dlfPdp.gatePageUid') ?? 0);
        if ($gatePid === 0 || $request->getAttribute('routing')->getPageId() === $gatePid) {
            return $handler->handle($request); // no gate configured, or we are already on it (avoid a loop)
        }

        $librml = $this->rights->findRightsByDocumentUid((int)$documentId);
        if ($librml === null) {
            return $handler->handle($request); // unknown or hidden document: nothing to protect here
        }
        $subject = AccessSubject::fromFeUserRow($request->getAttribute('frontend.user')?->user ?: null);
        $verdict = $this->pdp->decide($librml, $subject, 'read');

        if ($verdict->isPermitted()) {
            return $handler->handle($request);
        }

        $gateUri = $site->getRouter()->generateUri($gatePid, [
            'tx_dlfpdp_gate' => ['document' => (int)$documentId, 'returnUrl' => (string)$request->getUri()],
        ]);
        return new RedirectResponse($gateUri, 303);
    }
}