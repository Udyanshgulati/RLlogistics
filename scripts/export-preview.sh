#!/usr/bin/env bash
# Builds the static preview into /out with Next's assets moved under /rl (the preview host reserves "_" paths).
set -euo pipefail
cd "$(dirname "$0")/.."
STATIC_EXPORT=1 npx next build
mkdir -p out/rl
mv out/_next out/rl/_next
sed -i 's|<title>[^<]*</title>|<title>RL Logistics Website</title>|' out/index.html
python3 - <<'PY'
import os, json, glob
# The preview host rejects a literal U+FFFD; in bundled JS it only appears inside string literals.
for f in glob.glob("out/rl/_next/**/*.js", recursive=True):
    src = open(f, encoding="utf-8").read()
    if "\ufffd" in src:
        open(f, "w", encoding="utf-8").write(src.replace("\ufffd", "\\ufffd"))
files = []
for root, _, fs in os.walk("out"):
    for f in fs:
        p = os.path.relpath(os.path.join(root, f), "out")
        if p == "index.html": continue
        e = {"path": p}
        files.append(e)
json.dump(files, open("out/.files.json", "w"))
print(len(files), "files")
PY
