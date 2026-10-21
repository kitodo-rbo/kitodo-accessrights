<?php

declare(strict_types=1);

namespace Kitodo\Dlf\Pdp\Service;

use TYPO3\CMS\Core\Log\Logger;
use TYPO3\CMS\Core\Log\LogManager;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use SimpleXMLElement;

final class PdpService
{
    /**
     * @access protected
     * @var Logger This holds the logger
     */
    protected Logger $logger;

    public function __construct() {
        $this->logger = GeneralUtility::makeInstance(LogManager::class)->getLogger(static::class);
    }

    public function processLibRml(
        string $metadataId,
        SimpleXMLElement $libRmlXml,
    ): void {
        $this->logger->warning(
            'LibRML metadata received by PdpService.',
            [
                'metadataId' => $metadataId,
                'rootElement' => $libRmlXml->getName(),
            ],
        );

        // debug($libRmlXml->asXML());
        
        // Next step: parse LibRML rules and prepare the access decision.
    }
}