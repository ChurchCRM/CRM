---
title: "Church Review"
intent: "Review an issue or PR user-facing change from the church user's workflow and judge its value on the current product."
tags: ["issue","pr","product","workflow","ux"]
prereqs: []
complexity: "intermediate"
---

# Church Review

Use this card to judge whether a proposal helps a church user finish a real job on the product as it exists today. Review the issue or the PR's user-facing change; verify current behavior on `master` before reaching a verdict.

## Identify the user and job

Name the role doing the work: admin, finance/treasurer, office staff, group or volunteer leader, or member/portal user. State the recurring job and its cadence, such as the weekly giving and deposit cycle, Sunday check-in, monthly board or finance reporting, year-end statements, or one-off setup. If the issue does not identify a user and job, make that the first finding.

## Check the current product

Read the current screen and route, relevant service, and any dashboard or report that already covers part of the request. State what exists on `master`, the user's current workaround and its steps, and what the proposal changes from that baseline. Issue text can be stale; ground the review in the product.

## Judge workflow fit item by item

For each bundled item, say whether it shortens or completes the job end to end, or adds a screen or control without finishing it. Check for duplicated paths such as a menu item repeating a row link; consequential actions placed one click from a list when the user's workflow is review then act; data placed away from its workflow (name the better dashboard or page); labels, fields, or steps written for code rather than a volunteer; and member or giving data shown to a role that should not see it. Flag destructive actions and member-data exposure as hard blocks under the Security and RBAC review.

## Rate value and recommend

Rate the whole issue and each item high, medium, or low ROI. Weigh how many churches use the workflow, how often it occurs, today's pain, and build and maintenance cost. End with one recommendation: proceed as scoped; proceed with a slimmed scope naming exactly what to cut and move elsewhere; keep closed or close; or split, naming the split.

A change that tells or extends a user workflow story earns a marketing capture. A screen or CRUD operation on its own does not; state which applies.

## Draft output

Draft in chat. Post only when a maintainer says to post. Include:

- **User & job:** one or two lines
- **What exists on master today:** baseline and current workaround
- **Per-item workflow verdicts:** one entry per bundled item
- **ROI and recommendation:** overall and item ratings, exact slimmed scope when applicable, and whether the change earns a marketing capture
