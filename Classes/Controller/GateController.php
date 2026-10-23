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
        private readonly AccessRightsReader $rights,
        private readonly PolicyDecisionService $pdp,
    ) {}

    public function showAction(int $document, string $returnUrl = ''): ResponseInterface
{
    $librml = $this->rights->findRightsByDocumentUid($document);
    $subject = AccessSubject::fromFeUserRow($this->request->getAttribute('frontend.user')?->user ?: null);
    $state = $librml === null
        ? 'denied' // the gate was called for a document that doesn't exist
        : $this->pdp->decide($librml, $subject, 'read')->decision->value;
    $hints = $librml === null ? [] : $this->pdp->decide($librml, $subject, 'read')->hints;

    $site = $this->request->getAttribute('site');

    $this->view->assignMultiple([
        'state' => $state,   // permit | login_required | elevation_required | denied
        'hints' => $hints,
        'returnUrl' => GeneralUtility::sanitizeLocalUrl($returnUrl),
        'loginPageUid' => (int)($site->getSettings()->get('dlfPdp.loginPageUid') ?? 0),
        'currentLocation' => $subject->location,
    ]);
    return $this->htmlResponse();
}
}