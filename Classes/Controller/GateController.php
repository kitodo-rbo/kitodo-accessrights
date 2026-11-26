<?php
declare(strict_types=1);

namespace Kitodo\Dlf\Pdp\Controller;

use Kitodo\Dlf\Pdp\Domain\Model\AccessSubject;
use Kitodo\Dlf\Pdp\Domain\Repository\AccessRightsReader;
use Kitodo\Dlf\Pdp\Service\PolicyDecisionService;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Core\Utility\GeneralUtility;

final class GateController extends ActionController
{
    public function __construct(
        private readonly AccessRightsReader $rightsReader,
        private readonly PolicyDecisionService $policyDecisionService,
    ) {}
    
    public function deniedAction(int $documentId = 0, string $returnUrl = ''): ResponseInterface
    {
        // Never trust the URL: decide again, exactly like the gate did
        $rights = $this->rightsReader->findRightsByDocumentUid($documentId);
        $subject = AccessSubject::fromFeUserRow($this->request->getAttribute('frontend.user')?->user ?: null);
        $verdict = $rights === null ? null : $this->policyDecisionService->decide($rights, $subject, ['read'], $documentId);

        // Comes from the query string: local URLs only
        $returnUrl = GeneralUtility::sanitizeLocalUrl($returnUrl);

        // Allowed after all (e.g. just logged in): straight back to the object
        if (($verdict === null || $verdict->isPermitted()) && $returnUrl !== '') {
            return $this->redirectToUri($returnUrl);
        }

        $this->view->assignMultiple([
            'verdict' => $verdict?->decision,
            'hints' => $verdict?->hints ?? [],
            'returnUrl' => $returnUrl,
            'loginPageUid' => (int)$this->request->getAttribute('site')->getSettings()->get('dlfPdp.loginPid', 0),
        ]);

        return $this->htmlResponse();
    }
}