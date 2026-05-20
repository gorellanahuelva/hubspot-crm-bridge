# HubSpot CRM Bridge

A reference implementation for integrating a retail point-of-sale system with HubSpot CRM using PHP and OAuth 2.0.

This bridge was built to sync in-store sales interactions (customer visits, product interest, and deals) from a custom ERP/POS into HubSpot CRM in real time.

---

## What it does

1. **OAuth 2.0 flow** — authenticates against HubSpot using the authorization code flow, verifies the connected account belongs to the expected domain, and stores short-lived access tokens.
2. **Contact deduplication** — before creating a contact, searches HubSpot by phone and/or email using `filterGroups` to avoid duplicates.
3. **Contact creation** — creates contacts with standard and custom properties (product preferences, collections of interest, lead status, assigned sales rep).
4. **Engagement logging** — logs a `MEETING` engagement for every store visit and an optional `NOTE` with sales rep observations, both linked to the contact.
5. **Deal creation** — automatically opens a new deal associated with the contact, pre-populated with stage, pipeline, owner, and product interest description.

---

## File structure

```
hubspot-crm-bridge/
├── src/
│   ├── HubSpotOAuth.php        # OAuth 2.0 authorization code flow
│   ├── HubSpotContacts.php     # Contact search and creation
│   ├── HubSpotEngagements.php  # Meeting and note engagement logging
│   └── HubSpotDeals.php        # Deal creation
├── examples/
│   ├── oauth-flow.php          # Example: complete OAuth flow
│   └── create-contact.php      # Example: full contact + engagement + deal flow
├── config.example.php          # Configuration template
└── README.md
```

---

## Requirements

- PHP 7.4+
- `curl` extension enabled
- A HubSpot developer account with an OAuth app configured
- Scopes required: `oauth`, `contacts`, `timeline`, `tickets`

---

## Configuration

Copy `config.example.php` to `config.php` and fill in your values:

```php
return [
    'client_id'     => 'YOUR_HUBSPOT_APP_CLIENT_ID',
    'client_secret' => 'YOUR_HUBSPOT_APP_CLIENT_SECRET',
    'redirect_uri'  => 'https://yourdomain.com/oauth/callback',
    'account_domain'=> 'yourdomain.com', // Only allow connections from this HubSpot account
];
```

---

## HubSpot App setup

1. Go to [developers.hubspot.com](https://developers.hubspot.com) → Create App
2. Under **Auth**, set your redirect URI
3. Request scopes: `oauth`, `contacts`, `timeline`, `tickets`
4. Note your **Client ID** and **Client Secret**

---

## Custom CRM properties used

These properties must exist in your HubSpot portal before use (create via Settings → Properties):

| Property name | Type | Description |
|---|---|---|
| `preferred_types_of_products` | Checkbox | Product categories the customer showed interest in |
| `tumi_collections_interested` | Checkbox | Product collections shown during visit |
| `sales_associate` | Number | Internal staff ID of the salesperson |

---

## Usage

See `examples/` for end-to-end usage. Core flow:

```php
$oauth    = new HubSpotOAuth($config);
$contacts = new HubSpotContacts($accessToken);
$engagements = new HubSpotEngagements($accessToken);
$deals    = new HubSpotDeals($accessToken);

// 1. Check for existing contact
$existing = $contacts->search($phone, $email);

// 2. Create if new
if ($existing['total'] < 1) {
    $result = $contacts->create($contactData);
    $contactId = $result['vid'];

    // 3. Log the store visit as a meeting
    $engagements->createMeeting($contactId, $visitData);

    // 4. Open a deal
    $deals->create($contactId, $dealData);
}
```
