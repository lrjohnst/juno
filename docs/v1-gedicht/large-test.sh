#!/bin/bash
# Tien bundels op mistral-large, voorbeelden-variant A, normale volgorde.
# Direct vergelijkbaar met de A-small en A-medium cellen uit het 2x2.
set -u
exp="/home/lrjohnst/juno-experiment"
out="$exp/resultaten-large.csv"
model="mistral-large-latest"

cp -p /home/juno/mission/MISSION.md "$exp/.MISSION.bak"
herstel() {
    python3 -c "
data=open('$exp/.MISSION.bak').read(); open('/home/juno/mission/MISSION.md','w').write(data)"
    bash /home/juno/reseal -y >/dev/null 2>&1
    rm -f "$exp/.MISSION.bak"
    echo "missie teruggezet"
}
trap herstel EXIT

python3 -c "
data=open('$exp/MISSION-voorbeelden-A.md').read(); open('/home/juno/mission/MISSION.md','w').write(data)"
bash /home/juno/reseal -y >/dev/null 2>&1

printf 'run,model,input_tokens,output_tokens,accepted,rejected,archetype_1,archetype_2,archetype_3,archetype_4\n' > "$out"

for run in $(seq 1 10); do
    sudo -u juno bash -c 'cd /home/juno && ./reset' >/dev/null 2>&1
    for tick in 1 2 3 4; do
        sudo -u juno php /home/juno/kernel/tick.php --model="$model" >/dev/null 2>&1
    done
    snap="$exp/bundels-large/$(printf '%02d' "$run")"
    mkdir -p "$snap"
    cp /home/juno/work/GEDICHT.md /home/juno/mission/STATE.json \
       /home/juno/mission/JOURNAL.md /home/juno/mission/metrics.csv "$snap/" 2>/dev/null
    python3 - "$run" "$model" "$out" <<'PY'
import csv, json, sys
run, model, out = sys.argv[1:4]
arch = (json.load(open("/home/juno/mission/STATE.json")).get("used_archetypes", []) + [""]*4)[:4]
rows = list(csv.DictReader(open("/home/juno/mission/metrics.csv")))
acc = sum(1 for r in rows if r["outcome"] == "ACCEPTED")
rej = sum(1 for r in rows if r["outcome"] == "REJECTED")
tin = rows[0].get("input_tokens", "") if rows else ""
tout = sum(int(r.get("output_tokens") or 0) for r in rows)
with open(out, "a", newline="") as f:
    csv.writer(f).writerow([run, model, tin, tout, acc, rej] + arch)
print(f"  {run:>2}  {acc}/4  {' | '.join(a for a in arch if a)}")
PY
done
chown -R lrjohnst:lrjohnst "$exp"
echo; echo "klaar — $out"
