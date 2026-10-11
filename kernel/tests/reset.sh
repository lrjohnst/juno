#!/bin/bash
# Zet juno terug op tick 0.
#
# Raakt uitsluitend de staat en het werkstuk aan. Géén `git reset --hard`: dat
# gooit ook code weg die na de laatste tag is toegevoegd.
set -e

juno_root="/home/juno"

cat > "$juno_root/mission/STATE.json" <<'JSON'
{
    "tick": 0,
    "status": "idle",
    "last_outcome": null,
    "consecutive_failures": 0,
    "groen": 0,
    "totaal": 0,
    "bestanden": [],
    "updated_at": null
}
JSON

rm -f "$juno_root"/work/*
printf '# Journal\n\nAppend-only. Eén entry per tick, ook — juist — bij mislukking.\nEen teruggedraaide tick blijft hier staan; dat is wat voorkomt dat dezelfde fout zich herhaalt.\n\n---\n' \
  > "$juno_root/mission/JOURNAL.md"
printf 'tick,timestamp,outcome,groen,totaal,input_tokens,output_tokens,note\n' \
  > "$juno_root/mission/metrics.csv"

cat > "$juno_root/mission/BACKLOG.md" <<'MD'
# Backlog

Bovenste item is de eerstvolgende stap. juno onderhoudt deze lijst zelf.

## Open

1. Begin bij groep 1: laat `juno_eval` losse getallen teruggeven.

## Afgerond

_(nog niets)_
MD

rm -f "$juno_root/var/STOP"

git -C "$juno_root" add mission/ work/ >/dev/null 2>&1 || true
git -C "$juno_root" commit -q -m "test: reset naar tick 0" >/dev/null 2>&1 || true
echo "reset naar tick 0"
