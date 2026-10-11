#!/bin/bash
# Draait de bundel omgekeerd: nacht -> ochtend.
#
# Vraag: zit de zwaarte vast aan het TIJDSTIP (nacht blijft duister, ook als
# hij vooraan staat) of aan de POSITIE (de laatste strofe wordt duister, welk
# tijdstip daar ook staat)?
#
# Vergt twee wijzigingen, die dit script achteraf allebei terugdraait:
#   - config.php: 'moments' omgekeerd, want de gate eist een niet-dalende index
#   - MISSION.md: eis 3 omgekeerd
#
# Draaien als root:  sudo bash /home/lrjohnst/juno-experiment/omgekeerd-test.sh
set -u
exp="/home/lrjohnst/juno-experiment"
out="$exp/resultaten-omgekeerd.csv"
model="mistral-small-latest"
bundels=10

cp -p /home/juno/kernel/config.php "$exp/.config.php.bak"
cp -p /home/juno/mission/MISSION.md "$exp/.MISSION.md.bak"

herstel() {
    python3 -c "
data = open('$exp/.config.php.bak').read()
open('/home/juno/kernel/config.php','w').write(data)
data = open('$exp/.MISSION.md.bak').read()
open('/home/juno/mission/MISSION.md','w').write(data)
"
    sed -i "s/de bundel loopt van nacht naar ochtend/de bundel loopt van ochtend naar nacht/" /home/juno/kernel/Gates.php
    bash /home/juno/reseal -y >/dev/null 2>&1
    rm -f "$exp/.config.php.bak" "$exp/.MISSION.md.bak"
    echo "kernel en missie teruggezet"
}
trap herstel EXIT

# omkeren
sed -i "s/'moments' => \['ochtend', 'middag', 'avond', 'nacht'\]/'moments' => ['nacht', 'avond', 'middag', 'ochtend']/" /home/juno/kernel/config.php
sed -i "s/de bundel loopt van ochtend naar nacht/de bundel loopt van nacht naar ochtend/" /home/juno/kernel/Gates.php
python3 -c "
data = open('$exp/MISSION-omgekeerd.md').read()
open('/home/juno/mission/MISSION.md','w').write(data)
"
bash /home/juno/reseal -y >/dev/null 2>&1
grep -q "'nacht', 'avond'" /home/juno/kernel/config.php || { echo "omkeren mislukt" >&2; exit 1; }
echo "omgekeerd: $(grep "'moments'" /home/juno/kernel/config.php | sed 's/^ *//')"
echo

printf 'run,model,archetype_1_nacht,archetype_2_avond,archetype_3_middag,archetype_4_ochtend\n' > "$out"

for run in $(seq 1 $bundels); do
    sudo -u juno bash -c 'cd /home/juno && ./reset' >/dev/null 2>&1
    for tick in 1 2 3 4; do
        sudo -u juno php /home/juno/kernel/tick.php --model="$model" >/dev/null 2>&1
    done
    snap="$exp/bundels-omgekeerd/$(printf '%02d' "$run")"
    mkdir -p "$snap"
    cp /home/juno/work/GEDICHT.md /home/juno/mission/STATE.json \
       /home/juno/mission/JOURNAL.md /home/juno/mission/metrics.csv "$snap/" 2>/dev/null
    python3 - "$run" "$model" "$out" <<'PY'
import csv, json, sys
run, model, out = sys.argv[1:4]
arch = (json.load(open("/home/juno/mission/STATE.json")).get("used_archetypes", []) + [""]*4)[:4]
with open(out, "a", newline="") as f:
    csv.writer(f).writerow([run, model] + arch)
print(f"  {run:>2}  {' | '.join(a for a in arch if a)}")
PY
done

chown -R lrjohnst:lrjohnst "$exp"
echo; echo "klaar — $out"
