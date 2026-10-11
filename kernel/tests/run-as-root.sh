#!/bin/bash
# Tests die juno zelf niet kan uitvoeren, juist omdat hij de rechten mist.
# Draaien als root:  sudo bash /home/juno/kernel/tests/run-as-root.sh
set -u

kernel_dir="/home/juno/kernel"
mission_dir="/home/juno/mission"
spec_dir="/home/juno/spec"
backup="/root/.juno-mission.bak"

passed_count=0
failed_count=0
pass_test() { echo "  ✓ $1"; passed_count=$((passed_count + 1)); }
fail_test() { echo "  ✗ $1"; failed_count=$((failed_count + 1)); }

echo "anker-controle"
cp -p "$mission_dir/MISSION.md" "$backup"
printf '\n' >> "$mission_dir/MISSION.md"
sudo -u juno php "$kernel_dir/tick.php" --dry-run 2>&1 | grep -q "anker is verplaatst" \
  && pass_test "gewijzigde missie wordt betrapt" || fail_test "wijziging kwam er doorheen"
cp -p "$backup" "$mission_dir/MISSION.md"
chown root:juno "$mission_dir/MISSION.md"; rm -f "$backup"
sudo -u juno php "$kernel_dir/tick.php" --dry-run >/dev/null 2>&1 \
  && pass_test "na herstel draait de tick weer" || fail_test "tick bleef geblokkeerd"

echo "rechten"
# `test -w` is onbruikbaar bij ACL's: access(2) kijkt naar de mask en meldt
# schrijfbaar terwijl de echte operatie wordt geweigerd. Dus echt proberen.
sudo -u juno touch "$kernel_dir/.indringer" 2>/dev/null \
  && { fail_test "juno kan in kernel/ schrijven"; rm -f "$kernel_dir/.indringer"; } \
  || pass_test "kernel/ is dicht voor juno"
sudo -u juno touch "$spec_dir/.indringer" 2>/dev/null \
  && { fail_test "juno kan in spec/ schrijven"; rm -f "$spec_dir/.indringer"; } \
  || pass_test "spec/ is dicht voor juno — de meetlat staat vast"
sudo -u juno bash -c "echo x >> $spec_dir/tests/01-getallen.php" 2>/dev/null \
  && fail_test "juno kan een test wijzigen" || pass_test "tests zijn niet te wijzigen"
sudo -u juno bash -c "echo x >> $mission_dir/MISSION.md" 2>/dev/null \
  && fail_test "juno kan de missie wijzigen" || pass_test "missie is dicht voor juno"
sudo -u juno rm -f "$mission_dir/MISSION.md" 2>/dev/null \
  && fail_test "juno kon de missie verwijderen" || pass_test "missie kan niet verwijderd worden"

sudo -u juno bash "$kernel_dir/tests/reset.sh" >/dev/null
echo
echo "── $passed_count geslaagd, $failed_count mislukt ──"
exit $(( failed_count > 0 ? 1 : 0 ))
