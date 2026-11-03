#!/usr/bin/env node
// Prune branches whose work has landed. Dry run unless --apply.
//   node scripts/prune-branches.js [--apply] [--remote]
// Local: upstream gone, merged into origin/master, or PR merged, and no unpushed
// work. Remote (--remote): origin branches of your own merged PRs.
// Never touched: master, the current branch, checked-out worktrees, open PRs,
// closed-unmerged PRs, and remote security/* branches (private fix work).
const { execFileSync } = require("node:child_process");

const apply = process.argv.includes("--apply");
const remote = process.argv.includes("--remote");
const PROTECTED = /^(master|main|release\/.+|gh-pages)$/;
const REMOTE_PROTECTED = /^security\//;

const run = (cmd, args) => execFileSync(cmd, args, { encoding: "utf8", stdio: ["ignore", "pipe", "pipe"] }).trim();
const lines = (text) => text.split("\n").filter(Boolean);

run("git", ["fetch", "--prune", "origin"]);

const prs = JSON.parse(
  run("gh", ["pr", "list", "--author", "@me", "--state", "all", "--limit", "500", "--json", "headRefName,state,headRepositoryOwner"]),
);
const prState = new Map();
for (const pr of prs) {
  const states = prState.get(pr.headRefName) ?? new Set();
  states.add(pr.state);
  prState.set(pr.headRefName, states);
}
const hasOpenPr = (name) => prState.get(name)?.has("OPEN") ?? false;
const mergedPr = (name) => (prState.get(name)?.has("MERGED") ?? false) && !hasOpenPr(name);

const current = run("git", ["branch", "--show-current"]);
const worktreeBranches = new Set(
  lines(run("git", ["worktree", "list", "--porcelain"]))
    .filter((l) => l.startsWith("branch "))
    .map((l) => l.slice("branch refs/heads/".length)),
);
const mergedIntoMaster = new Set(lines(run("git", ["branch", "--format=%(refname:short)", "--merged", "origin/master"])));

const localPlan = [];
const localKept = [];
for (const row of lines(run("git", ["for-each-ref", "--format=%(refname:short)|%(upstream:track)", "refs/heads"]))) {
  const [name, track = ""] = row.split("|");
  if (PROTECTED.test(name) || name === current || worktreeBranches.has(name)) continue;
  if (hasOpenPr(name)) {
    localKept.push([name, "open PR"]);
    continue;
  }
  const gone = track.includes("gone");
  if (!gone && !mergedIntoMaster.has(name) && !mergedPr(name)) {
    localKept.push([name, "not merged"]);
    continue;
  }
  if (!gone && track.includes("ahead") && !mergedIntoMaster.has(name)) {
    localKept.push([name, "unpushed commits"]);
    continue;
  }
  localPlan.push(name);
}

const remotePlan = [];
if (remote) {
  for (const ref of lines(run("git", ["for-each-ref", "--format=%(refname:short)", "refs/remotes/origin"]))) {
    const name = ref.replace(/^origin\//, "");
    if (name === "HEAD" || PROTECTED.test(name) || REMOTE_PROTECTED.test(name) || hasOpenPr(name)) continue;
    if (mergedPr(name)) remotePlan.push(name);
  }
}

const show = (title, items) => {
  console.log(`${title} (${items.length})`);
  for (const item of items) console.log("  " + (Array.isArray(item) ? `${item[0]}  [kept: ${item[1]}]` : item));
};
show("Local branches to delete", localPlan);
show("Local branches kept", localKept);
if (remote) show("Remote branches to delete", remotePlan);

if (!apply) {
  console.log("\nDry run. Re-run with --apply to delete.");
  process.exit(0);
}
for (const name of localPlan) run("git", ["branch", "-D", name]);
for (const name of remotePlan) run("git", ["push", "origin", "--delete", name]);
console.log(`\nDeleted ${localPlan.length} local and ${remotePlan.length} remote branches.`);
