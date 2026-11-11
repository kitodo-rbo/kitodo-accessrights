<?php
declare(strict_types=1);

namespace Kitodo\Dlf\Pdp\EventListener;

use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Attribute\AsEventListener;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Http\HtmlResponse;
use TYPO3\CMS\Core\Http\RedirectResponse;
use TYPO3\CMS\Core\View\ViewFactoryData;
use TYPO3\CMS\Core\View\ViewFactoryInterface;
use TYPO3\CMS\Frontend\Event\AfterPageAndLanguageIsResolvedEvent;
use Kitodo\Dlf\Pdp\Service\PolicyDecisionService;
use Kitodo\Dlf\Pdp\Domain\Model\AccessSubject;
use Kitodo\Dlf\Pdp\Domain\Repository\AccessRightsReader;

#[AsEventListener(identifier: 'dlf-pdp/access-gate')]
final class AccessGateListener
{
    public function __construct(
        private readonly ConnectionPool $connectionPool,
        private readonly PolicyDecisionService $policyDecisionService,
        private readonly ViewFactoryInterface $viewFactory,
        private readonly AccessRightsReader $rightsReader,
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

        if(array_key_exists('tx_dlf', $request->getQueryParams())) {
            $documentId = (int)$request->getQueryParams()['tx_dlf']['id'] ?? null;
        }
        else {
            return;
        }

        $rights = $this->rightsReader->findRightsByDocumentUid($documentId);
        if ($rights === null) {
            return; // not a viewer request, or the object has no restrictions
        }
        $subject = AccessSubject::fromFeUserRow($request->getAttribute('frontend.user')?->user ?: null);

        $verdict = $this->policyDecisionService->decide($rights, $subject, 'read');
        if ($verdict->isPermitted()) {
            return; // do nothing: normal rendering continues
        }

        $noStore = ['Cache-Control' => 'private, no-store'];

        // Fail closed: a missing setting must never grant access
        if ($deniedPid === 0) {
            $event->setResponse(new HtmlResponse('Access denied', 403, $noStore));
            return;
        }

        $uri = $request->getUri();
        $event->setResponse(new RedirectResponse(
            (string)$site->getRouter()->generateUri($deniedPid, [
                'tx_dlfpdp_gate' => [
                    'document' => $documentId,
                    // path + query only, no host
                    'returnUrl' => $uri->getPath() . ($uri->getQuery() !== '' ? '?' . $uri->getQuery() : ''),
                ],
            ]),
            303,
            $noStore
        ));
    }
}
