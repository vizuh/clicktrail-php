<?php

declare(strict_types=1);

namespace ClickTrail\Consent;

/**
 * One normalized consent decision snapshot. PHP mirror of the
 * ClickTrailConsentSnapshot contract in docs/consent-compatibility-plan.md.
 *
 * Adapters (WP Consent API, CookieYes, Cookiebot, iubenda, ...) produce this;
 * everything downstream talks only to this shape - never to a CMP directly.
 * Snapshots travel with leads/orders so offline-conversion workers can apply
 * destination policy months later.
 */
final class ConsentSnapshot
{
    /**
     * @param string[] $rawCategoryIds
     */
    public function __construct(
        public readonly string $source,
        public readonly string $collectedAt,
        public readonly ConsentValue $functionalStorage,
        public readonly ConsentValue $analyticsStorage,
        public readonly ConsentValue $advertisingStorage,
        public readonly ConsentValue $adUserData,
        public readonly ConsentValue $adPersonalization,
        public readonly ?string $policyVersion = null,
        public readonly ?string $receiptId = null,
        public readonly array $rawCategoryIds = [],
    ) {
    }

    /** Capability names accepted by ConsentBehavior::can(). */
    public const CAP_ANALYTICS = 'analytics';
    public const CAP_ADVERTISING_STORAGE = 'advertising_storage';
    public const CAP_AD_USER_DATA = 'ad_user_data';
    public const CAP_AD_PERSONALIZATION = 'ad_personalization';

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'source' => $this->source,
            'collected_at' => $this->collectedAt,
            'policy_version' => $this->policyVersion,
            'receipt_id' => $this->receiptId,
            'functional_storage' => $this->functionalStorage->value,
            'analytics_storage' => $this->analyticsStorage->value,
            'advertising_storage' => $this->advertisingStorage->value,
            'ad_user_data' => $this->adUserData->value,
            'ad_personalization' => $this->adPersonalization->value,
            'raw_category_ids' => $this->rawCategoryIds === [] ? null : $this->rawCategoryIds,
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $val = static fn(mixed $v): ConsentValue => ConsentValue::tryFrom((string) $v) ?? ConsentValue::Unknown;

        return new self(
            source: (string) ($data['source'] ?? 'custom'),
            collectedAt: (string) ($data['collected_at'] ?? ''),
            functionalStorage: $val($data['functional_storage'] ?? 'unknown'),
            analyticsStorage: $val($data['analytics_storage'] ?? 'unknown'),
            advertisingStorage: $val($data['advertising_storage'] ?? 'unknown'),
            adUserData: $val($data['ad_user_data'] ?? 'unknown'),
            adPersonalization: $val($data['ad_personalization'] ?? 'unknown'),
            policyVersion: isset($data['policy_version']) ? (string) $data['policy_version'] : null,
            receiptId: isset($data['receipt_id']) ? (string) $data['receipt_id'] : null,
            rawCategoryIds: is_array($data['raw_category_ids'] ?? null) ? array_map('strval', $data['raw_category_ids']) : [],
        );
    }

    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_THROW_ON_ERROR);
    }
}
