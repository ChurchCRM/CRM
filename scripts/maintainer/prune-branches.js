#!/usr/bin/env node
// Prune branches whose work has landed. Dry run unless --apply.
//   node scripts/maintainer/prune-branches.js [--remote]                 preview, prints a plan id
//   node scripts/maintainer/prune-branches.js [--remote] --apply <id>    delete exactly that plan
// A branch has landed when its tip is merged into origin/master, or equals the head
// of one of your merged PRs from origin. Remote (--remote): origin branches of your
// own merged PRs.
// Never touched: master, the current branch, checked-out worktrees, branches with an
// open PR (any author), branches with unmerged work, and remote security/* branches.
const { execFileSync } = require("node:child_process");
const { createHash } = require("node:crypto");

const args = process.argv.slice(2);
const remote = args.includes("--remote");
const applyAt = args.indexOf("--apply");
const apply = applyAt !== -1;
const planId = apply ? args[applyAt + 1] : undefined;
const PROTECTED = /^(master|main|release\/.+|gh-pages)$/;
const REMOTE_PROTECTED = /^security\//;
const PR_LIMIT = 1000;

const run = (cmd, cmdArgs) => execFileSync(cmd, cmdArgs, { encoding: "utf8", stdio: ["ignore", "pipe", "pipe"] }).trim();
const lines = (text) => text.split("\n").filter(Boolean);
// Branch names may hold "|" but never a space, so "<sha> <name>" splits at the first space.
const refs = (pattern) =>
  lines(run("git", ["for-each-ref", "--format=%(objectname) %(refname:short)", pattern])).map((row) => {
    const cut = row.indexOf(" ");
    return { sha: row.slice(0, cut), name: row.slice(cut + 1) };
  });
const listPrs = (prArgs, fields) => JSON.parse(run("gh", ["pr", "list", ...prArgs, "--limit", String(PR_LIMIT), "--json", fields]));

try {
  run("git", ["fetch", "--prune", "origin"]);

  const originUrl = run("git", ["config", "--get", "remote.origin.url"]);
  const originOwner = originUrl.match(/github\.com[:/]([^/]+)\//)?.[1];

  const open = listPrs(["--state", "open"], "headRefName");
  if (open.length >= PR_LIMIT) throw new Error(`gh pr list returned ${PR_LIMIT} open PRs; the list may be truncated, so nothing is pruned.`);
  const openPrs = new Set(open.map((pr) => pr.headRefName));
  const mergedHeads = new Map();
  for (const pr of listPrs(["--author", "@me", "--state", "merged"], "headRefName,headRefOid,headRepositoryOwner")) {
    if (originOwner && pr.headRepositoryOwner?.login !== originOwner) continue;
    const heads = mergedHeads.get(pr.headRefName) ?? new Set();
    heads.add(pr.headRefOid);
    mergedHeads.set(pr.headRefName, heads);
  }
  const mergedPr = (name, sha) => mergedHeads.get(name)?.has(sha) ?? false;

  const current = run("git", ["branch", "--show-current"]);
  const worktreeBranches = new Set(
    lines(run("git", ["worktree", "list", "--porcelain"]))
      .filter((l) => l.startsWith("branch "))
      .map((l) => l.slice("branch refs/heads/".length)),
  );
  const mergedIntoMaster = new Set(lines(run("git", ["branch", "--format=%(refname:short)", "--merged", "origin/master"])));

  const localPlan = [];
  const localKept = [];
  for (const { name, sha } of refs("refs/heads")) {
    if (PROTECTED.test(name) || name === current || worktreeBranches.has(name)) continue;
    if (openPrs.has(name)) {
      localKept.push([name, "open PR"]);
    } else if (mergedIntoMaster.has(name) || mergedPr(name, sha)) {
      localPlan.push({ name, sha });
    } else {
      localKept.push([name, "not merged"]);
    }
  }

  const remotePlan = [];
  if (remote) {
    for (const { name: ref, sha } of refs("refs/remotes/origin")) {
      const name = ref.replace(/^origin\//, "");
      if (name === "HEAD" || name === current || worktreeBranches.has(name)) continue;
      if (PROTECTED.test(name) || REMOTE_PROTECTED.test(name) || openPrs.has(name)) continue;
      if (mergedPr(name, sha)) remotePlan.push({ name, sha });
    }
  }

  const id = createHash("sha1")
    .update([...localPlan.map((b) => `L ${b.sha} ${b.name}`), ...remotePlan.map((b) => `R ${b.sha} ${b.name}`)].join("\n"))
    .digest("hex")
    .slice(0, 12);

  const show = (title, items) => {
    console.log(`${title} (${items.length})`);
    for (const item of items) console.log("  " + (Array.isArray(item) ? `${item[0]}  [kept: ${item[1]}]` : item.name));
  };

  if (!apply) {
    show("Local branches to delete", localPlan);
    show("Local branches kept", localKept);
    if (remote) show("Remote branches to delete", remotePlan);
    console.log(`\nDry run. To delete exactly this list: --apply ${id}`);
    process.exit(0);
  }
  if (planId !== id) {
    console.error(`The branches changed since the preview (plan ${id}, not ${planId ?? "none"}). Run a dry run again.`);
    process.exit(1);
  }
  for (const { name } of localPlan) run("git", ["branch", "-D", name]);
  for (const { name } of remotePlan) run("git", ["push", "origin", "--delete", name]);
  console.log(`Deleted ${localPlan.length} local and ${remotePlan.length} remote branches.`);
} catch (error) {
  console.error(error.stderr?.trim() || error.message);
  process.exit(1);
}
