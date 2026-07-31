<?php

namespace App\Integrations\HubSpot;

use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

class HubSpotEngagementMapper
{
    public const TYPE_MAP = [
        'calls' => 'llamada',
        'meetings' => 'visita',
        'emails' => 'mail',
    ];

    /**
     * @return list<string>
     */
    public static function propertiesFor(string $engagementType): array
    {
        return match ($engagementType) {
            'calls' => ['hs_timestamp', 'hs_call_title', 'hs_call_body', 'hubspot_owner_id'],
            'meetings' => ['hs_timestamp', 'hs_meeting_start_time', 'hs_meeting_title', 'hs_meeting_body', 'hubspot_owner_id'],
            'emails' => ['hs_timestamp', 'hs_email_subject', 'hs_email_html', 'hubspot_owner_id'],
            default => ['hs_timestamp', 'hubspot_owner_id'],
        };
    }

    /**
     * @param  array<string, mixed>  $engagement
     * @param  array<string, int>  $ownerIdToUserId
     * @return array<string, mixed>|null
     */
    public function map(string $engagementType, array $engagement, int $customerId, array $ownerIdToUserId): ?array
    {
        $id = (string) ($engagement['id'] ?? '');

        if ($id === '') {
            return null;
        }

        /** @var array<string, mixed> $properties */
        $properties = $engagement['properties'] ?? [];

        $happenedAt = $this->resolveTimestamp($properties);

        if ($happenedAt === null) {
            return null;
        }

        $notes = $this->buildNotes($engagementType, $properties);

        if ($notes === '') {
            return null;
        }

        $ownerId = $properties['hubspot_owner_id'] ?? null;

        return [
            'customer_id' => $customerId,
            'user_id' => $ownerId !== null ? ($ownerIdToUserId[(string) $ownerId] ?? null) : null,
            'type' => self::TYPE_MAP[$engagementType] ?? 'llamada',
            'happened_at' => $happenedAt,
            'notes' => $notes,
            'source' => 'hubspot',
            'external_id' => "hubspot:{$engagementType}:{$id}",
        ];
    }

    /**
     * @param  array<string, mixed>  $properties
     */
    protected function resolveTimestamp(array $properties): ?CarbonImmutable
    {
        $raw = $properties['hs_meeting_start_time'] ?? $properties['hs_timestamp'] ?? null;

        if ($raw === null || $raw === '') {
            return null;
        }

        return is_numeric($raw)
            ? CarbonImmutable::createFromTimestampMs((int) $raw)
            : CarbonImmutable::parse((string) $raw);
    }

    /**
     * @param  array<string, mixed>  $properties
     */
    protected function buildNotes(string $engagementType, array $properties): string
    {
        [$title, $body] = match ($engagementType) {
            'calls' => [$properties['hs_call_title'] ?? null, $properties['hs_call_body'] ?? null],
            'meetings' => [$properties['hs_meeting_title'] ?? null, $properties['hs_meeting_body'] ?? null],
            'emails' => [$properties['hs_email_subject'] ?? null, $properties['hs_email_html'] ?? null],
            default => [null, null],
        };

        $body = $body !== null ? trim(strip_tags((string) $body)) : null;
        $body = $body !== null && $body !== '' ? Str::limit($body, 2000) : null;
        $title = $title !== null ? trim((string) $title) : null;

        return trim(implode(" \n", array_filter([$title, $body], static fn (?string $v): bool => $v !== null && $v !== '')));
    }
}
