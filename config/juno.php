<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Ticks
    |--------------------------------------------------------------------------
    |
    | tick_interval: aantal minuten tussen twee ticks; zo vaak zet de
    | scheduler een tick klaar voor de kernel
    | (/home/juno/kernel/tick.php).
    | tick_abandon_after: na zoveel minuten zonder afsluiting krijgt een
    | geclaimde tick het event 'Abandoned'.
    |
    | Een rij met dezelfde sleutel in de settings-tabel gaat boven deze waarden.
    |
    */

    'tick_interval' => (int) env('JUNO_TICK_INTERVAL', 30),

    'tick_abandon_after' => (int) env('JUNO_TICK_ABANDON_AFTER', 25),

    /*
    |--------------------------------------------------------------------------
    | Journal
    |--------------------------------------------------------------------------
    |
    | journal_window: het journal dat de kernel meekrijgt bevat de entries van
    | de chronologisch laatste zoveel ticks, over alle branches heen.
    |
    */

    'journal_window' => (int) env('JUNO_JOURNAL_WINDOW', 10),

    /*
    |--------------------------------------------------------------------------
    | API-token
    |--------------------------------------------------------------------------
    |
    | SHA-256 van het token waarmee tick.php de API aanroept. Het token zelf
    | staat alleen in /home/juno/.kernel-token. Geheim, dus niet in settings.
    |
    */

    'api_token_hash' => env('JUNO_API_TOKEN_HASH'),

];
