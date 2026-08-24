<?php

declare(strict_types=1);

namespace ClickTrail\Consent;

/**
 * Normalized consent value. "unknown" is treated as DENIED everywhere
 * (marketplace-safe default); "not_applicable" means the CMP does not
 * expose that signal and no mapping was configured.
 */
enum ConsentValue: string
{
    case Granted = 'granted';
    case Denied = 'denied';
    case Unknown = 'unknown';
    case NotApplicable = 'not_applicable';

    /** Effective permission under the unknown=denied default. */
    public function isGranted(): bool
    {
        return $this === self::Granted;
    }
}
