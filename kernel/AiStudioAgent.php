<?php
declare(strict_types=1);

/**
 * De agent kon niet bereikt worden — netwerk, quota, of een fout aan de kant
 * van ai-studio.
 *
 * Dit is uitdrukkelijk iets anders dan een afgekeurde strofe. Slecht werk van
 * de agent hoort de faalteller te verhogen en uiteindelijk te escaleren; een
 * lege provider hoort dat níét te doen. Anders escaleert de loop op iets waar
 * de agent part noch deel aan heeft, en staat juno stil om de verkeerde reden.
 */
final class AgentUnavailable extends RuntimeException {}

/**
 * Praat met ai-studio, de eigen LLM-gateway.
 *
 * Drie dingen die dit ons oplevert boven een directe Anthropic-aanroep:
 *  - de provider-sleutel ligt bij ai-studio, niet bij juno
 *  - de system prompt en het JSON-schema staan in ai-studio's database en zijn
 *    daarmee fysiek buiten het bereik van de agent
 *  - tokengebruik wordt daar al per bericht geregistreerd
 *
 * Elke tick is een nieuw gesprek: geen conversation_id, dus geen groeiende
 * geschiedenis. Het geheugen van juno zit in bestanden, niet in een chat.
 */
final class AiStudioAgent implements Agent
{
    public function __construct(
        private array $config,
        private string $api_url,
        private string $api_key,
    ) {}

    public function run(string $prompt): array
    {
        $request_body = json_encode([
            'prompt'  => $this->config['aistudio']['prompt'],
            'message' => $prompt,
            'user'    => 'juno',
            'model'   => $this->config['aistudio']['model'],
            // bewust géén conversation_id
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $curl = curl_init($this->api_url);
        curl_setopt_array($curl, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $request_body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $this->config['timeout_seconds'],
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $this->api_key,
                'Content-Type: application/json',
                'Accept: application/json',
            ],
        ]);

        $response_body = curl_exec($curl);
        $status_code   = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $curl_error    = curl_error($curl);
        curl_close($curl);

        if ($response_body === false) {
            throw new AgentUnavailable("ai-studio niet bereikbaar: $curl_error");
        }

        $decoded_response = json_decode((string)$response_body, true);
        if (!is_array($decoded_response)) {
            throw new AgentUnavailable("ai-studio gaf geen JSON terug (HTTP $status_code)");
        }

        if ($status_code !== 200) {
            throw new AgentUnavailable($this->describeError($status_code, $decoded_response));
        }

        $message = $decoded_response['message'] ?? null;
        if (!is_array($message) || !isset($message['content'])) {
            throw new AgentUnavailable('antwoord van ai-studio miste het message-veld');
        }

        return [
            'raw'   => (string)$message['content'],
            'usage' => [
                'input_tokens'  => (int)($message['input_tokens'] ?? 0),
                'output_tokens' => (int)($message['output_tokens'] ?? 0),
            ],
        ];
    }

    /** Maakt van de foutenvelop van ai-studio één leesbare regel voor het journal. */
    private function describeError(int $status_code, array $response): string
    {
        // Een validatiefout van Laravel heeft geen error-envelop maar
        // {message, errors}. Zonder deze tak lees je alleen "http_422" terug.
        if ($status_code === 422 && isset($response['errors']) && is_array($response['errors'])) {
            $field_messages = [];
            foreach ($response['errors'] as $field_name => $field_errors) {
                $field_messages[] = $field_name . ': ' . implode(' ', (array)$field_errors);
            }
            return 'ai-studio wees het verzoek af — ' . implode('; ', $field_messages);
        }

        $error      = $response['error'] ?? [];
        $error_code = (string)($error['code'] ?? "http_$status_code");

        $readable = match ($error_code) {
            'provider_quota'   => 'het tegoed bij de provider is op',
            'schema_violation' => 'het model hield zich niet aan het JSON-schema van de prompt',
            'provider_bad_request' => 'ai-studio stuurde een verzoek dat de provider weigerde',
            default            => "ai-studio gaf $error_code (HTTP $status_code)",
        };

        $extra = [];
        if (!empty($error['model'])) {
            $extra[] = 'model ' . $error['model'];
        }
        if (!empty($error['retry_after'])) {
            $extra[] = 'opnieuw proberen over ' . $error['retry_after'] . 's';
        }
        if (!empty($error['alternatives'])) {
            $alternative_ids = array_map(
                fn($alternative) => is_array($alternative) ? ($alternative['model'] ?? '?') : (string)$alternative,
                $error['alternatives']
            );
            $extra[] = 'uitwijk mogelijk naar: ' . implode(', ', $alternative_ids);
        }

        return $readable . ($extra === [] ? '' : ' (' . implode('; ', $extra) . ')');
    }
}

/** Leest een .env-bestand met simpele KEY=value regels. */
function read_env_file(string $path): array
{
    $values = [];
    foreach (@file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = explode('=', $line, 2);
        $values[trim($key)] = trim($value, " \t\"'");
    }
    return $values;
}
