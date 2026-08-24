<?php

declare(strict_types=1);

namespace ClickTrail\Client\Exception;

/** Transport-level failure that may succeed on retry (429, 5xx, network). */
final class RetryableException extends \RuntimeException
{
}
