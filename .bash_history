tick --dry-run
ll
./tick --dry-run
./reset
./tick --dry-run --mode=gate-fail
./tick --dry-run --mode=invalid-json
./tick --dry-run --mode=gate-fail
./reset
./tick
