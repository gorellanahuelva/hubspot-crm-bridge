<?php
/**
 * Example: Full Contact + Engagement + Deal flow
 *
 * Simulates what happens when a salesperson creates a new sales ticket
 * in the in-store ERP. The flow:
 *
 *   1. Search HubSpot for an existing contact by phone or email.
 *   2. Create a new contact if none found (deduplication).
 *   3. Log the store visit as a MEETING engagement.
 *   4. Add a NOTE if the salesperson left observations.
 *   5. Open a Deal linked to the contact.
 */

require_once __DIR__ . '/../src/HubSpotContacts.php';
require_once __DIR__ . '/../src/HubSpotEngagements.php';
require_once __DIR__ . '/../src/HubSpotDeals.php';

// ── Retrieve the access token stored after OAuth ──────────────────────────────
$accessToken = $_COOKIE['hubspot_access_token'] ?? null;

if (!$accessToken) {
    die('Not authenticated. Run oauth-flow.php first.');
}

// ── Sample data (in production this comes from your POS / ERP form) ───────────
$visitData = [
    // Contact info
    'firstname'      => 'Maria',
    'lastname'       => 'Lopez',
    'email'          => 'maria.lopez@example.com',
    'phone'          => '50250001234',

    // Product interest captured during the visit
    'product_types'  => ['Hardside Luggage', 'Backpacks'],
    'collections'    => ['19 Degree', 'Alpha Bravo'],

    // Sales rep assignment
    'sales_associate'    => 9,              // Internal staff ID
    'hubspot_owner_id'   => '49892735',     // HubSpot user ID of the rep
    'rep_name'           => 'Jane Smith',

    // Optional notes from the salesperson
    'notes'          => 'Customer is traveling in two weeks, interested in carry-on options.',

    // Lead metadata
    'hs_lead_status' => 'NEW',
];

// ── Initialise API clients ────────────────────────────────────────────────────
$contacts    = new HubSpotContacts($accessToken);
$engagements = new HubSpotEngagements($accessToken);
$deals       = new HubSpotDeals($accessToken);

// ── 1. Deduplication search ───────────────────────────────────────────────────
$existing = $contacts->search($visitData['phone'], $visitData['email']);

if ($existing['total'] > 0) {
    // Contact already exists — reuse their ID
    $contactId = (string) $existing['results'][0]['id'];
    echo "Existing contact found: $contactId\n";
} else {
    // ── 2. Create new contact ─────────────────────────────────────────────────
    $newContact = $contacts->create($visitData);

    if (!$newContact) {
        die('Failed to create HubSpot contact.');
    }

    $contactId = $contacts->extractId($newContact);
    echo "New contact created: $contactId\n";
}

$timestamp = time();

// ── 3. Log store visit as a MEETING ──────────────────────────────────────────
$meetingBody = sprintf(
    'Customer %s %s visited the store. Attended by sales rep %s. '
    . 'Showed interest in SKUs: %s.',
    $visitData['firstname'],
    $visitData['lastname'],
    $visitData['rep_name'],
    implode(', ', $visitData['collections'])
);

$meeting = $engagements->createMeeting($contactId, [
    'owner_id'  => $visitData['hubspot_owner_id'],
    'title'     => 'Store Visit',
    'body'      => $meetingBody,
    'timestamp' => $timestamp,
]);

echo $meeting ? "Meeting engagement logged.\n" : "Warning: meeting engagement failed.\n";

// ── 4. Add salesperson note (if provided) ─────────────────────────────────────
if (!empty($visitData['notes'])) {
    $noteBody = sprintf(
        '%s added the following note about %s %s: "%s"',
        $visitData['rep_name'],
        $visitData['firstname'],
        $visitData['lastname'],
        $visitData['notes']
    );

    $note = $engagements->createNote($contactId, [
        'owner_id'  => $visitData['hubspot_owner_id'],
        'body'      => $noteBody,
        'timestamp' => $timestamp,
    ]);

    echo $note ? "Note engagement logged.\n" : "Warning: note engagement failed.\n";
}

// ── 5. Open a Deal ────────────────────────────────────────────────────────────
$dealDescription = sprintf(
    'Interest in product types: %s. Collections: %s.',
    implode(', ', $visitData['product_types']),
    implode(', ', $visitData['collections'])
);

$deal = $deals->create($contactId, [
    'name'        => sprintf(
        'Visit — %s %s (rep: %s)',
        $visitData['firstname'],
        $visitData['lastname'],
        $visitData['rep_name']
    ),
    'owner_id'    => $visitData['hubspot_owner_id'],
    'stage'       => 'qualifiedtobuy',
    'pipeline'    => 'default',
    'close_date'  => $timestamp,
    'deal_type'   => 'newbusiness',
    'description' => $dealDescription,
]);

echo $deal ? "Deal created and linked to contact.\n" : "Warning: deal creation failed.\n";
