#!/usr/bin/env python3
"""Compare Python script output with expected scipy values."""

import json
import subprocess
import sys
from pathlib import Path

from scipy.stats import beta

K = 15
P0 = 0.01
P_THRESHOLD = 0.05

SCRIPT = Path(__file__).with_name("confidence_level.py")
PYTHON = sys.executable

cases = [(0, 0), (20, 1), (100, 5), (5, 0)]


def expected(successful: int, litigations: int) -> float:
    n = successful + litigations
    i = litigations
    alpha_post = K * P0 + i
    beta_post = K * (1 - P0) + n - i
    return max(0.0, min(1.0, 1.0 - beta.cdf(P_THRESHOLD, alpha_post, beta_post)))


def main() -> int:
    ok = True
    for successful, litigations in cases:
        proc = subprocess.run(
            [PYTHON, str(SCRIPT), "--successful", str(successful), "--litigations", str(litigations)],
            capture_output=True,
            text=True,
            check=True,
        )
        score = json.loads(proc.stdout.strip())["score"]
        ref = expected(successful, litigations)
        if abs(score - ref) > 1e-9:
            print(f"FAIL successful={successful} litigations={litigations} score={score} ref={ref}")
            ok = False
        else:
            print(f"OK   successful={successful} litigations={litigations} score={score}")
    return 0 if ok else 1


if __name__ == "__main__":
    raise SystemExit(main())
