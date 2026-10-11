#!/bin/bash
# A/B-test: heeft de meta-tekst ("fluff") in MISSION.md en CONSTITUTION.md
# invloed op wat juno schrijft?
#
# Afwisselend draaien (A,B,A,B,...) zodat drift aan de kant van de provider
# niet samenvalt met de variabele. Tien bundels per arm, vier strofen elk.
#
# Draaien als root:  sudo bash /home/lrjohnst/juno-experiment/ab-test.sh
set -u
exp="/home/lrjohnst/juno-experiment"
out="$exp/resultaten.csv"
runs_per_arm=10

printf 'run,arm,input_tokens,archetype_1,archetype_2,archetype_3,archetype_4\n' > "$out"

zet_variant() {
    # in-place schrijven: inode en eigendom blijven, anders kan juno niet lezen
    python3 - "$1" <<'PY'
import sys
arm = sys.argv[1]
exp = "/home/lrjohnst/juno-experiment"
for src, dst in [(f"{exp}/MISSION-{arm}.md",      "/home/juno/mission/MISSION.md"),
                 (f"{exp}/CONSTITUTION-{arm}.md", "/home/juno/kernel/CONSTITUTION.md")]:
    data = open(src).read()
    with open(dst, "w") as f:
        f.write(data)
PY
    bash /home/juno/reseal -y >/dev/null 2>&1
}

for run in $(seq 1 $((runs_per_arm * 2))); do
    if [ $((run % 2)) -eq 1 ]; then arm="zonder-fluff"; else arm="met-fluff"; fi

    zet_variant "$arm"
    sudo -u juno bash -c 'cd /home/juno && ./reset' >/dev/null 2>&1

    for tick in 1 2 3 4; do
        sudo -u juno php /home/juno/kernel/tick.php --model=mistral-small-latest >/dev/null 2>&1
    done

    # snapshot van deze bundel
    snap="$exp/bundels/$(printf '%02d' "$run")-$arm"
    mkdir -p "$snap"
    cp /home/juno/work/GEDICHT.md /home/juno/mission/STATE.json \
       /home/juno/mission/JOURNAL.md /home/juno/mission/metrics.csv "$snap/" 2>/dev/null

    python3 - "$run" "$arm" "$out" <<'PY'
import csv, json, sys
run, arm, out = sys.argv[1], sys.argv[2], sys.argv[3]
state = json.load(open("/home/juno/mission/STATE.json"))
arch = (state.get("used_archetypes", []) + ["", "", "", ""])[:4]
tokens = ""
with open("/home/juno/mission/metrics.csv") as f:
    rows = list(csv.DictReader(f))
    if rows:
        tokens = rows[0].get("input_tokens", "")
with open(out, "a", newline="") as f:
    csv.writer(f).writerow([run, arm, tokens] + arch)
print(f"  {run:>2}  {arm:<13} {tokens:>5}  {' | '.join(a for a in arch if a)}")
PY
done

chown -R lrjohnst:lrjohnst "$exp"
echo
echo "klaar — resultaten in $out"
