<?php
/**
 * HubSpot CRM Bridge — Configuration Template
 * Copy this file to config.php and fill in your values.
 * Never commit config.php to version control.
 */
return [
    // HubSpot OAuth App credentials (from developers.hubspot.com)
    'client_id'      => 'YOUR_HUBSPOT_APP_CLIENT_ID',
    'client_secret'  => 'YOUR_HUBSPOT_APP_CLIENT_SECRET',

    // Must match the redirect URI registered in your HubSpot app
    'redirect_uri'   => 'https://yourdomain.com/oauth/callback',

    // Only accept tokens from this HubSpot account domain
    'account_domain' => 'yourdomain.com',

    // Token TTL in seconds (HubSpot access tokens expire in 6 hours)
    'token_ttl'      => 21600,
];
