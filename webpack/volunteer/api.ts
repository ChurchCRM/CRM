/**
 * Shared client for the Volunteer v2 setup API (#9715).
 *
 * One module so the setup flow and the ministry page cannot drift apart on a
 * field name, a path or an error shape. #9707 and #9708 add their own calls
 * here rather than re-deriving the envelope.
 *
 * The API layer answers with `SlimUtils` envelopes: success payloads are a plain
 * object, failures are `{"success": false, "message": "…"}` with a meaningful
 * status. `request()` turns a failure into a `VolunteerApiError` carrying both,
 * so a caller can show the server's own sentence ("A ministry with that name
 * already exists") instead of inventing one, and can branch on `status` when it
 * needs to.
 *
 * `window.CRM.root` is read lazily, per call, never captured at module scope:
 * the bundle is evaluated before the inline `<script>` in the view has
 * necessarily run, and a subdirectory install ("/churchcrm") would otherwise get
 * "".
 */

/** One ministry as `volunteerMinistryToArray()` shapes it. */
export interface VolunteerMinistry {
  id: number;
  name: string;
  description: string | null;
  active: boolean;
  createdDate: string | null;
  createdByPersonId: number | null;
  teamCount: number;
  positionCount: number;
}

/** One team as `volunteerTeamToArray()` shapes it. */
export interface VolunteerTeam {
  id: number;
  ministryId: number;
  name: string;
  description: string | null;
  active: boolean;
  positionCount: number;
}

/**
 * One position as `volunteerPositionToArray()` shapes it.
 *
 * `teamId` is never null: every position belongs to a team, and a ministry is
 * never created without one.
 */
export interface VolunteerPosition {
  id: number;
  ministryId: number;
  teamId: number;
  teamName: string | null;
  name: string;
  description: string | null;
  active: boolean;
  order: number;
}

/**
 * One pool link as `volunteerPoolToArray()` shapes it (#9707).
 *
 * `memberCount` is counted live off the Group's own membership rows on every
 * request — V2 copies no people (design D1, §2.5) — so it is always current and
 * is never written back.
 */
export interface VolunteerPool {
  id: number;
  ownerType: "ministry" | "team";
  ownerId: number;
  ownerName: string | null;
  groupId: number;
  groupName: string | null;
  memberCount: number;
  label: string | null;
}

/** One qualification as `volunteerQualificationToArray()` shapes it (#9707). */
export interface VolunteerQualification {
  id: number;
  personId: number;
  displayName: string | null;
  positionId: number;
  positionName: string | null;
  ministryId: number | null;
  teamId: number | null;
  active: boolean;
  grantedDate: string | null;
  grantedByPersonId: number | null;
  notes: string | null;
}

/** One row of the qualification matrix: a person and the positions they hold. */
export interface VolunteerPoolPerson {
  personId: number;
  displayName: string;
  /** The pool groups this person arrived through — they appear once however many. */
  groupIds: number[];
  /** Position ids the person is actively qualified for (design §3.3.1). */
  qualifications: number[];
  /** Position id → qualification row id, so unticking revokes that exact row. */
  qualificationIds: Record<string, number>;
}

/**
 * The whole matrix in one document — positions across the top, pool people down
 * the side, each person's qualified position ids inline. §5.4 requires this to
 * be ONE fetch for 15–200 people, never a request per cell.
 */
export interface QualificationMatrix {
  ministryId: number;
  teamId: number | null;
  positions: VolunteerPosition[];
  pools: VolunteerPool[];
  people: VolunteerPoolPerson[];
}

export interface MinistryDetail {
  ministry: VolunteerMinistry;
  teams: VolunteerTeam[];
  positions: VolunteerPosition[];
  /** Added by #9707; absent on a response from an older build. */
  pools?: VolunteerPool[];
}

/** A non-2xx answer from the API, carrying the server's message and status. */
export class VolunteerApiError extends Error {
  readonly status: number;

  constructor(message: string, status: number) {
    super(message);
    this.name = "VolunteerApiError";
    this.status = status;
  }
}

/** The install's root path, read at call time (see the module docblock). */
function rootPath(): string {
  return window.CRM?.root ?? "";
}

async function request<T>(path: string, init?: RequestInit): Promise<T> {
  return requestAt(`/api/volunteer${path}`, init);
}

/**
 * Same envelope handling as `request()`, for the few core endpoints V2 reuses
 * outside `/api/volunteer` (today: creating a Group to use as a pool).
 */
async function requestAt<T>(absolutePath: string, init?: RequestInit): Promise<T> {
  const response = await fetch(`${rootPath()}${absolutePath}`, {
    credentials: "same-origin",
    headers: { Accept: "application/json", "Content-Type": "application/json" },
    ...init,
  });

  let body: unknown = null;
  try {
    body = await response.json();
  } catch {
    // A middleware denial can answer with an empty body; the status still speaks.
    body = null;
  }

  if (!response.ok) {
    const message =
      (body as { message?: string; error?: string } | null)?.message ??
      (body as { message?: string; error?: string } | null)?.error ??
      "";
    throw new VolunteerApiError(message, response.status);
  }

  return body as T;
}

export function listMinistries(activeOnly = false): Promise<{ ministries: VolunteerMinistry[] }> {
  return request(`/ministries${activeOnly ? "?active=1" : ""}`);
}

export function createMinistry(name: string, description: string): Promise<{ ministry: VolunteerMinistry }> {
  return request("/ministries", { method: "POST", body: JSON.stringify({ name, description }) });
}

export function getMinistry(ministryId: number): Promise<MinistryDetail> {
  return request(`/ministries/${ministryId}`);
}

export function updateMinistry(
  ministryId: number,
  fields: Partial<Pick<VolunteerMinistry, "name" | "description" | "active">>,
): Promise<{ ministry: VolunteerMinistry }> {
  return request(`/ministries/${ministryId}`, { method: "POST", body: JSON.stringify(fields) });
}

export function listTeams(ministryId: number): Promise<{ teams: VolunteerTeam[] }> {
  return request(`/ministries/${ministryId}/teams`);
}

export function createTeam(ministryId: number, name: string, description: string): Promise<{ team: VolunteerTeam }> {
  return request(`/ministries/${ministryId}/teams`, {
    method: "POST",
    body: JSON.stringify({ name, description }),
  });
}

export function updateTeam(
  teamId: number,
  fields: Partial<Pick<VolunteerTeam, "name" | "description" | "active">>,
): Promise<{ team: VolunteerTeam }> {
  return request(`/teams/${teamId}`, { method: "POST", body: JSON.stringify(fields) });
}

export function deleteTeam(teamId: number): Promise<{ success: boolean }> {
  return request(`/teams/${teamId}`, { method: "DELETE" });
}

export function listPositions(ministryId: number): Promise<{ positions: VolunteerPosition[] }> {
  return request(`/ministries/${ministryId}/positions`);
}

export function createPosition(
  ministryId: number,
  payload: { name: string; description: string; teamId: number | null; order: number },
): Promise<{ position: VolunteerPosition }> {
  return request(`/ministries/${ministryId}/positions`, { method: "POST", body: JSON.stringify(payload) });
}

export function updatePosition(
  positionId: number,
  fields: Partial<Pick<VolunteerPosition, "name" | "description" | "teamId" | "order" | "active">>,
): Promise<{ position: VolunteerPosition }> {
  return request(`/positions/${positionId}`, { method: "POST", body: JSON.stringify(fields) });
}

export function deletePosition(positionId: number): Promise<{ success: boolean }> {
  return request(`/positions/${positionId}`, { method: "DELETE" });
}

// ─── Pools and qualifications (#9707) ────────────────────────────────────────

export function listPools(ministryId: number, teamId?: number | null): Promise<{ pools: VolunteerPool[] }> {
  return request(`/ministries/${ministryId}/pools${teamId ? `?teamId=${teamId}` : ""}`);
}

/**
 * Link an existing Group as a pool. The owner is a ministry or a team; there is
 * no third case, because `vpol_OwnerType` is an enum of exactly those two.
 */
export function linkPool(
  owner: { type: "ministry" | "team"; id: number },
  groupId: number,
  label = "",
): Promise<{ pool: VolunteerPool }> {
  const path = owner.type === "ministry" ? `/ministries/${owner.id}/pools` : `/teams/${owner.id}/pools`;

  return request(path, { method: "POST", body: JSON.stringify({ groupId, label }) });
}

/**
 * Create a Group through the core groups API (`POST /api/groups/`), the same call
 * the Groups dashboard makes — V2 builds no second group editor. Membership is
 * managed on the group page afterwards (Appendix D-1). The core API answers with
 * Propel's `toArray()` (phpName keys), hence `Id`.
 */
export function createGroup(groupName: string): Promise<{ Id: number; Name: string }> {
  return requestAt("/api/groups/", { method: "POST", body: JSON.stringify({ groupName }) });
}

/** Unlink a pool. The Group itself is never touched (D1, Appendix D-1). */
export function unlinkPool(poolId: number): Promise<{ success: boolean }> {
  return request(`/pools/${poolId}`, { method: "DELETE" });
}

export function getQualificationMatrix(ministryId: number, teamId?: number | null): Promise<QualificationMatrix> {
  return request(`/ministries/${ministryId}/qualification-matrix${teamId ? `?teamId=${teamId}` : ""}`);
}

/** Idempotent: a repeat grant reactivates the same row rather than adding one. */
export function grantQualification(
  positionId: number,
  personId: number,
  notes = "",
): Promise<{ qualification: VolunteerQualification }> {
  return request(`/positions/${positionId}/qualifications`, {
    method: "POST",
    body: JSON.stringify({ personId, notes }),
  });
}

export function listQualifications(
  positionId: number,
  activeOnly = false,
): Promise<{ qualifications: VolunteerQualification[] }> {
  return request(`/positions/${positionId}/qualifications${activeOnly ? "?active=1" : ""}`);
}

/** Revoke = deactivate; the row survives so history stays readable (§2.7). */
export function revokeQualification(qualificationId: number): Promise<{ qualification: VolunteerQualification }> {
  return request(`/qualifications/${qualificationId}`, { method: "DELETE" });
}

/** The Cart sink (P5/P6): the people come from the session cart, not the body. */
export function qualifyCart(
  positionId: number,
): Promise<{ granted: number; reactivated: number; existing: number; skipped: number }> {
  return request(`/positions/${positionId}/qualifications/from-cart`, { method: "POST" });
}

/**
 * The message to put in front of a person, preferring the server's own sentence.
 * `fallback` is used when a denial answered with no body at all.
 */
export function errorMessage(error: unknown, fallback: string): string {
  if (error instanceof VolunteerApiError && error.message !== "") {
    return error.message;
  }

  return fallback;
}

/** Toast helper — `"danger"`, never `"error"`: `"error"` renders blue (U5/E-7). */
export function notifyError(message: string): void {
  window.CRM?.notify?.(message, { type: "danger" });
}

export function notifySuccess(message: string): void {
  window.CRM?.notify?.(message, { type: "success" });
}

/**
 * How a position is named on a screen that can show more than one team's positions
 * at once — "Elementary · Lead Teacher".
 *
 * This is the whole point of the "every ministry has at least one team" decision:
 * two teams under "Children's Ministry" may each own a "Lead Teacher", and a
 * coordinator looking at a qualification matrix, a dashboard gap list or a
 * cross-team "still needed" line has to be able to tell them apart. Where the
 * context is already ONE team — a schedule form after a team is chosen, the
 * occurrence page, whose header already names the ministry and the team — the bare
 * position name is right and this helper is not used.
 *
 * The separator is a middle dot with hair spaces around it, matching the
 * "Ministry · Team" line the occurrence header already draws. Returns the bare
 * position name when there is no team name to prefix, so a caller never has to
 * guard.
 */
export function positionLabel(teamName: string | null | undefined, positionName: string | null): string {
  const position = positionName ?? "";
  const team = teamName ?? "";

  return team === "" ? position : `${team} · ${position}`;
}

// ─── Assignments, gaps and swaps (#9709) ─────────────────────────────────────

/** One assignment as `volunteerAssignmentToArray()` shapes it. */
export interface VolunteerAssignment {
  id: number;
  occurrenceId: number;
  positionId: number;
  positionName: string | null;
  personId: number;
  displayName: string | null;
  requirementId: number | null;
  status: "pending" | "accepted" | "declined" | "cancelled" | "substituted" | "completed";
  source: "coordinator" | "self_signup" | "substitute";
  assignedDate: string | null;
  assignedByPersonId: number | null;
  respondedDate: string | null;
  replacesAssignmentId: number | null;
  notes: string | null;
  /** Only ever set for a LINKED occurrence (design E10); read-only, V2 never writes it. */
  attendance: "checked_in" | "checked_out" | "not_checked_in" | null;
}

/**
 * One requirement with its derived counts and the rows filling it.
 *
 * `gapCount` is `max(0, min - live)` and `openCount` is the remaining self-signup
 * capacity — they differ whenever a requirement has a `maxCount` above its `minCount`
 * (the optional third volunteer of §2.17). Both come from the server's single gap
 * implementation; nothing here re-derives them.
 */
export interface VolunteerStaffedRequirement {
  requirementId: number | null;
  positionId: number;
  positionName: string | null;
  minCount: number;
  maxCount: number | null;
  liveCount: number;
  gapCount: number;
  openCount: number;
  pendingCount: number;
  acceptedCount: number;
  source: "schedule" | "occurrence";
  assignments: VolunteerAssignment[];
}

export interface VolunteerOccurrenceSummary {
  id: number;
  scheduleId: number;
  scheduleName: string | null;
  ministryId: number | null;
  teamId: number | null;
  eventId: number | null;
  occurrenceDate: string | null;
  start: string | null;
  end: string | null;
  status: "scheduled" | "cancelled";
  requiredCount: number;
  /**
   * How many positions the effective plan names — NOT `requiredCount`, which adds their
   * minimums up. A Min 0 / Max 1 requirement contributes a plan and no required body, so
   * only this number can tell "nobody has set any staffing needs" apart from "everything
   * that was asked for is filled". Zero must render as "No staffing needs set", never as
   * "Fully staffed".
   */
  requirementCount: number;
  /** The occurrence carries override rows, so "use the schedule's needs" has somewhere to go. */
  requirementsOverridden: boolean;
  liveCount: number;
  gapCount: number;
  openCount: number;
  pendingCount: number;
  /**
   * The genuinely-short positions by name, so a list can say "1 Lead Teacher, 2 Helper"
   * rather than a bare number nobody can act on. Sliced out of the same `getGaps()`
   * result the totals come from.
   */
  gaps: VolunteerOccurrenceGap[];
}

export interface VolunteerOccurrenceGap {
  positionId: number;
  positionName: string | null;
  gapCount: number;
}

/**
 * One position the staffing-needs editor may offer, from the occurrence's own scope.
 *
 * `teamName` lets a caller that mixes several teams' positions label them
 * "{Team} · {Position}"; an editor showing one team's positions uses the bare name.
 */
export interface VolunteerCandidatePosition {
  id: number;
  name: string;
  teamId: number;
  teamName?: string | null;
  order: number;
}

/** One row of the staffing-needs editor as it is sent back to the server. */
export interface VolunteerRequirementInput {
  positionId: number;
  minCount: number;
  maxCount: number | null;
}

/** A requirement as `volunteerRequirementToArray()` shapes it. */
export interface VolunteerRequirementRow {
  id: number;
  scheduleId: number | null;
  occurrenceId: number | null;
  positionId: number;
  positionName: string | null;
  minCount: number;
  maxCount: number | null;
  notes: string | null;
  source: "schedule" | "occurrence";
}

export interface VolunteerOccurrenceRequirements {
  occurrenceId: number;
  scheduleId: number | null;
  scheduleName: string | null;
  requirements: VolunteerRequirementRow[];
  /** At least one row is this occurrence's own override. */
  overridden: boolean;
  positions: VolunteerCandidatePosition[];
  /**
   * The positions the SCHEDULE asks for. The merge is a union (§2.10): an occurrence
   * cannot remove a schedule's requirement by leaving it out, only outvote it — so
   * "not this week" is an override of Min 0 / Max 0, and the editor needs this list to
   * know which unchecked rows have to be written as one.
   */
  schedulePositionIds: number[];
}

export interface VolunteerStaffing {
  occurrence: VolunteerOccurrenceSummary;
  ministryId: number | null;
  teamId: number | null;
  requirements: VolunteerStaffedRequirement[];
  /** Rows whose position is no longer part of the plan — surfaced, never hidden. */
  otherAssignments: VolunteerAssignment[];
  attendanceAvailable: boolean;
}

/**
 * One candidate from the eligible picker.
 *
 * `inPool` is reported, never used to filter: an out-of-pool qualified person is
 * assignable behind the `allowOutsidePool` confirm (I3), so hiding them would make the
 * override unreachable. `conflictPositionId` is the I7/D16 annotation — the person
 * already holds ANOTHER position on this occurrence, which is allowed and only warned
 * about.
 */
export interface VolunteerEligiblePerson {
  personId: number;
  displayName: string;
  inPool: boolean;
  lastServedDate: string | null;
  conflictPositionId: number | null;
  conflictPositionName: string | null;
}

/** One substitution request as `volunteerSwapToArray()` shapes it. */
export interface VolunteerSwap {
  id: number;
  assignmentId: number;
  occurrenceId: number | null;
  positionId: number | null;
  positionName: string | null;
  proposedByPersonId: number;
  proposedByName: string | null;
  proposedPersonId: number;
  proposedPersonName: string | null;
  status: "proposed" | "approved" | "rejected" | "withdrawn";
  proposedDate: string | null;
  decidedDate: string | null;
  decidedByPersonId: number | null;
  comment: string | null;
}

/**
 * Everything the "Edit staffing needs" modal draws: the EFFECTIVE requirements (the
 * schedule's, with this occurrence's overrides merged over them) and the positions this
 * occurrence could name, both under the occurrence's own scope check.
 */
export function getOccurrenceRequirements(occurrenceId: number): Promise<VolunteerOccurrenceRequirements> {
  return request(`/occurrences/${occurrenceId}/requirements`);
}

/** Write this occurrence's override rows to match `requirements` exactly. */
export function replaceOccurrenceRequirements(
  occurrenceId: number,
  requirements: VolunteerRequirementInput[],
): Promise<{ requirements: VolunteerRequirementRow[]; overridden: boolean }> {
  return request(`/occurrences/${occurrenceId}/requirements/replace`, {
    method: "POST",
    body: JSON.stringify({ requirements }),
  });
}

/**
 * Drop the overrides so the occurrence follows its schedule again. Nothing was ever
 * copied at generation time — the merge is derived on every read — so this is the whole
 * of the reset.
 */
export function clearOccurrenceRequirements(
  occurrenceId: number,
): Promise<{ requirements: VolunteerRequirementRow[]; overridden: boolean }> {
  return request(`/occurrences/${occurrenceId}/requirements`, { method: "DELETE" });
}

export function getStaffing(occurrenceId: number): Promise<VolunteerStaffing> {
  return request(`/occurrences/${occurrenceId}/staffing`);
}

/** Ordered by last served, never-served first — that ordering IS the rotation (§2.17). */
export function listEligiblePeople(
  occurrenceId: number,
  positionId: number,
  query = "",
): Promise<{ people: VolunteerEligiblePerson[] }> {
  const q = query === "" ? "" : `&q=${encodeURIComponent(query)}`;

  return request(`/occurrences/${occurrenceId}/eligible?positionId=${positionId}${q}`);
}

export function createAssignment(
  occurrenceId: number,
  payload: { positionId: number; personId: number; allowOutsidePool?: boolean; notes?: string },
): Promise<{ assignment: VolunteerAssignment }> {
  return request(`/occurrences/${occurrenceId}/assignments`, {
    method: "POST",
    body: JSON.stringify(payload),
  });
}

/**
 * Coordinator status change. `accepted` / `declined` record a response on the
 * volunteer's behalf and are written with channel `coordinator`, so the audit trail
 * shows who actually recorded it (§2.11.1).
 */
export function setAssignmentStatus(
  assignmentId: number,
  status: "accepted" | "declined" | "cancelled",
  comment = "",
): Promise<{ assignment: VolunteerAssignment }> {
  return request(`/assignments/${assignmentId}/status`, {
    method: "POST",
    body: JSON.stringify({ status, comment }),
  });
}

/** Cancels; hard-deletes only a pending row that carries no history (§3.3.2). */
export function deleteAssignment(assignmentId: number): Promise<{ deleted: boolean }> {
  return request(`/assignments/${assignmentId}`, { method: "DELETE" });
}

/** Idempotent through the dedupe key unless `force` (§2.14). Nothing is sent here. */
export function notifyAssignment(
  assignmentId: number,
  force = false,
): Promise<{ created: boolean; notification: { id: number; status: string } }> {
  return request(`/assignments/${assignmentId}/notify${force ? "?force=1" : ""}`, {
    method: "POST",
    body: JSON.stringify({}),
  });
}

export function listSwaps(occurrenceId: number, status = "proposed"): Promise<{ swaps: VolunteerSwap[] }> {
  return request(`/swaps?occurrenceId=${occurrenceId}&status=${encodeURIComponent(status)}`);
}

/** One transaction server-side: original substituted, replacement inserted (§2.13). */
export function approveSwap(swapId: number, comment = ""): Promise<{ swap: VolunteerSwap }> {
  return request(`/swaps/${swapId}/approve`, { method: "POST", body: JSON.stringify({ comment }) });
}

export function rejectSwap(swapId: number, comment = ""): Promise<{ swap: VolunteerSwap }> {
  return request(`/swaps/${swapId}/reject`, { method: "POST", body: JSON.stringify({ comment }) });
}

/**
 * The Cart sink (P6): the people come from the session cart, not the body. Per-person
 * failures come back in `skipped` rather than failing the batch.
 */
export function assignCart(
  occurrenceId: number,
  positionId: number,
): Promise<{ assigned: number; skipped: Array<{ personId: number; reason: string }> }> {
  return request("/cart/assign", {
    method: "POST",
    body: JSON.stringify({ occurrenceId, positionId }),
  });
}

/**
 * The coordinator occurrence list (#9708, with #9709's derived counts filled in).
 *
 * `from` and `to` are mandatory server-side — no pagination protocol is invented for
 * one module (design M9). `hasGaps` filters to the weeks that are actually short.
 */
export function listOccurrences(params: {
  from: string;
  to: string;
  ministryId?: number;
  teamId?: number;
  hasGaps?: boolean;
}): Promise<{ occurrences: VolunteerOccurrenceSummary[]; capped: boolean }> {
  const query = new URLSearchParams({ from: params.from, to: params.to });
  if (params.ministryId) {
    query.set("ministryId", String(params.ministryId));
  }
  if (params.teamId) {
    query.set("teamId", String(params.teamId));
  }
  if (params.hasGaps) {
    query.set("hasGaps", "1");
  }

  return request(`/occurrences?${query.toString()}`);
}

// ─── The member surface (#9712, design §3.3.3) ───────────────────────────────
//
// Same `request()`, same envelope, same error type as everything above — the member
// screens are not a second client. What makes them the member surface is what the
// calls do NOT carry: there is no `personId` anywhere below, because every one of
// these endpoints derives the acting person from the session (§3.3.3). The only
// `personId` that appears is `proposeSubstitute()`'s, and it names the substitute.

/** One of my own commitments, as the S5 card renders it. */
export interface VolunteerMyAssignment {
  id: number;
  occurrenceId: number;
  personId: number;
  positionId: number;
  positionName: string | null;
  ministryName: string | null;
  teamName: string | null;
  start: string | null;
  end: string | null;
  occurrenceDate: string | null;
  occurrenceStatus: "scheduled" | "cancelled" | null;
  status: "pending" | "accepted" | "declined" | "cancelled" | "substituted" | "completed";
  source: "coordinator" | "self_signup" | "substitute";
  respondedDate: string | null;
  canRespond: boolean;
  canProposeSubstitute: boolean;
  /** Set while a substitution request of mine is still `proposed` — drives Withdraw. */
  pendingSwapId: number | null;
  pendingSwapPersonName: string | null;
}

/**
 * One open slot I could take.
 *
 * `alreadyServing` is §5.6's same-occurrence warning and D16's "allowed, with a
 * warning, never blocked": the row is still offered, the card just says so.
 */
export interface VolunteerMyOpportunity {
  occurrenceId: number;
  positionId: number;
  positionName: string | null;
  ministryName: string | null;
  teamName: string | null;
  occurrenceDate: string | null;
  start: string | null;
  end: string | null;
  openCount: number;
  minCount: number;
  liveCount: number;
  alreadyServing: boolean;
  alreadyServingPositionNames: string[];
}

export interface VolunteerMyQualification {
  positionId: number;
  positionName: string;
  ministryId: number;
  ministryName: string | null;
  teamId: number | null;
  teamName: string | null;
}

export function listMyAssignments(includePast = false): Promise<{ assignments: VolunteerMyAssignment[] }> {
  return request(`/me/assignments${includePast ? "?includePast=1" : ""}`);
}

export function respondToMyAssignment(
  assignmentId: number,
  response: "accepted" | "declined",
  comment = "",
): Promise<{ assignment: VolunteerMyAssignment }> {
  return request(`/me/assignments/${assignmentId}/respond`, {
    method: "POST",
    body: JSON.stringify({ response, comment }),
  });
}

/** The picker source for "Find a sub" — already excludes me and anyone on this slot. */
export function listMySubstituteCandidates(
  assignmentId: number,
  query = "",
): Promise<{ people: VolunteerEligiblePerson[] }> {
  const suffix = query ? `?q=${encodeURIComponent(query)}` : "";

  return request(`/me/assignments/${assignmentId}/substitutes${suffix}`);
}

/** `personId` here is the SUBSTITUTE. The proposer is the session. */
export function proposeMySubstitute(
  assignmentId: number,
  personId: number,
  comment = "",
): Promise<{ swap: VolunteerSwap }> {
  return request(`/me/assignments/${assignmentId}/propose-substitute`, {
    method: "POST",
    body: JSON.stringify({ personId, comment }),
  });
}

export function withdrawMySwap(swapId: number, comment = ""): Promise<{ swap: VolunteerSwap }> {
  return request(`/me/swaps/${swapId}/withdraw`, {
    method: "POST",
    body: JSON.stringify({ comment }),
  });
}

export function listMyOpportunities(
  from?: string,
  to?: string,
): Promise<{ opportunities: VolunteerMyOpportunity[]; from: string; to: string }> {
  const query = new URLSearchParams();
  if (from) {
    query.set("from", from);
  }
  if (to) {
    query.set("to", to);
  }
  const suffix = query.toString() ? `?${query.toString()}` : "";

  return request(`/me/opportunities${suffix}`);
}

export function signUpForOpportunity(
  occurrenceId: number,
  positionId: number,
): Promise<{ assignment: VolunteerMyAssignment }> {
  return request("/me/signup", {
    method: "POST",
    body: JSON.stringify({ occurrenceId, positionId }),
  });
}

export function listMyQualifications(): Promise<{ qualifications: VolunteerMyQualification[] }> {
  return request("/me/qualifications");
}

// ─── Schedules (#9708, surfaced on the ministry page by #9711) ───────────────

/** One schedule as `volunteerScheduleToArray()` shapes it. */
export interface VolunteerSchedule {
  id: number;
  ministryId: number;
  /** Never null: a schedule always belongs to one of its ministry's teams. */
  teamId: number;
  name: string;
  linkMode: "event_type" | "standalone";
  eventTypeId: number | null;
  eventTypeName: string | null;
  titleFilter: string | null;
  recurType: string | null;
  recurDow: string | null;
  recurDom: number | null;
  startTime: string | null;
  endTime: string | null;
  windowStart: string | null;
  windowEnd: string | null;
  generateAheadDays: number;
  active: boolean;
  /** Cheap "has this been generated yet?" signal — a COUNT, never a hydration. */
  occurrenceCount: number;
}

export function listSchedules(ministryId: number): Promise<{ schedules: VolunteerSchedule[] }> {
  return request(`/ministries/${ministryId}/schedules`);
}

/** A schedule's template staffing needs — what the edit form pre-fills its rows from. */
export function listScheduleRequirements(scheduleId: number): Promise<{ requirements: VolunteerRequirementRow[] }> {
  return request(`/schedules/${scheduleId}/requirements`);
}

export function createSchedule(
  ministryId: number,
  payload: Record<string, unknown>,
): Promise<{ schedule: VolunteerSchedule }> {
  return request(`/ministries/${ministryId}/schedules`, { method: "POST", body: JSON.stringify(payload) });
}

export function updateSchedule(
  scheduleId: number,
  fields: Record<string, unknown>,
): Promise<{ schedule: VolunteerSchedule }> {
  return request(`/schedules/${scheduleId}`, { method: "POST", body: JSON.stringify(fields) });
}

export function deleteSchedule(scheduleId: number): Promise<{ success: boolean }> {
  return request(`/schedules/${scheduleId}`, { method: "DELETE" });
}

/** Idempotent (§2.9): re-running it creates nothing that already exists. */
export function generateOccurrences(
  scheduleId: number,
  through?: string,
): Promise<{ created: number; existing: number; through: string }> {
  return request(`/schedules/${scheduleId}/generate`, {
    method: "POST",
    body: JSON.stringify(through ? { through } : {}),
  });
}

// ─── The coordinator dashboard aggregate (#9711) ─────────────────────────────

/**
 * The occurrence context every dashboard row carries, so a row can be rendered
 * and clicked without a second request (design §5.2).
 */
export interface VolunteerDashboardContext {
  occurrenceId: number;
  occurrenceDate: string | null;
  start: string | null;
  end: string | null;
  occurrenceStatus: string;
  eventId: number | null;
  scheduleId: number | null;
  scheduleName: string | null;
  ministryId: number | null;
  ministryName: string | null;
  teamId: number | null;
  teamName: string | null;
}

export type VolunteerDashboardGap = {
  positionId: number;
  positionName: string | null;
  minCount: number;
  liveCount: number;
  gapCount: number;
} & VolunteerDashboardContext;

export type VolunteerDashboardPending = VolunteerAssignment &
  VolunteerDashboardContext & {
    /** True when the occurrence is inside `iVolunteerReminderLeadHours` of now. */
    withinReminderWindow: boolean;
  };

export type VolunteerDashboardSwap = VolunteerSwap & Partial<VolunteerDashboardContext>;

export type VolunteerDashboardOccurrence = VolunteerOccurrenceSummary & VolunteerDashboardContext;

/** One ministry the caller may navigate to; `manageable` is false for a team leader's parent. */
export interface VolunteerScopeMinistry {
  id: number;
  name: string;
  description: string | null;
  active: boolean;
  manageable: boolean;
}

export interface VolunteerScopeTeam {
  id: number;
  name: string;
  ministryId: number;
  ministryName: string | null;
  active: boolean;
}

export interface VolunteerDashboard {
  days: number;
  from: string;
  to: string;
  upcoming: VolunteerDashboardOccurrence[];
  gaps: VolunteerDashboardGap[];
  pendingResponses: VolunteerDashboardPending[];
  proposedSwaps: VolunteerDashboardSwap[];
  failedNotifications: number;
  scope: {
    isAdmin: boolean;
    isManager: boolean;
    ministries: VolunteerScopeMinistry[];
    teams: VolunteerScopeTeam[];
  };
  limit: number;
  capped: boolean;
}

/**
 * The ONE call S1 makes. §5.2: "The dashboard makes exactly one API call and
 * renders all five panels from it. Do not fan out to five endpoints."
 */
export function getDashboard(days: number): Promise<VolunteerDashboard> {
  return request(`/dashboard?days=${days}`);
}
