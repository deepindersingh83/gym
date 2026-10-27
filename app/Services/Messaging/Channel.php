<?php

namespace App\Services\Messaging;

use App\Models\Gym;
use App\Models\Member;

abstract class Channel
{
    public function __construct(protected Gym $gym) {}

    abstract public function isConfigured(): bool;

    /** @param  list<string>  $params  Template variables in order. */
    abstract public function send(Member $member, string $recipient, string $body, string $subject, ?string $providerTemplate, array $params): void;
}
