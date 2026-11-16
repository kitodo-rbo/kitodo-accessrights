<?php

declare(strict_types=1);

namespace Kitodo\Dlf\Pdp\Service;

use InvalidArgumentException;
use TYPO3\CMS\Core\Page\AssetCollector;

/**
 * Hands the access token to the browser.
 *
 * The browser cannot send headers from a plain <img> tag, so two things are registered on the page:
 *   1. a tiny inline script that stores the token in sessionStorage and tells the JavaScript
 *      which origins (= the image server) may receive it, and
 *   2. rbo-auth.js, which attaches "Authorization: Bearer <token>" to requests to those origins.
 *
 * SECURITY NOTES
 *  - The token is per user. A page containing it must NEVER be served from the page cache to someone else.
 *    The caller has to disable caching for the request (see README / listener example).
 *  - Everything that ends up inside the inline <script> is validated first (token alphabet, origin
 *    syntax) and JSON-encoded with the HEX flags, so it cannot break out of the script element.
 *
 * Validation of the origin happens in inject(), not in the constructor: a missing or wrong setting must
 * not break every frontend page that merely instantiates this service (e.g. open-access documents).
 *
 * @access public
 */
final class BrowserTokenInjector
{
    public const STORAGE_KEY = 'dlf.rbo.token';

    private const JSON_FLAGS = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR;

    public function __construct(
        private readonly AssetCollector $assetCollector,
        private readonly ?string $imageServerOrigin = null
    ) {}

    /**
     * Registers the token script (head, runs first) and rbo-auth.js (footer, after Kitodo's own scripts).
     *
     * @throws InvalidArgumentException if the token or the configured image server origin is malformed
     */
    public function provide(string $token): void
    {
        self::assertTokenShape($token);
        $origin = self::normalizeOrigin((string) $this->imageServerOrigin);

        $this->assetCollector->addInlineJavaScript(
            'dlf-pdp-token',
            self::tokenScript($token, $origin),
            [],
            ['priority' => true]
        );
        $this->assetCollector->addJavaScript(
            'dlf-pdp-auth',
            'EXT:dlf_pdp/Resources/Public/JavaScript/rbo-auth.js'
        );
    }

    /**
     * For pages without a token: remove a stale token left in this tab by a previously viewed document.
     */
    public function clear(): void
    {
        $this->assetCollector->addInlineJavaScript(
            'dlf-pdp-token',
            self::clearScript(),
            [],
            ['priority' => true]
        );
    }

    public static function tokenScript(string $token, string $normalizedOrigin): string
    {
        return 'window.DlfPdp=' . json_encode(['origins' => [$normalizedOrigin]], self::JSON_FLAGS) . ';'
            . 'try{sessionStorage.setItem(' . json_encode(self::STORAGE_KEY, self::JSON_FLAGS)
            . ',' . json_encode($token, self::JSON_FLAGS) . ');}catch(e){}';
    }

    public static function clearScript(): string
    {
        return 'try{sessionStorage.removeItem(' . json_encode(self::STORAGE_KEY, self::JSON_FLAGS) . ');}catch(e){}';
    }

    /**
     * A JWT is three base64url segments. Anything else is refused before it gets near a <script> element.
     */
    public static function assertTokenShape(string $token): void
    {
        if (preg_match('/^[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+$/', $token) !== 1) {
            throw new InvalidArgumentException('Token is not a compact JWT (header.payload.signature).', 1731500001);
        }
    }

    /**
     * Brings an origin into the exact form that JavaScript's `new URL(x).origin` produces, because
     * rbo-auth.js compares origins as plain strings: scheme://host[:port], lower case, no default port,
     * no path, no trailing slash. "https://host:443/" and "https://host" must therefore both become "https://host".
     *
     * @throws InvalidArgumentException
     */
    public static function normalizeOrigin(string $origin): string
    {
        $parts = parse_url(trim($origin));
        if ($parts === false || !isset($parts['scheme'], $parts['host'])) {
            throw new InvalidArgumentException('Image server origin must look like https://host[:port].', 1731500002);
        }

        $scheme = strtolower($parts['scheme']);
        if (!in_array($scheme, ['http', 'https'], true)) {
            throw new InvalidArgumentException('Image server origin must use http or https.', 1731500003);
        }
        if (isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])) {
            throw new InvalidArgumentException('Image server origin must not contain credentials, query or fragment.', 1731500004);
        }
        if (isset($parts['path']) && $parts['path'] !== '' && $parts['path'] !== '/') {
            throw new InvalidArgumentException('Image server origin must not contain a path.', 1731500005);
        }

        $host = strtolower($parts['host']);
        if (preg_match('/^[a-z0-9.\-\[\]:]+$/', $host) !== 1) {
            throw new InvalidArgumentException('Image server origin contains invalid host characters.', 1731500006);
        }

        $port = $parts['port'] ?? null;
        $defaultPort = $scheme === 'https' ? 443 : 80;

        return $scheme . '://' . $host . ($port !== null && $port !== $defaultPort ? ':' . $port : '');
    }
}
