#!/bin/bash
# Kernel-testsuite. Draait de hele lus zonder één API-aanroep en dus zonder kosten.
# Voer uit als juno:  sudo -u juno bash /home/juno/kernel/tests/run.sh
set -u   # geen pipefail: de kernel gebruikt exit 2 voor 'bewust overgeslagen',
         # en dat mag een pipe met grep niet als mislukking lezen

kernel_dir="/home/juno/kernel"
mission_dir="/home/juno/mission"
work_dir="/home/juno/work"
var_dir="/home/juno/var"

passed_count=0
failed_count=0

pass_test() { echo "  ✓ $1"; passed_count=$((passed_count + 1)); }
fail_test() { echo "  ✗ $1"; failed_count=$((failed_count + 1)); }
run_tick()  { php "$kernel_dir/tick.php" --dry-run "$@" 2>&1; }

bash "$kernel_dir/tests/reset.sh" >/dev/null

echo "1. volledige cyclus tot de bundel af is"
for stanza in 1 2 3 4; do run_tick >/dev/null; done
accepted_count=$(grep -c ACCEPTED "$mission_dir/metrics.csv")
[ "$accepted_count" = "4" ] \
  && pass_test "vier strofen geaccepteerd" \
  || fail_test "verwachtte 4 accepted, kreeg $accepted_count"
run_tick | grep -q "compleet" \
  && pass_test "vijfde tick weigert" \
  || fail_test "vijfde tick had moeten weigeren"

echo "2. afkeuring en rollback"
bash "$kernel_dir/tests/reset.sh" >/dev/null
run_tick >/dev/null
poem_hash_before=$(md5sum "$work_dir/GEDICHT.md" | cut -d' ' -f1)
run_tick --mode=gate-fail >/dev/null
poem_hash_after=$(md5sum "$work_dir/GEDICHT.md" | cut -d' ' -f1)
[ "$poem_hash_before" = "$poem_hash_after" ] \
  && pass_test "werkstuk teruggedraaid" \
  || fail_test "rollback mislukt"
grep -q REJECTED "$mission_dir/JOURNAL.md" \
  && pass_test "journal bewaart de mislukking" \
  || fail_test "journal miste de mislukking"
grep -q REJECTED "$mission_dir/metrics.csv" \
  && pass_test "metrics bewaart de mislukking" \
  || fail_test "metrics miste de mislukking"

echo "3. onleesbaar antwoord"
run_tick --mode=invalid-json | grep -q "onleesbaar" \
  && pass_test "ongeldige JSON afgevangen" \
  || fail_test "ongeldige JSON niet afgevangen"

echo "4. escalatie"
bash "$kernel_dir/tests/reset.sh" >/dev/null
run_tick >/dev/null
for failure in 1 2 3; do run_tick --mode=gate-fail >/dev/null; done
run_tick | grep -q "vraagt om Lucas" \
  && pass_test "loop escaleert na herhaald falen" \
  || fail_test "escalatie bleef uit"

echo "5. veiligheidskleppen"
bash "$kernel_dir/tests/reset.sh" >/dev/null
run_tick >/dev/null & run_tick >/dev/null & wait
concurrent_accepted=$(grep -c ACCEPTED "$mission_dir/metrics.csv")
[ "$concurrent_accepted" = "1" ] \
  && pass_test "lock laat één tick door" \
  || fail_test "lock hield niet: $concurrent_accepted ticks"
touch "$var_dir/STOP"
run_tick | grep -q "STOP" \
  && pass_test "kill switch werkt" \
  || fail_test "kill switch faalde"
rm -f "$var_dir/STOP"

# De missie-hash-test staat in tests/run-as-root.sh: juno mag MISSION.md niet
# wijzigen, dus hij kan zijn eigen anker-controle niet uitlokken. Dat is het punt.

bash "$kernel_dir/tests/reset.sh" >/dev/null
echo
echo "── $passed_count geslaagd, $failed_count mislukt ──"
exit $(( failed_count > 0 ? 1 : 0 ))
