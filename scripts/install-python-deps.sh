#!/usr/bin/env bash
set -euo pipefail

PYTHON="${RESALE_PYTHON_BIN:-python3}"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

echo "Using Python: $($PYTHON --version)"
"$PYTHON" -m pip install --upgrade pip
"$PYTHON" -m pip install -r "$SCRIPT_DIR/requirements.txt"
echo "Installed dependencies from $SCRIPT_DIR/requirements.txt"
