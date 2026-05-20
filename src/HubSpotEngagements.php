<?php
/**
 * HubSpot Engagements API
 *
 * Logs MEETING and NOTE engagements against a HubSpot contact.
 *
 * In this retail integration, a MEETING represents a customer's store visit
 * and a NOTE captures the salesperson's observations about the interaction.
 * Both are linked to the contact created (or found) during checkout.
 *
 * Reference:
 *   https://developers.hubspot.com/docs/api/crm/engagements
 */
class HubSpotEngagements
{
    const ENGAGEMENTS_URL = 'https://api.hubapi.com/engagements/v1/engagements';

    private string $accessToken;

    public function __construct(string $accessToken)
    {
        $this->accessToken = $accessToken;
    }

    /**
     * Log a store visit as a MEETING engagement.
     *
     * @param  string $contactId    HubSpot contact VID.
     * @param  array  $data         Visit details:
     *   - owner_id     (string)  HubSpot user ID of the responsible rep
     *   - title        (string)  Engagement title, e.g. "Store Visit"
     *   - body         (string)  Description of the interaction
     *   - timestamp    (int)     Unix timestamp of the visit
     *
     * @return array|null  HubSpot engagement response, or null on failure.
     */
    public function createMeeting(string $contactId, array $data): ?array
    {
        return $this->create($contactId, 'MEETING', $data);
    }

    /**
     * Log a salesperson's notes as a NOTE engagement.
     *
     * @param  string $contactId  HubSpot contact VID.
     * @param  array  $data       Note details:
     *   - owner_id   (string)  HubSpot user ID
     *   - body       (string)  The note text
     *   - timestamp  (int)     Unix timestamp
     *
     * @return array|null
     */
    public function createNote(string $contactId, array $data): ?array
    {
        return $this->create($contactId, 'NOTE', $data);
    }

    /**
     * Create an engagement and associate it with a contact.
     *
     * Engagement structure:
     *   - engagement:   type, active status, owner, timestamp
     *   - associations: contact IDs to link (companies, deals left empty here)
     *   - metadata:     type-specific fields (title + body for MEETING, body for NOTE)
     *
     * @param  string $contactId
     * @param  string $type       'MEETING' or 'NOTE'
     * @param  array  $data
     * @return array|null
     */
    private function create(string $contactId, string $type, array $data): ?array
    {
        $timestamp = $data['timestamp'] ?? time();

        $payload = [
            'engagement' => [
                'active'    => true,
                'type'      => $type,
                'ownerId'   => $data['owner_id'],
                'createdAt' => $timestamp,
            ],
            'associations' => [
                'contactIds' => [(int) $contactId],
                'companyIds' => [],
                'dealIds'    => [],
                'ownerIds'   => [],
            ],
            'metadata' => $this->buildMetadata($type, $data, $timestamp),
        ];

        $ch = curl_init(self::ENGAGEMENTS_URL);
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

    /**
     * Build type-specific metadata for the engagement.
     */
    private function buildMetadata(string $type, array $data, int $timestamp): array
    {
        if ($type === 'MEETING') {
            return [
                'title'     => $data['title'] ?? 'Store Visit',
                'body'      => $data['body']  ?? '',
                'startTime' => $timestamp,
            ];
        }

        // NOTE
        return [
            'body' => $data['body'] ?? '',
        ];
    }
}
