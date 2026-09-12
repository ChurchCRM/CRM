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

/** One position as `volunteerPositionToArray()` shapes it. */
export interface VolunteerPosition {
  id: number;
  ministryId: number;
  teamId: number | null;
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
  const response = await fetch(`${rootPath()}/api/volunteer${path}`, {
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
