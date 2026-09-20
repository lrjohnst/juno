#!/bin/bash
# Kernel-testsuite. Draait de hele lus zonder één API-aanroep en dus zonder kosten.
# Voer uit als juno:  sudo -u juno bash /home/juno/kernel/tests/run.sh
set -uo pipefail
K=/home/juno/kernel; M=/home/juno/mission; W=/home/juno/work; V=/home/juno/var
pass=0; fail=0
ok()   { echo "  ✓ $1"; pass=$((pass+1)); }
nok()  { echo "  ✗ $1"; fail=$((fail+1)); }
tick() { php "$K/tick.php" --dry-run "$@" 2>&1; }

bash "$K/tests/reset.sh" >/dev/null

echo "1. volledige cyclus tot de bundel af is"
for i in 1 2 3 4; do tick >/dev/null; done
[ "$(grep -c ACCEPTED "$M/metrics.csv")" = "4" ] && ok "vier strofen geaccepteerd" || nok "verwachtte 4 accepted"
tick | grep -q "compleet" && ok "vijfde tick weigert" || nok "vijfde tick had moeten weigeren"

echo "2. afkeuring en rollback"
bash "$K/tests/reset.sh" >/dev/null; tick >/dev/null
before=$(md5sum "$W/GEDICHT.md" | cut -d' ' -f1)
tick --mode=gate-fail >/dev/null
[ "$before" = "$(md5sum "$W/GEDICHT.md" | cut -d' ' -f1)" ] && ok "werkstuk teruggedraaid" || nok "rollback mislukt"
grep -q REJECTED "$M/JOURNAL.md" && ok "journal bewaart de mislukking" || nok "journal miste de mislukking"
grep -q REJECTED "$M/metrics.csv" && ok "metrics bewaart de mislukking" || nok "metrics miste de mislukking"

echo "3. onleesbaar antwoord"
tick --mode=invalid-json | grep -q "onleesbaar" && ok "ongeldige JSON afgevangen" || nok "ongeldige JSON niet afgevangen"

echo "4. escalatie"
bash "$K/tests/reset.sh" >/dev/null; tick >/dev/null
for i in 1 2 3; do tick --mode=gate-fail >/dev/null; done
tick | grep -q "vraagt om Lucas" && ok "loop escaleert na herhaald falen" || nok "escalatie bleef uit"

echo "5. veiligheidskleppen"
bash "$K/tests/reset.sh" >/dev/null
tick >/dev/null & tick >/dev/null & wait
[ "$(grep -c ACCEPTED "$M/metrics.csv")" = "1" ] && ok "lock laat één tick door" || nok "lock hield niet"
touch "$V/STOP"; tick | grep -q "STOP" && ok "kill switch werkt" || nok "kill switch faalde"; rm -f "$V/STOP"
printf '\n' >> "$M/MISSION.md" 2>/dev/null || sudo -n true
if tick 2>&1 | grep -q "anker is verplaatst"; then ok "missie-hash betrapt wijziging"; else nok "missie-hash liet wijziging door"; fi
truncate -s -1 "$M/MISSION.md" 2>/dev/null || true

bash "$K/tests/reset.sh" >/dev/null
echo
echo "── $pass geslaagd, $fail mislukt ──"
exit $(( fail > 0 ? 1 : 0 ))
