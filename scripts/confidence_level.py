#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Bayesian litigation-risk score for the ticket-resale platform.

Uses a Beta prior (strength K, mean p0) updated with observed successful
transactions and litigations, then returns the posterior probability that
the true litigation rate exceeds p_threshold.

Output (stdout, JSON): {"score": <float in [0, 1]>}

Usage:
    python3 confidence_level.py --successful 20 --litigations 1
"""

import argparse
import json
import sys

from scipy.stats import beta

K = 15
P0 = 0.01
P_THRESHOLD = 0.05


def compute_score(successful: int, litigations: int) -> float:
    successful = max(0, int(successful))
    litigations = max(0, int(litigations))

    n = successful + litigations
    i = litigations

    alpha_post = K * P0 + i
    beta_post = K * (1 - P0) + n - i

    cdf = beta.cdf(P_THRESHOLD, alpha_post, beta_post)
    score = 1.0 - cdf
    return max(0.0, min(1.0, float(score)))


def main(argv=None):
    parser = argparse.ArgumentParser()
    parser.add_argument("--successful", type=int, required=True)
    parser.add_argument("--litigations", type=int, required=True)
    args = parser.parse_args(argv)

    score = compute_score(args.successful, args.litigations)
    sys.stdout.write(json.dumps({"score": score}))
    return 0


if __name__ == "__main__":
    sys.exit(main())
