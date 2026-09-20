#!/bin/bash
# Tests die juno zelf niet kan uitvoeren, juist omdat hij de rechten mist.
# Draaien als root:  sudo bash /home/juno/kernel/tests/run-as-root.sh
set -u
M=/home/juno/mission; K=/home/juno/kernel
pass=0; fail=0
ok(){ echo "  ✓ $1"; pass=$((pass+1)); }
nok(){ echo "  ✗ $1"; fail=$((fail+1)); }

echo "anker-controle"
cp -p "$M/MISSION.md" /root/.mission.bak
printf '\n' >> "$M/MISSION.md"
sudo -u juno php "$K/tick.php" --dry-run 2>&1 | grep -q "anker is verplaatst" \
  && ok "gewijzigde missie wordt betrapt" || nok "wijziging kwam er doorheen"
cp -p /root/.mission.bak "$M/MISSION.md"; chown root:juno "$M/MISSION.md"; rm -f /root/.mission.bak
sudo -u juno php "$K/tick.php" --dry-run >/dev/null 2>&1 \
  && ok "na herstel draait de tick weer" || nok "tick bleef geblokkeerd"

echo "rechten"
sudo -u juno test -w "$K" && nok "juno kan in kernel/ schrijven" || ok "kernel/ is dicht voor juno"
sudo -u juno test -w "$M/MISSION.md" && nok "juno kan de missie wijzigen" || ok "missie is dicht voor juno"
sudo -u juno rm -f "$M/MISSION.md" 2>/dev/null && nok "juno kon de missie verwijderen" || ok "missie kan niet verwijderd worden"

sudo -u juno bash "$K/tests/reset.sh" >/dev/null
echo; echo "── $pass geslaagd, $fail mislukt ──"
exit $(( fail > 0 ? 1 : 0 ))
