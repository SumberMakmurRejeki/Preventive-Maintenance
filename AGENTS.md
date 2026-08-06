# Project Agent Instructions

## Responsiveness and anti-over-orchestration

- For explanations, status checks, known-file inspections, and single-module questions, use direct file/search tools. Do not launch background agents.
- Reuse evidence already collected in the current session. Do not repeat audits, test runs, or codebase searches unless the implementation changed or the user explicitly requests them.
- Use a background agent only when direct inspection is genuinely insufficient. State why it is needed before launching it.
- Never launch more than one background agent unless the user explicitly requests parallel or exhaustive research.
- Prefer a prompt, confidence-calibrated answer over waiting for redundant verification. Clearly label anything not directly proven.
- If an agent run becomes disproportionate to the request or the user reports excessive delay, cancel it individually and continue with available evidence.
- Explanation-only requests must not trigger implementation, full-suite testing, review waves, or orchestration plans.
