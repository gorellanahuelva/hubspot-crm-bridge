<?php
/**
 * HubSpot OAuth 2.0 — Authorization Code Flow
 *
 * Handles the full OAuth handshake:
 *   1. Build the authorization URL to redirect the user to HubSpot.
 *   2. Exchange the returned authorization code for access + refresh tokens.
 *   3. Verify the connected account matches the expected company domain.
 *   4. Store tokens in cookies (short-lived; 6-hour TTL).
 *
 * Reference: https://developers.hubspot.com/docs/api/oauth-quickstart-guide
 */
class HubSpotOAuth
{
    const AUTH_URL  = 'https://app.hubspot.com/oauth/authorize';
    const TOKEN_URL = 'https://api.hubapi.com/oauth/v1/token';
    const INFO_URL  = 'https://api.hubapi.com/oauth/v1/access-tokens/';

    private string $clientId;
    private string $clientSecret;
    private string $redirectUri;
    private string $accountDomain;
    private int    $tokenTtl;

    public function __construct(array $config)
    {
        $this->clientId      = $config['client_id'];
        $this->clientSecret  = $config['client_secret'];
        $this->redirectUri   = $config['redirect_uri'];
        $this->accountDomain = $config['account_domain'];
        $this->tokenTtl      = $config['token_ttl'] ?? 21600;
    }

    /**
     * Build the HubSpot authorization URL.
     * Redirect the user here to start the OAuth flow.
     *
     * @param  array  $scopes  e.g. ['oauth', 'contacts', 'timeline', 'tickets']
     * @return string
     */
    public function getAuthorizationUrl(array $scopes): string
    {
        return self::AUTH_URL . '?' . http_build_query([
            'client_id'    => $this->clientId,
            'redirect_uri' => $this->redirectUri,
            'scope'        => implode(' ', $scopes),
        ]);
    }

    /**
     * Exchange an authorization code for access + refresh tokens.
     * Call this after HubSpot redirects back with ?code=...
     *
     * @param  string $code  The authorization code from the query string.
     * @return array|null    ['access_token' => ..., 'refresh_token' => ...] or null on failure.
     */
    public function exchangeCode(string $code): ?array
    {
        $response = $this->post(self::TOKEN_URL, [
            'grant_type'    => 'authorization_code',
            'client_id'     => $this->clientId,
            'client_secret' => $this->clientSecret,
            'redirect_uri'  => $this->redirectUri,
            'code'          => $code,
        ], 'application/x-www-form-urlencoded');

        return $response['status'] === 200 ? $response['body'] : null;
    }

    /**
     * Verify that the token belongs to the expected HubSpot account.
     * Prevents connecting an unauthorized portal.
     *
     * @param  string $accessToken
     * @return bool
     */
    public function verifyAccountDomain(string $accessToken): bool
    {
        $response = $this->get(self::INFO_URL . $accessToken);

        if ($response['status'] !== 200) {
            return false;
        }

        return isset($response['body']['hub_domain'])
            && $response['body']['hub_domain'] === $this->accountDomain;
    }

    /**
     * Store access and refresh tokens in cookies.
     *
     * In production, prefer server-side session storage or a database
     * over cookies for sensitive tokens.
     *
     * @param  string $accessToken
     * @param  string $refreshToken
     */
    public function storeTokens(string $accessToken, string $refreshToken): void
    {
        $expiry = time() + $this->tokenTtl;
        setcookie('hubspot_access_token',  $accessToken,  $expiry, '/', '', true, true);
        setcookie('hubspot_refresh_token', $refreshToken, $expiry, '/', '', true, true);
    }

    // -------------------------------------------------------------------------
    // Internal HTTP helpers
    // -------------------------------------------------------------------------

    private function post(string $url, array $fields, string $contentType = 'application/json'): array
    {
        $ch = curl_init($url);
        $body = ($contentType === 'application/json') ? json_encode($fields) : http_build_query($fields);

        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => ["Content-Type: $contentType"],
            CURLOPT_TIMEOUT        => 30,
        ]);

        $raw    = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return ['status' => $status, 'body' => json_decode($raw, true)];
    }

    private function get(string $url): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
        ]);

        $raw    = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return ['status' => $status, 'body' => json_decode($raw, true)];
    }
}
