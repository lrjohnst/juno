#!/bin/bash
# Tien bundels op gpt-5.5, voorbeelden-variant A, normale volgorde.
# Vergelijkbaar met de A-small / A-medium / A-large reeksen.
#
# OpenAI kent strakke rate limits, dus: pauze tussen ticks, en een tick die
# UNAVAILABLE geeft wordt opnieuw geprobeerd in plaats van overgeslagen —
# anders lijkt een rate limit op een afgekeurde strofe in de resultaten.
set -u
exp="/home/lrjohnst/juno-experiment"
out="$exp/resultaten-gpt.csv"
model="gpt-5.5"
pauze=4

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

printf 'run,model,input_tokens,output_tokens,accepted,rejected,ratelimits,archetype_1,archetype_2,archetype_3,archetype_4\n' > "$out"

for run in $(seq 1 10); do
    sudo -u juno bash -c 'cd /home/juno && ./reset' >/dev/null 2>&1
    ratelimits=0
    for tick in 1 2 3 4; do
        for poging in 1 2 3 4 5; do
            uitvoer=$(sudo -u juno php /home/juno/kernel/tick.php --model="$model" 2>&1)
            if echo "$uitvoer" | grep -q "UNAVAILABLE"; then
                ratelimits=$((ratelimits + 1))
                sleep 15
            else
                break
            fi
        done
        sleep "$pauze"
    done
    snap="$exp/bundels-gpt/$(printf '%02d' "$run")"
    mkdir -p "$snap"
    cp /home/juno/work/GEDICHT.md /home/juno/mission/STATE.json \
       /home/juno/mission/JOURNAL.md /home/juno/mission/metrics.csv "$snap/" 2>/dev/null
    python3 - "$run" "$model" "$out" "$ratelimits" <<'PY'
import csv, json, sys
run, model, out, rl = sys.argv[1:5]
arch = (json.load(open("/home/juno/mission/STATE.json")).get("used_archetypes", []) + [""]*4)[:4]
rows = list(csv.DictReader(open("/home/juno/mission/metrics.csv")))
acc = sum(1 for r in rows if r["outcome"] == "ACCEPTED")
rej = sum(1 for r in rows if r["outcome"] == "REJECTED")
tin = rows[0].get("input_tokens", "") if rows else ""
tout = sum(int(r.get("output_tokens") or 0) for r in rows)
with open(out, "a", newline="") as f:
    csv.writer(f).writerow([run, model, tin, tout, acc, rej, rl] + arch)
print(f"  {run:>2}  {acc}/4  rl={rl}  {' | '.join(a for a in arch if a)}")
PY
done
chown -R lrjohnst:lrjohnst "$exp"
echo; echo "klaar — $out"
