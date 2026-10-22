<?php

declare(strict_types=1);

namespace Kitodo\Dlf\Pdp\Service;

use SimpleXMLElement;

final class PdpService
{
    public function processLibRml(
        string $mdId,
        SimpleXMLElement $libRmlXml,
    ): void {
        // dump the rightsMD ID
        debug($mdId, 'rightsMD ID');
    
        // dump the SimpleXMLElement Object
        debug($libRmlXml->asXML(), 'SimpleXMLElement Object');

        // Next step: parse LibRML rules and prepare the access decision.
    }
}