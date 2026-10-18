<?php
declare(strict_types=1);

/**
 * Praat met ai-studio, de eigen LLM-gateway.
 *
 * Drie dingen die dat oplevert boven een directe aanroep bij een provider:
 *   - de providersleutel ligt bij de gateway, niet bij deze gebruiker
 *   - systeemprompt en antwoordschema staan in de database van de gateway en
 *     liggen daarmee fysiek buiten het bereik van de agent
 *   - tokengebruik wordt daar al per bericht geregistreerd
 *
 * Elke tick is een nieuw gesprek: geen conversation_id, dus geen groeiende
 * geschiedenis. Het geheugen van juno zit in bestanden, niet in een chat.
 */
final class AiStudioAgent implements Agent
{
    public function __construct(
        private Config $config,
        private string $apiKey,
        private ?string $modelOverride = null,
    ) {}

    public function naam(): string
    {
        return 'ai-studio:' . ($this->modelOverride ?? $this->config->aistudioModel);
    }

    public function vraag(string $opdracht): array
    {
        $body = json_encode([
            'prompt'  => $this->config->aistudioPrompt,
            'message' => $opdracht,
            'user'    => 'juno',
            'model'   => $this->modelOverride ?? $this->config->aistudioModel,
            // bewust géén conversation_id
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $curl = curl_init($this->config->aistudioUrl);
        curl_setopt_array($curl, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $this->config->timeoutSeconden,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $this->apiKey,
                'Content-Type: application/json',
                'Accept: application/json',
            ],
        ]);

        $antwoord = curl_exec($curl);
        $status   = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $curlFout = curl_error($curl);
        curl_close($curl);

        if ($antwoord === false) {
            throw new AgentOnbereikbaar("gateway niet bereikbaar: $curlFout");
        }

        $data = json_decode((string) $antwoord, true);
        if (! is_array($data)) {
            throw new AgentOnbereikbaar("gateway gaf geen JSON terug (HTTP $status)");
        }
        if ($status !== 200) {
            throw new AgentOnbereikbaar($this->leesFout($status, $data));
        }

        $bericht = $data['message'] ?? null;
        if (! is_array($bericht) || ! isset($bericht['content'])) {
            throw new AgentOnbereikbaar('antwoord van de gateway miste het message-veld');
        }

        return [
            'rauw' => (string) $bericht['content'],
            'verbruik' => [
                'input_tokens'  => (int) ($bericht['input_tokens'] ?? 0),
                'output_tokens' => (int) ($bericht['output_tokens'] ?? 0),
            ],
        ];
    }

    /** Maakt van de foutenvelop één leesbare regel voor het journal. */
    private function leesFout(int $status, array $data): string
    {
        // Een validatiefout van Laravel heeft geen error-envelop maar
        // {message, errors}. Zonder deze tak lees je alleen "http_422" terug.
        if ($status === 422 && isset($data['errors']) && is_array($data['errors'])) {
            $regels = [];
            foreach ($data['errors'] as $veld => $fouten) {
                $regels[] = $veld . ': ' . implode(' ', (array) $fouten);
            }
            return 'de gateway wees het verzoek af — ' . implode('; ', $regels);
        }

        $fout = $data['error'] ?? [];
        $code = (string) ($fout['code'] ?? "http_$status");

        $leesbaar = match ($code) {
            'provider_quota'       => 'het tegoed bij de provider is op',
            'provider_rate_limit'  => 'de provider limiteert het tempo',
            'schema_violation'     => 'het model hield zich niet aan het antwoordschema van de prompt',
            'provider_bad_request' => 'de gateway stuurde een verzoek dat de provider weigerde',
            default                => "de gateway gaf $code (HTTP $status)",
        };

        $extra = [];
        if (! empty($fout['model']))       { $extra[] = 'model ' . $fout['model']; }
        if (! empty($fout['retry_after'])) { $extra[] = 'opnieuw proberen over ' . $fout['retry_after'] . 's'; }
        if (! empty($fout['alternatives'])) {
            $extra[] = 'uitwijk mogelijk naar: ' . implode(', ', array_map(
                fn($a) => is_array($a) ? ($a['model'] ?? '?') : (string) $a,
                $fout['alternatives']
            ));
        }

        return $leesbaar . ($extra === [] ? '' : ' (' . implode('; ', $extra) . ')');
    }
}
