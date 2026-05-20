<?php
/**
 * HubSpot Contacts API
 *
 * Handles contact search (deduplication) and creation.
 *
 * Deduplication strategy:
 *   Before creating a contact, the system searches by phone and/or email
 *   using HubSpot's filterGroups with OR logic. If a match is found, the
 *   existing contact is reused and no duplicate is created.
 *
 * Custom properties:
 *   The integration uses custom contact properties to capture retail-specific
 *   data (product preferences, collections, sales associate). These must be
 *   created in your HubSpot portal before use.
 *
 * Reference:
 *   https://developers.hubspot.com/docs/api/crm/contacts
 */
class HubSpotContacts
{
    const SEARCH_URL = 'https://api.hubapi.com/crm/v3/objects/contacts/search';
    const CREATE_URL = 'https://api.hubapi.com/contacts/v1/contact/';

    private string $accessToken;

    public function __construct(string $accessToken)
    {
        $this->accessToken = $accessToken;
    }

    /**
     * Search for an existing contact by phone and/or email.
     *
     * Uses filterGroups with OR logic: a contact matches if either
     * the phone OR the email equals the provided values.
     *
     * @param  string|null $phone
     * @param  string|null $email
     * @return array  HubSpot search response. Check ['total'] > 0 for a match.
     */
    public function search(?string $phone, ?string $email): array
    {
        $filterGroups = [];

        // Each filterGroup is an independent OR condition.
        // Within a group, filters are ANDed — here each group has one filter.
        if (!empty($phone)) {
            $filterGroups[] = [
                'filters' => [[
                    'propertyName' => 'phone',
                    'operator'     => 'EQ',
                    'value'        => $phone,
                ]],
            ];
        }

        if (!empty($email)) {
            $filterGroups[] = [
                'filters' => [[
                    'propertyName' => 'email',
                    'operator'     => 'EQ',
                    'value'        => $email,
                ]],
            ];
        }

        if (empty($filterGroups)) {
            return ['total' => 0, 'results' => []];
        }

        $response = $this->post(self::SEARCH_URL, [
            'filterGroups' => $filterGroups,
        ]);

        return $response['body'] ?? ['total' => 0, 'results' => []];
    }

    /**
     * Create a new HubSpot contact.
     *
     * @param  array $data  Contact fields:
     *   - firstname            (string)
     *   - lastname             (string)
     *   - email                (string, optional)
     *   - phone                (string, optional)
     *   - hs_lead_status       (string) e.g. "NEW"
     *   - hubspot_owner_id     (string) HubSpot user ID of the assigned owner
     *   - sales_associate      (string|int) Internal staff identifier
     *   - product_types        (array)  Product categories shown during visit
     *   - collections          (array)  Product collections shown during visit
     *
     * @return array|null  HubSpot contact object on success, null on failure.
     */
    public function create(array $data): ?array
    {
        $properties = [
            ['property' => 'firstname',        'value' => $data['firstname']],
            ['property' => 'lastname',         'value' => $data['lastname']],
            ['property' => 'hs_lead_status',   'value' => $data['hs_lead_status'] ?? 'NEW'],
            ['property' => 'hubspot_owner_id', 'value' => $data['hubspot_owner_id']],
            ['property' => 'sales_associate',  'value' => $data['sales_associate']],
            // Custom properties — must exist in your HubSpot portal
            ['property' => 'preferred_types_of_products', 'value' => implode(';', $data['product_types'] ?? [])],
            ['property' => 'tumi_collections_interested',  'value' => implode(';', $data['collections'] ?? [])],
        ];

        if (!empty($data['email'])) {
            $properties[] = ['property' => 'email', 'value' => $data['email']];
        }

        if (!empty($data['phone'])) {
            $properties[] = ['property' => 'phone', 'value' => $data['phone']];
        }

        $response = $this->post(self::CREATE_URL, ['properties' => $properties]);

        return ($response['status'] === 200) ? $response['body'] : null;
    }

    /**
     * Extract the contact ID from a create or search response.
     *
     * HubSpot uses 'vid' in v1 responses and 'id' in v3 responses.
     *
     * @param  array $response
     * @return string|null
     */
    public function extractId(array $response): ?string
    {
        if (isset($response['vid'])) {
            return (string) $response['vid'];
        }
        if (isset($response['results'][0]['id'])) {
            return (string) $response['results'][0]['id'];
        }
        return null;
    }

    // -------------------------------------------------------------------------
    // Internal HTTP helper
    // -------------------------------------------------------------------------

    private function post(string $url, array $payload): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Accept: application/json',
                "Authorization: Bearer {$this->accessToken}",
            ],
            CURLOPT_TIMEOUT        => 30,
        ]);

        $raw    = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return ['status' => $status, 'body' => json_decode($raw, true)];
    }
}
