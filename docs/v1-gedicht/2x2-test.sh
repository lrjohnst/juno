#!/bin/bash
# 2x2: sturen de voorbeelden in de missie wat de agent kiest, en is een groter
# model daar minder gevoelig voor?
#
#   voorbeelden A = moeder / lege winkelstraat in de ochtend / afgesneden in een bocht
#   voorbeelden B = volle wachtkamer / balanceren in de bus / te hoge kast
#
# Cyclisch door de vier cellen zodat provider-drift niet met een cel samenvalt.
# Draaien als root:  sudo bash /home/lrjohnst/juno-experiment/2x2-test.sh
set -u
exp="/home/lrjohnst/juno-experiment"
out="$exp/resultaten-2x2.csv"
herhalingen=5

printf 'run,voorbeelden,model,input_tokens,archetype_1,archetype_2,archetype_3,archetype_4\n' > "$out"

zet_missie() {
    python3 - "$1" <<'PY'
import sys
variant = sys.argv[1]
src = f"/home/lrjohnst/juno-experiment/MISSION-voorbeelden-{variant}.md"
data = open(src).read()
with open("/home/juno/mission/MISSION.md", "w") as f:   # in-place: eigendom blijft
    f.write(data)
PY
    bash /home/juno/reseal -y >/dev/null 2>&1
}

run=0
for ronde in $(seq 1 $herhalingen); do
  for cel in "A mistral-small-latest" "A mistral-medium-latest" \
             "B mistral-small-latest" "B mistral-medium-latest"; do
    set -- $cel; variant="$1"; model="$2"
    run=$((run + 1))

    zet_missie "$variant"
    sudo -u juno bash -c 'cd /home/juno && ./reset' >/dev/null 2>&1
    for tick in 1 2 3 4; do
        sudo -u juno php /home/juno/kernel/tick.php --model="$model" >/dev/null 2>&1
    done

    snap="$exp/bundels-2x2/$(printf '%02d' "$run")-${variant}-${model%%-latest}"
    mkdir -p "$snap"
    cp /home/juno/work/GEDICHT.md /home/juno/mission/STATE.json \
       /home/juno/mission/JOURNAL.md /home/juno/mission/metrics.csv "$snap/" 2>/dev/null

    python3 - "$run" "$variant" "$model" "$out" <<'PY'
import csv, json, sys
run, variant, model, out = sys.argv[1:5]
state = json.load(open("/home/juno/mission/STATE.json"))
arch = (state.get("used_archetypes", []) + ["", "", "", ""])[:4]
tokens = ""
rows = list(csv.DictReader(open("/home/juno/mission/metrics.csv")))
if rows: tokens = rows[0].get("input_tokens", "")
with open(out, "a", newline="") as f:
    csv.writer(f).writerow([run, variant, model, tokens] + arch)
print(f"  {run:>2}  {variant}  {model.replace('-latest',''):<15} {' | '.join(a for a in arch if a)}")
PY
  done
done

chown -R lrjohnst:lrjohnst "$exp"
echo; echo "klaar — $out"
