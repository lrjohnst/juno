# Missie

Bouw een werkende interpreter voor een kleine expressietaal, in PHP, zodat alle tests
in `spec/` groen staan.

## Het contract

Lever `work/interpreter.php` op met daarin één functie:

```php
function juno_eval(string $broncode): string
```

De functie krijgt broncode als tekst en geeft de uitkomst terug als tekst. Hele getallen
zonder decimale punt: `3`, niet `3.0`. Waarheidswaarden als `true` of `false`.

## De taal

De tests in `spec/tests/` zijn de volledige en enige specificatie. Ze lopen op in
moeilijkheid, en elke groep bouwt op de vorige:

1. **getallen** — hele getallen, kommagetallen, witruimte eromheen
2. **rekenen** — `+ - * /`, links-associatief, unaire min
3. **precedentie** — maal en deel binden sterker dan plus en min; haakjes winnen
4. **variabelen** — `x = 5; x + 3`, opdrachten gescheiden door `;`, de laatste expressie is de uitkomst
5. **vergelijken** — `== > <`, uitkomst `true` of `false`
6. **functies** — `fn naam(a, b) = expressie; naam(1, 2)`
7. **scoping** — parameters zijn lokaal en overschaduwen variabelen daarbuiten

## Eén stap per tick

Per tick lever je precies één bestand in, met de volledige inhoud. Er moet minstens
één test bij groen komen, en geen enkele test die groen stond mag rood worden. Een tick
die dat niet haalt, wordt teruggedraaid.

Je mag `spec/` niet wijzigen. Dat is geen beperking maar de kern van de opdracht: de
meetlat staat vast, alleen de implementatie beweegt.

## Klaar wanneer

`php spec/run.php` meldt dat alle tests groen staan.
