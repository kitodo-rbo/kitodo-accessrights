<?php

declare(strict_types=1);

namespace Kitodo\Dlf\Pdp\EventListener;

use Kitodo\Dlf\Event\LibRmlDetectedEvent;
use Kitodo\Dlf\Pdp\Service\PdpService;
use TYPO3\CMS\Core\Attribute\AsEventListener;

#[AsEventListener]
final class LibRmlDetectedListener
{
    public function __construct(
        private readonly PdpService $pdpService,
    ) {
    }

    public function __invoke(LibRmlDetectedEvent $event): void
    {
        $this->pdpService->processLibRml(
            $event->getMetadataId(),
            $event->getLibRmlXml(),
        );
    }
}