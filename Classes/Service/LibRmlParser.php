<?php
declare(strict_types=1);

namespace Kitodo\Dlf\Pdp\Service;

use Kitodo\Dlf\Pdp\Domain\Model\ActionRule;
use Kitodo\Dlf\Pdp\Domain\Model\Restriction;

final class InvalidRightsException extends \RuntimeException {}

final class LibRmlParser
{
    public const NS = 'http://librml.org/schema';

    /**
     * @return ActionRule[]|null null = no rights description at all (blank input)
     */
    public function rulesFor(string $xml, string $actionType): ?array
    {
        // Leading whitespace before <?xml makes the document invalid; pasted text often has it
        $xml = trim($xml);
        if ($xml === '') {
            return null;
        }

        $dom = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);
        try {
            // LIBXML_NONET: never fetch anything over the network. No LIBXML_NOENT: no entity expansion.
            if (!$dom->loadXML($xml, LIBXML_NONET)) {
                throw new InvalidRightsException('LibRML is not well-formed XML');
            }
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        $xpath = new \DOMXPath($dom);
        $xpath->registerNamespace('l', self::NS);

        $rules = [];
        // If the namespace is wrong this finds nothing -> empty list -> PDP says Denied (fail closed)
        foreach ($xpath->query('/l:libRML/l:item/l:action') as $action) {
            /** @var \DOMElement $action */
            if ($action->getAttribute('type') !== $actionType) {
                continue; // compare in PHP rather than building XPath from strings
            }
            $restrictions = [];
            foreach ($xpath->query('l:restriction', $action) as $restriction) {
                /** @var \DOMElement $restriction */
                $type = strtolower($restriction->getAttribute('type'));
                $attribute = $type === 'location' ? 'locations' : 'groups';
                $values = preg_split('/[,\s]+/', strtolower($restriction->getAttribute($attribute)), -1, PREG_SPLIT_NO_EMPTY);
                $restrictions[] = new Restriction($type, $values);
            }
            $rules[] = new ActionRule(
                strtolower($action->getAttribute('permission')) === 'true',
                $restrictions,
            );
        }
        return $rules;
    }
}