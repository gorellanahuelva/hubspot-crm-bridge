<?php
/**
 * HubSpot Deals API
 *
 * Creates a deal in HubSpot and associates it with a contact.
 *
 * In this retail integration, a deal is automatically opened for every
 * new customer interaction (store visit / sales ticket), pre-populated
 * with the product types and collections the customer showed interest in.
 *
 * Reference:
 *   https://developers.hubspot.com/docs/api/crm/deals
 */
class HubSpotDeals
{
    const DEALS_URL = 'https://api.hubapi.com/deals/v1/deal';

    private string $accessToken;

    public function __construct(string $accessToken)
    {
        $this->accessToken = $accessToken;
    }

    /**
     * Create a deal and associate it with a HubSpot contact.
     *
     * @param  string $contactId   HubSpot contact VID to associate with.
     * @param  array  $data        Deal details:
     *   - name           (string)  Deal name, e.g. "Visit — John Doe (rep: Jane)"
     *   - owner_id       (string)  HubSpot user ID of the deal owner
     *   - stage          (string)  Deal stage ID, e.g. "qualifiedtobuy"
     *   - pipeline       (string)  Pipeline ID, e.g. "default"
     *   - close_date     (int)     Unix timestamp for the expected close date
     *   - deal_type      (string)  e.g. "newbusiness"
     *   - description    (string)  Free-text summary of product interest
     *
     * @return array|null  HubSpot deal object on success, null on failure.
     */
    public function create(string $contactId, array $data): ?array
    {
        $payload = [
            'associations' => [
                'associatedVids' => [(int) $contactId],
            ],
            'properties' => [
                ['name' => 'dealname',         'value' => $data['name']],
                ['name' => 'dealstage',        'value' => $data['stage']        ?? 'qualifiedtobuy'],
                ['name' => 'pipeline',         'value' => $data['pipeline']     ?? 'default'],
                ['name' => 'hubspot_owner_id', 'value' => $data['owner_id']],
                ['name' => 'closedate',        'value' => $data['close_date']   ?? time()],
                ['name' => 'dealtype',         'value' => $data['deal_type']    ?? 'newbusiness'],
                ['name' => 'description',      'value' => $data['description']  ?? ''],
            ],
        ];

        $ch = curl_init(self::DEALS_URL);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
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

        $response = json_decode($raw, true);
        return ($status === 200 || $status === 201) ? $response : null;
    }
}
