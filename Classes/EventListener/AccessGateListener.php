<?php
declare(strict_types=1);

namespace Kitodo\Dlf\Pdp\EventListener;

use Kitodo\Dlf\Pdp\Domain\Model\AccessSubject;
use Kitodo\Dlf\Pdp\Domain\Model\AccessVerdict;
use Kitodo\Dlf\Pdp\Domain\Repository\AccessRightsReader;
use Kitodo\Dlf\Pdp\Service\BrowserTokenInjector;
use Kitodo\Dlf\Pdp\Service\PolicyDecisionService;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Attribute\AsEventListener;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Http\HtmlResponse;
use TYPO3\CMS\Core\Http\RedirectResponse;
use TYPO3\CMS\Core\Utility\MathUtility;
use TYPO3\CMS\Core\View\ViewFactoryInterface;
use TYPO3\CMS\Frontend\Cache\CacheInstruction;
use TYPO3\CMS\Frontend\Event\AfterPageAndLanguageIsResolvedEvent;

#[AsEventListener(identifier: 'dlf-pdp/access-gate')]
final class AccessGateListener
{
    public function __construct(
        private readonly ConnectionPool $connectionPool,
        private readonly PolicyDecisionService $policyDecisionService,
        private readonly ViewFactoryInterface $viewFactory,
        private readonly AccessRightsReader $rightsReader,
        private readonly BrowserTokenInjector $tokenInjector
    ) {}

    public function __invoke(AfterPageAndLanguageIsResolvedEvent $event): void
    {
        $request = $event->getRequest();
        $site = $request->getAttribute('site');
        $deniedPid = (int)$site->getSettings()->get('dlfPdp.deniedPid', 0);

        // Loop guard: never gate the info page itself
        if ($deniedPid !== 0 && $event->getPageInformation()->getId() === $deniedPid) {
            return;
        }

        $noStore = ['Cache-Control' => 'private, no-store'];

        $params = $request->getQueryParams()['tx_dlf'] ?? null;
        if (!is_array($params)) {
            return; // not a viewer request (this also covers a malformed ?tx_dlf=abc)
        }
        $hasId = array_key_exists('id', $params) && $params['id'] !== '';
        if (!$hasId && empty($params['recordId'])) {
            return; // no document addressed at all (search, calendar, ...)
        }

        // Kitodo loads a document by UID, by URL, OR by recordId. The decision "documents are addressed by UID only"
        // is enforced here: anything that is not a positive integer UID cannot be mapped to rights -> fail closed.
        // (A cast like (int)'https://...' would give 0, find "no rights" and let the request through.)
        $id = $params['id'] ?? '';
        if (!is_string($id) || !MathUtility::canBeInterpretedAsInteger($id) || (int)$id <= 0) {
            $event->setResponse(new HtmlResponse('Access denied', 403, $noStore));
            return;
        }
        $documentId = (int)$id;

        $rights = $this->rightsReader->findRightsByDocumentUid($documentId);
        if ($rights === null) {
            return; // not a viewer request, or the object has no restrictions
        }
        $subject = AccessSubject::fromFeUserRow($request->getAttribute('frontend.user')?->user ?: null);

        $verdict = $this->policyDecisionService->decide($rights, $subject, ['read'], $documentId);
        if ($verdict->isPermitted()) {
            $this->handOverToken($request, $verdict);
            return; // normal rendering continues
        }

        // Fail closed: a missing setting must never grant access
        if ($deniedPid === 0) {
            $event->setResponse(new HtmlResponse('Access denied', 403, $noStore));
            return;
        }

        $uri = $request->getUri();
        $event->setResponse(new RedirectResponse(
            (string)$site->getRouter()->generateUri($deniedPid, [
                'tx_dlfpdp_gate' => [
                    'documentId' => $documentId,
                    // path + query only, no host
                    'returnUrl' => $uri->getPath() . ($uri->getQuery() !== '' ? '?' . $uri->getQuery() : ''),
                ],
            ]),
            303,
            $noStore
        ));
    }

    /**
     * Gives the browser its token, or removes a stale one.
     */
    private function handOverToken(ServerRequestInterface $request, AccessVerdict $verdict): void
    {
        if ($verdict->token === null) {
            $this->tokenInjector->clear(); // unrestricted object: drop a token left by a previously viewed document
            return;
        }

        // The token is per user. A page containing it must never be cached and served to somebody else.
        // If we cannot switch caching off, we must not hand out the token at all (fail closed).
        $cacheInstruction = $request->getAttribute('frontend.cache.instruction');
        if (!$cacheInstruction instanceof CacheInstruction) {
            throw new \RuntimeException('dlf_pdp: frontend.cache.instruction missing, refusing to embed a per-user token.', 1731600002);
        }
        $cacheInstruction->disableCache('dlf_pdp: page contains a per-user access token');

        $this->tokenInjector->provide($verdict->token);
    }
}
