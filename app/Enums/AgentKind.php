<?php

namespace App\Enums;

/**
 * Agent: een Claude Code-chat met een eigen rol en eigen ticks. Human: Lucas, zonder ticks.
 */
enum AgentKind: string
{
    case Agent = 'Agent';
    case Human = 'Human';
}
