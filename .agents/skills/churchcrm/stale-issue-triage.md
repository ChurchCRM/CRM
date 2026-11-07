---
title: "Stale Issue Triage"
intent: "Re-triage issues closed by the stale bot: verify each one against current master, then reopen it, close it as addressed, or keep it closed with its labels cleaned"
tags: ["issues", "triage", "process", "github"]
prereqs: ["[[church-review]]"]
complexity: "intermediate"
---

# Stale Issue Triage

How to work through issues the stale bot closed as `not_planned` with the `Stale` label, and give each one a verified, recorded disposition. Issues closed as completed or duplicate are deliberate closures and stay out of the pool unless a maintainer says otherwise.

The maintainer decides judgment calls. The agent verifies, executes the decided dispositions, and keeps the counts honest.

## Verify before you judge

Never disposition an issue from its title or a keyword search.

- Read the thread: what was actually asked, who reported it, and whether a maintainer already engaged or made a call in it.
- Search for the concept behind the ask, not only its exact words. Features get revamped, renamed, and replaced; search the successor's vocabulary too, then read the code that implements it.
- Read the implementing file yourself. Before quoting a function as evidence, find its live caller or route; a method with no callers in `src/` is dead code, not shipped behavior.
- Check design-intent sources (these skill cards, `docs/decisions/`) before calling a convention a bug. Some behaviors that look wrong are documented design — event times stored as wall-clock in the church timezone are the standing example (`timezone-handling.md`).
- Check prior art before recommending anything new: the open epics, the RFC board (`docs/decisions/`), and the same ask under different wording (family / envelope / giving / contact clusters are common). If a shipped feature answers the ask in a different shape, say which one.

Record one verdict per issue:

| Verdict | Meaning |
|---|---|
| PRESENT | The ask is addressed in master — cite the file and what exists |
| ABSENT | Not in master — cite what you searched |
| COVERED | An open issue, epic, or RFC already tracks it — name it |
| KEEP-CLOSED | Obsolete, superseded, or by design — one line why |

Never present a queue item to the maintainer with an unverified caveat. If the evidence is mixed, ask one scoped question instead of presenting a call built on a guess.

## Dispositions

One approval from the maintainer covers the whole bundle for a ticket (reopen + labels + comment + type). Execute through the sandbox/write path the maintainer has approved; verify the live state after, because a timed-out write may still have landed.

- **ABSENT, maintainer says reopen**: reopen, strip `Stale` and `needs-manual-review`, post a status comment with the verified evidence and the bounded scope, and set the issue type (Bug / Feature / Task / Question / Epic). Add `good first issue` when the fix is simple and bounded. Set the target-release milestone when it needs addressing ASAP — breakage a church hits in normal use (errors, wrong data, blocked workflow).
- **PRESENT, outside reporter**: run the retest flow — reopen, strip both labels, set the type, and comment naming what shipped and in which version (or that the fix is on master for the next release), asking the reporter to retest on the latest version and confirm; then close as completed. The comment promises a reopen if it still happens.
- **PRESENT, internal reporter** (maintainers and the project's own reporting accounts): silent cleanup only — strip both labels, set the type, no comment, issue stays closed.
- **KEEP-CLOSED**: strip both labels and set the type. No comment unless a pointer helps (a fold or a covering issue worth linking).
- **COVERED / duplicate**: strip both labels, set the type, and post a short pointer comment linking the surviving issue or epic. Fold clusters into the surviving item rather than reopening each one.
- **Systemic pattern** (the same gap surfacing across many areas): propose one audit issue that tracks the sweep instead of many small reopens.
- **Direction-sized ask**: propose an RFC instead of a plain reopen — use cases, goals, non-goals, and open questions only; architecture is a separate document after the RFC is approved. Check the RFC board and open epics first, and fold a duplicate into the decided epic.

## Hygiene

- Decided means labels off: any issue with a disposition loses `Stale` and `needs-manual-review`. Only genuinely undecided items keep them — the labels are the marker of work still owed.
- Set the issue type on every issue you touch.
- On every closure as completed, judge whether the user docs cover the behavior. Not every change needs a docs update. When coverage is missing, file the docs issue in ChurchCRM/docs.churchcrm.io — never in this repo — and link it from the closed issue.
- Record every decision with its evidence in the triage decision log, so the next reader does not re-derive it.
- Count honestly: screened is not verified, and a verdict count covers only the issues that actually received one.

## Working the queue

- Present one issue at a time: verified evidence, bounded scope, one reopen or keep-closed call.
- Items whose verified call is keep-closed are dispositioned silently and logged, never presented.
- Frame a reopen around how church staff actually work (see `church-review.md`), not around a single field or sum.
- When an item depends on a decision the maintainer has not made, park it explicitly and return after that decision lands.
