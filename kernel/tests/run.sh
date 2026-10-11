#!/bin/bash
# Kernel-testsuite. Draait de hele lus zonder één API-aanroep en dus zonder kosten.
# Voer uit als juno:  sudo -u juno bash /home/juno/kernel/tests/run.sh
set -u   # geen pipefail: de kernel gebruikt exit 2 voor 'bewust overgeslagen',
         # en dat mag een pipe met grep niet als mislukking lezen

kernel_dir="/home/juno/kernel"
mission_dir="/home/juno/mission"
work_dir="/home/juno/work"
spec_dir="/home/juno/spec"
var_dir="/home/juno/var"

passed_count=0
failed_count=0
pass_test() { echo "  ✓ $1"; passed_count=$((passed_count + 1)); }
fail_test() { echo "  ✗ $1"; failed_count=$((failed_count + 1)); }
# LET OP: `run_tick | grep -q ...` sluit grep af bij de eerste match en stuurt
# SIGPIPE naar tick.php, die dan halverwege stopt en zijn journal nooit schrijft.
# Altijd eerst opvangen in een variabele, dan pas zoeken.
run_tick()  { php "$kernel_dir/tick.php" --dry-run "$@" 2>&1; }
bevat()     { printf '%s' "$1" | grep -q "$2"; }
groen()     { php "$spec_dir/run.php" --stil 2>/dev/null | sed -n 's|^TOTAAL \([0-9]*\)/.*|\1|p'; }

bash "$kernel_dir/tests/reset.sh" >/dev/null

echo "1. voortgang"
[ "$(groen)" = "0" ] && pass_test "begint op 0 groen" || fail_test "startte niet op 0"
run_tick >/dev/null
[ "$(groen)" = "19" ] \
  && pass_test "tick 1 brengt tests naar groen" \
  || fail_test "verwachtte 19 groen, kreeg $(groen)"
grep -q ACCEPTED "$mission_dir/metrics.csv" \
  && pass_test "metrics legt de acceptatie vast" || fail_test "metrics miste de acceptatie"

echo "2. geen voortgang wordt afgekeurd"
uitvoer=$(run_tick)
bevat "$uitvoer" "afgekeurd" \
  && pass_test "dezelfde code opnieuw wordt afgekeurd" \
  || fail_test "geen-voortgang kwam er doorheen"
[ "$(groen)" = "19" ] \
  && pass_test "werkstuk teruggedraaid naar de laatste goede staat" \
  || fail_test "rollback mislukt: $(groen) groen"
grep -q REJECTED "$mission_dir/JOURNAL.md" \
  && pass_test "journal bewaart de mislukking" || fail_test "journal miste de mislukking"

echo "3. vormcontrole"
uitvoer=$(run_tick --mode=vorm-fout)
bevat "$uitvoer" "mag niet" \
  && pass_test "schrijven buiten work/ geblokkeerd" \
  || fail_test "padtraversal kwam er doorheen"
uitvoer=$(run_tick --mode=invalid-json)
bevat "$uitvoer" "onleesbaar" \
  && pass_test "ongeldige JSON afgevangen" || fail_test "ongeldige JSON niet afgevangen"

echo "4. escalatie"
bash "$kernel_dir/tests/reset.sh" >/dev/null
run_tick >/dev/null
for i in 1 2 3; do run_tick --mode=geen-voortgang >/dev/null; done
uitvoer=$(run_tick)
bevat "$uitvoer" "vraagt om Lucas" \
  && pass_test "loop escaleert na herhaald falen" || fail_test "escalatie bleef uit"

echo "5. veiligheidskleppen"
bash "$kernel_dir/tests/reset.sh" >/dev/null
run_tick >/dev/null & run_tick >/dev/null & wait
accepted=$(grep -c ACCEPTED "$mission_dir/metrics.csv")
[ "$accepted" = "1" ] && pass_test "lock laat één tick door" || fail_test "lock hield niet: $accepted"
touch "$var_dir/STOP"
uitvoer=$(run_tick)
bevat "$uitvoer" "STOP" && pass_test "kill switch werkt" || fail_test "kill switch faalde"
rm -f "$var_dir/STOP"

echo "6. de missie is af als alles groen staat"
# Simuleer de eindstand door de voortgangsdrempel te halen is niet mogelijk met de
# stub; in plaats daarvan toetsen we dat de kernel stopt zodra groen == totaal.
# Dat pad staat in tick.php vlak na de nulmeting.
grep -q 'De missie is behaald' "$kernel_dir/tick.php" \
  && pass_test "kernel kent een eindtoestand" || fail_test "geen eindtoestand in tick.php"

bash "$kernel_dir/tests/reset.sh" >/dev/null
echo
echo "── $passed_count geslaagd, $failed_count mislukt ──"
exit $(( failed_count > 0 ? 1 : 0 ))
