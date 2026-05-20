<?php
/**
 * Example: HubSpot OAuth 2.0 Flow
 *
 * This shows the two-step OAuth handshake:
 *   Step 1 — Redirect the user to HubSpot to authorize access.
 *   Step 2 — Handle the callback, exchange the code for tokens,
 *             verify the account domain, and store the tokens.
 */

require_once __DIR__ . '/../src/HubSpotOAuth.php';

$config = require __DIR__ . '/../config.php';
$oauth  = new HubSpotOAuth($config);

$code         = $_GET['code']  ?? null;
$accessToken  = $_COOKIE['hubspot_access_token']  ?? null;
$scopes       = ['oauth', 'contacts', 'timeline', 'tickets'];

// ── Step 2: Handle OAuth callback ────────────────────────────────────────────
if ($code && !$accessToken) {

    $tokens = $oauth->exchangeCode($code);

    if (!$tokens) {
        die('Token exchange failed. Check your client ID, secret, and redirect URI.');
    }

    // Verify the connected portal belongs to the expected company
    if (!$oauth->verifyAccountDomain($tokens['access_token'])) {
        die('Unauthorized HubSpot portal. Connection rejected.');
    }

    $oauth->storeTokens($tokens['access_token'], $tokens['refresh_token']);
    echo 'Connected successfully. Access token stored in cookie.';
    exit;
}

// ── Step 1: Redirect to HubSpot authorization ────────────────────────────────
if (!$accessToken) {
    $authUrl = $oauth->getAuthorizationUrl($scopes);
    header("Location: $authUrl");
    exit;
}

echo 'Already authenticated. Access token present in cookie.';
