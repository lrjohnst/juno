#!/bin/bash
# Zet juno terug op tick 0.
#
# Raakt uitsluitend de state-bestanden aan. Géén `git reset --hard`: dat gooit
# ook code weg die na de laatste tag is toegevoegd — zo is de ./tick-shortcut
# een keer verdwenen. Een state-reset hoort geen code te kunnen slopen.
set -e

juno_root="/home/juno"

cat > "$juno_root/mission/STATE.json" <<'JSON'
{
    "tick": 0,
    "status": "idle",
    "last_outcome": null,
    "consecutive_failures": 0,
    "stanzas": 0,
    "target_stanzas": 4,
    "used_archetypes": [],
    "last_moment_index": -1,
    "updated_at": null
}
JSON

printf '# (nog naamloos)\n\n' > "$juno_root/work/GEDICHT.md"
printf '# Journal\n\nAppend-only. Eén entry per tick, ook — juist — bij mislukking.\nEen teruggedraaide tick blijft hier staan; dat is wat voorkomt dat dezelfde fout zich herhaalt.\n\n---\n' \
  > "$juno_root/mission/JOURNAL.md"
printf 'tick,timestamp,outcome,stanzas,words,judge_score,input_tokens,output_tokens,note\n' \
  > "$juno_root/mission/metrics.csv"

cat > "$juno_root/mission/BACKLOG.md" <<'MD'
# Backlog

Bovenste item is de eerstvolgende stap. juno onderhoudt deze lijst zelf.

## Open

1. Schrijf strofe 1: zet het eerste archetype neer, vroeg op de dag.

## Afgerond

_(nog niets)_
MD

rm -f "$juno_root/var/STOP"

git -C "$juno_root" add mission/ work/ >/dev/null 2>&1 || true
git -C "$juno_root" commit -q -m "test: reset naar tick 0" >/dev/null 2>&1 || true
echo "reset naar tick 0"
