#!/bin/bash
# Runs the first-time/upgrade setup in the background, then hands over to the official WordPress entrypoint.
set -euo pipefail
/opt/schermo/setup.sh &
exec docker-entrypoint.sh "$@"
