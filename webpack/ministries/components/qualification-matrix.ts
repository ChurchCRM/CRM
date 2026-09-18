/**
 * The qualification matrix — people down the side, positions across the top, a
 * checkbox in every cell (#9707, design §5.4).
 *
 * Extracted verbatim from `webpack/ministries/ministry.ts` for #9868 so the Member
 * Portal's My Teams page draws the same grid, with the same optimistic ticks, the
 * same tick-save toasts and the same horizontal-scroll / unclipped-menu rules, in
 * the portal layout. The only thing that differs between the two callers is the
 * CONTEXT handed in below: which teams the filter may offer, which document the
 * grid is fetched from, and whether the ministry-level pool controls exist.
 *
 * The grid is plain markup rather than a DataTable: the column set is data-driven
 * and every cell is an input, so DataTables' row cache would fight the optimistic
 * toggle. The one DataTables feature this grid wants — a filter box — is the
 * `#qualification-filter` input, applied over `data-person-name`.
 *
 * Controls a caller does not want are simply left out of ITS markup: every
 * `byId()` below returns null for an absent element and the wiring is inert. That
 * is how the portal hides "Add Volunteer", "Add from Cart" and "Remove Volunteer"
 * — all three are ministry-level acts the API refuses a team leader anyway (D5).
 */

import { attachToModal } from "../../common/person-select";
import {
  errorMessage,
  grantQualification,
  notifyError,
  notifySuccess,
  positionLabel,
  type QualificationMatrix,
  revokeQualification,
  type VolunteerPoolPerson,
  type VolunteerPosition,
  type VolunteerTeam,
} from "../api";
import {
  actionMenu,
  byId,
  confirmDelete,
  escapeAttribute,
  escapeHtml,
  hideModal,
  modal,
  renderState,
  setModalTitle,
  show,
  showModalError,
  wireModalFadeGuard,
  wireUnclippedRowMenus,
} from "./ui";

export interface QualificationMatrixOptions {
  /**
   * The teams `#qualification-team-filter` may offer, first one first. On the
   * portal this is the single team the page is about, which is why the filter is
   * left out of that template entirely.
   */
  teams(): VolunteerTeam[];
  /**
   * Make `teams()` answerable. The ministry page fetches its ministry document
   * here; the portal team page already knows its one team and resolves at once.
   */
  ensureContext(): Promise<void>;
  /** ONE fetch for the whole grid (§5.4), never a request per cell. */
  fetch(teamId: number | null): Promise<QualificationMatrix>;
  /**
   * Advisory: the server's opinion that the viewer coordinates the ministry, which
   * is what decides whether "Remove Volunteer" is OFFERED and nothing else. The
   * API authorizes independently (D5).
   */
  canRemoveVolunteer(): boolean;
  /** Ministry-level pool writes; omitted by a caller whose markup has no such buttons. */
  addPoolMember?(personId: number): Promise<{ added: boolean }>;
  addPoolFromCart?(): Promise<{ added: number; alreadyMembers: number }>;
  removeVolunteer?(personId: number): Promise<{ qualifications: number; assignments: number }>;
  /** What the "Remove {name} from …?" confirm names — the ministry. */
  removeScopeName?(): string;
  /** Re-fetch the caller's own document after a change that moved its counts. */
  reload(): Promise<void>;
}

export interface QualificationMatrixHandle {
  /** Render from cache, or fetch. `force` clears the cache first. */
  load(force?: boolean): Promise<void>;
  /** Drop the cached grid, so the next `load()` genuinely re-fetches. */
  invalidate(): void;
  /** The team the grid is showing, for a dialog title. */
  teamName(): string;
}

export function createQualificationMatrix(options: QualificationMatrixOptions): QualificationMatrixHandle {
  /**
   * The matrix is cached separately from the caller's own document because it is a
   * different document with a different filter (`?teamId=`) — but it is still ONE
   * fetch for the whole grid, never one per cell (§5.4).
   */
  let matrix: QualificationMatrix | null = null;
  /**
   * The team whose positions the grid is showing. There is no "all teams" answer:
   * a ministry's teams own their own positions, two teams may own a position of
   * the same name, and a grid spanning them made the columns ambiguous. Null only
   * before the first team is known.
   */
  let teamId: number | null = null;

  /**
   * The team the grid is showing, by name — the two Add dialogs put it in their
   * titles, because "Add Volunteer" on its own does not say to what.
   */
  function teamName(): string {
    return options.teams().find((team) => team.id === teamId)?.name ?? "";
  }

  /**
   * The team picker above the grid.
   *
   * It has no "all teams" entry: positions belong to teams, two teams under one
   * ministry may own a position of the same name, and a grid spanning them made the
   * columns ambiguous however they were labelled. The grid therefore always shows
   * exactly one team, and it starts on the first — the same order the caller's own
   * document uses.
   */
  function fillTeamFilter(): void {
    const teams = options.teams();
    if (teamId === null || !teams.some((team) => team.id === teamId)) {
      teamId = teams[0]?.id ?? null;
    }

    const select = byId<HTMLSelectElement>("qualification-team-filter");
    if (!select) {
      return;
    }

    select.textContent = "";
    for (const team of teams) {
      const option = document.createElement("option");
      option.value = String(team.id);
      option.textContent = team.name;
      select.append(option);
    }
    select.value = teamId === null ? "" : String(teamId);
  }

  /** Hide the rows whose name does not contain what was typed. Pure client-side. */
  function applyFilter(): void {
    const needle = byId<HTMLInputElement>("qualification-filter")?.value.trim().toLowerCase() ?? "";
    for (const row of document.querySelectorAll<HTMLTableRowElement>("#volunteerQualificationsTable tbody tr")) {
      const name = row.dataset.personName ?? "";
      row.hidden = needle !== "" && !name.includes(needle);
    }
  }

  function render(data: QualificationMatrix): void {
    const head = document.querySelector("#volunteerQualificationsTable thead tr");
    const body = document.querySelector("#volunteerQualificationsTable tbody");
    if (!head || !body) {
      return;
    }

    if (data.positions.length === 0 || data.people.length === 0) {
      head.innerHTML = `<th>${i18next.t("Volunteer")}</th>`;
      body.innerHTML = "";
      show(byId("volunteers-save-hint"), false);
      renderState("volunteers", "empty");

      return;
    }

    // "Remove Volunteer" is a ministry-level act, so it is offered to a ministry
    // coordinator and above and to nobody else. The flag is the server's opinion and
    // is advisory: the API refuses a team leader with 403 whatever this says (D5).
    const canRemove = options.canRemoveVolunteer() && options.removeVolunteer !== undefined;

    // "Elementary · Lead Teacher", not a bare "Lead Teacher", whenever the columns
    // come from more than one team — two teams under one ministry may own a position
    // of the same name, and the coordinator has to be able to tell the columns apart.
    // Once the filter names a single team the prefix is noise, so it is dropped.
    const matrixSpansTeams = new Set(data.positions.map((position: VolunteerPosition) => position.teamId)).size > 1;
    const columnLabel = (position: VolunteerPosition): string =>
      matrixSpansTeams ? positionLabel(position.teamName, position.name) : (position.name ?? "");

    head.innerHTML = [
      `<th>${i18next.t("Volunteer")}</th>`,
      ...data.positions.map(
        (position: VolunteerPosition) => `<th class="text-center">${escapeHtml(columnLabel(position))}</th>`,
      ),
      canRemove ? `<th class="text-center no-export w-1">${i18next.t("Actions")}</th>` : "",
    ].join("");

    body.innerHTML = data.people
      .map((person: VolunteerPoolPerson) => {
        const cells = data.positions
          .map((position: VolunteerPosition) => {
            const qualificationId = person.qualificationIds?.[String(position.id)] ?? 0;
            const checked = person.qualifications.includes(position.id) ? " checked" : "";

            // The cell is the checkbox and NOTHING else. It used to carry a status slot
            // beside the box — a spinner, then a green "Saved" badge for a second and a
            // half — and that badge is wider than a checkbox, so every tick widened its
            // column and shifted every box to the right of it. A save is confirmed in
            // the standard top-right notification instead, which is outside the table
            // and cannot move anything in it.
            return `<td class="text-center">
              <input type="checkbox" class="form-check-input volunteer-qual-toggle"${checked}
                     data-person-id="${person.personId}"
                     data-position-id="${position.id}"
                     data-qualification-id="${qualificationId}"
                     aria-label="${escapeAttribute(`${person.displayName} — ${columnLabel(position)}`)}">
            </td>`;
          })
          .join("");

        // D19: the rows are the pool UNION the qualified, so a row can be here for
        // either reason and the screen has to say which. A pool member with no ticks
        // yet is the one a coordinator opened this screen to deal with; a qualified
        // non-member was taken out of the group and is still assignable.
        //
        // For a pool member the badge is always RENDERED and merely hidden when it
        // does not apply, because a tick falsifies it there and then: the first tick
        // must be able to take "not qualified yet" away, and the last untick must be
        // able to bring it back, without re-rendering the grid under the coordinator.
        const hint = person.inPool
          ? ` <span class="badge bg-secondary-lt text-secondary volunteer-pool-hint"${
              person.qualifications.length === 0 ? "" : " hidden"
            }>${escapeHtml(i18next.t("In the pool, not qualified yet"))}</span>`
          : ` <span class="badge bg-secondary-lt text-secondary volunteer-outside-pool-hint">${escapeHtml(
              i18next.t("Not in the pool"),
            )}</span>`;

        const menu = canRemove
          ? `<td class="text-center w-1">${actionMenu([
              {
                type: "button",
                icon: "fa-solid fa-user-minus",
                label: i18next.t("Remove Volunteer"),
                className: "volunteer-remove-volunteer",
                danger: true,
                data: { "person-id": person.personId, "person-name": person.displayName },
              },
            ])}</td>`
          : "";

        return `<tr data-person-name="${escapeAttribute(person.displayName.toLowerCase())}">
          <td class="fw-bold">${escapeHtml(person.displayName)}${hint}</td>
          ${cells}
          ${menu}
        </tr>`;
      })
      .join("");

    renderState("volunteers", "loaded");
    // Only meaningful next to a grid that exists, so it is shown with the grid and
    // hidden with the empty and error states.
    show(byId("volunteers-save-hint"), true);
    applyFilter();
  }

  /**
   * Write one tick back into the cached matrix.
   *
   * This is the whole of the "the ticks do not persist" defect. The grid is cached
   * and re-rendered from that cache whenever the tab is activated again, so a save
   * that only changed the checkbox's DOM was thrown away by the next `render()` —
   * the write had reached the database and the screen was drawing a document that
   * predated it. Every save now updates the model the renderer reads, in exactly the
   * shape the server would have sent: an active qualification is in `qualifications`
   * and carries its row id in `qualificationIds`, and a revoked one is in neither.
   */
  function setCached(personId: number, positionId: number, qualificationId: number, active: boolean): void {
    const person = matrix?.people.find((row: VolunteerPoolPerson) => row.personId === personId);
    if (!person) {
      return;
    }

    const held = new Set(person.qualifications);
    if (active) {
      held.add(positionId);
      person.qualificationIds[String(positionId)] = qualificationId;
    } else {
      held.delete(positionId);
      delete person.qualificationIds[String(positionId)];
    }
    person.qualifications = [...held];
  }

  /**
   * "In the pool, not qualified yet" is a statement about the row that one tick
   * makes false and the last untick makes true again, so it is re-decided from the
   * cached model after every save rather than left until the next full render.
   */
  function refreshPoolHint(input: HTMLInputElement, personId: number): void {
    const person = matrix?.people.find((row: VolunteerPoolPerson) => row.personId === personId);
    const hint = input.closest("tr")?.querySelector<HTMLElement>(".volunteer-pool-hint");
    if (!person || !hint) {
      return;
    }

    hint.hidden = person.qualifications.length > 0;
  }

  /**
   * What one cell names, for the toast that confirms its save.
   *
   * "Espresso: Jane Doe qualified" says what happened without the coordinator having
   * to remember which of forty boxes they just clicked — a bare "Saved" in the corner
   * of the screen, several columns away from the box, does not. Both names come from
   * the cached matrix, which is the same document the grid was drawn from.
   */
  function cellNames(personId: number, positionId: number): { person: string; position: string } {
    return {
      person: matrix?.people.find((row: VolunteerPoolPerson) => row.personId === personId)?.displayName ?? "",
      position: matrix?.positions.find((row: VolunteerPosition) => row.id === positionId)?.name ?? "",
    };
  }

  async function load(force = false): Promise<void> {
    if (matrix !== null && !force) {
      render(matrix);

      return;
    }

    renderState("volunteers", "loading");
    show(byId("volunteers-save-hint"), false);
    try {
      // The caller's document names the teams, and the grid is always one team's —
      // so it has to be there before the first matrix request can name one.
      await options.ensureContext();
      fillTeamFilter();
      matrix = await options.fetch(teamId);
      render(matrix);
    } catch (error) {
      // Clearing the cache is what makes Retry a genuine retry (§5.8).
      matrix = null;
      show(byId("volunteers-save-hint"), false);
      renderState("volunteers", "error", errorMessage(error, i18next.t("Could not load the volunteers")));
    }
  }

  function invalidate(): void {
    matrix = null;
  }

  /**
   * "Remove Volunteer" on a row of the grid.
   *
   * One call, three effects, spelled out in the confirm because none of them is
   * guessable from the words "remove": the qualifications go, the upcoming
   * assignments are cancelled, and they leave the pool. The toast reports the
   * server's own counts rather than assuming what happened.
   */
  function wireRemoveVolunteer(): void {
    const remove = options.removeVolunteer;
    if (!remove) {
      return;
    }

    document.addEventListener("click", (event) => {
      const target = (event.target as HTMLElement | null)?.closest<HTMLElement>(".volunteer-remove-volunteer");
      if (!target) {
        return;
      }

      const personId = Number(target.dataset.personId);
      const personName = target.dataset.personName ?? "";

      confirmDelete(
        i18next.t("Remove Volunteer"),
        i18next.t(
          "Remove {{name}} from {{ministry}}? This removes all their qualifications here, takes them off every future occurrence, and removes them from the volunteer pool.",
          { name: personName, ministry: options.removeScopeName?.() ?? "" },
        ),
        () => {
          remove(personId)
            .then((result) => {
              notifySuccess(
                i18next.t("Removed. {{qualifications}} qualifications, {{assignments}} upcoming assignments.", {
                  qualifications: result.qualifications,
                  assignments: result.assignments,
                }),
              );
              matrix = null;

              // The pool count moved, so the caller's document is stale too.
              return options.reload().then(() => load(true));
            })
            .catch((error: unknown) => {
              notifyError(errorMessage(error, i18next.t("They could not be removed from this ministry")));
            });
        },
      );
    });
  }

  function wire(): void {
    byId("qualification-filter")?.addEventListener("input", applyFilter);

    byId("qualification-team-filter")?.addEventListener("change", (event) => {
      // Every option is a team now, so there is no "" to translate back to null.
      teamId = Number((event.target as HTMLSelectElement).value) || null;
      void load(true);
    });

    // ── "Add Volunteer" ────────────────────────────────────────────────────────
    //
    // The dialog used to grant a qualification and carried a position select to say
    // which. It does not any more: it puts the person in the ministry's POOL and
    // grants nothing, because being in the pool is candidacy and a tick on the grid
    // is eligibility (§2.5). Their row appears with no ticks, which is the prompt to
    // make the second statement.
    //
    // The shared person selector (CR1/#9819) rather than a hand-rolled TomSelect: it
    // owns the modal lifecycle, the body-mounted dropdown and the maxOptions fix.
    const personModal = byId("addVolunteerModal");
    const personPicker = personModal ? attachToModal(personModal, "#add-volunteer-person") : null;
    // Every dialog that closes ITSELF on a successful save needs the guard: a local
    // API answers inside Bootstrap's 150 ms fade, and `Modal.hide()` during the
    // transition is accepted and thrown away, leaving the dialog open over a screen
    // that has already been updated.
    wireModalFadeGuard("addVolunteerModal");
    wireModalFadeGuard("addFromCartModal");

    const addPoolMember = options.addPoolMember;
    if (addPoolMember) {
      byId("qualification-add-person")?.addEventListener("click", () => {
        show(byId("add-volunteer-form-error"), false);
        personPicker?.getInstance()?.clear();
        setModalTitle("addVolunteerModalTitle", i18next.t("Add Volunteer to {{team}}", { team: teamName() }));
        modal("addVolunteerModal")?.show();
      });

      byId("add-volunteer-save")?.addEventListener("click", () => {
        const personId = Number(personPicker?.getInstance()?.getValue() ?? 0);
        if (!personId) {
          showModalError("add-volunteer", i18next.t("Choose a person"), notifyError);

          return;
        }

        // Idempotent server-side, so "they were already here" is an ANSWER rather than
        // an error — and the grid is force-refreshed either way, because a coordinator
        // who has just named somebody expects to see them whichever answer came back.
        addPoolMember(personId)
          .then((result) => {
            hideModal("addVolunteerModal");
            notifySuccess(
              result.added
                ? i18next.t("Added — now tick the positions they can serve")
                : i18next.t("They were already a volunteer here"),
            );

            return options.reload().then(() => load(true));
          })
          .catch((error: unknown) => {
            showModalError("add-volunteer", errorMessage(error, i18next.t("They could not be added")), notifyError);
          });
      });
    }

    // ── "Add from Cart" ────────────────────────────────────────────────────────
    //
    // Same change, same reason: everyone in the cart joins the ministry's volunteers
    // and nobody is qualified for anything, so there is nothing to choose. ONE
    // request does the whole cart — thirty people should not be thirty round trips,
    // and a batch that half-completed is not a state this screen could describe.
    const addPoolFromCart = options.addPoolFromCart;
    if (addPoolFromCart) {
      byId("qualification-cart-btn")?.addEventListener("click", () => {
        show(byId("add-from-cart-form-error"), false);
        setModalTitle("addFromCartModalTitle", i18next.t("Add Everyone in Cart to {{team}}", { team: teamName() }));
        modal("addFromCartModal")?.show();
      });

      byId("add-from-cart-save")?.addEventListener("click", () => {
        addPoolFromCart()
          .then((result) => {
            hideModal("addFromCartModal");
            // The server's own counts, never an assumption: the cart may hold people
            // who were volunteers here already, and saying so is the honest answer.
            notifySuccess(
              i18next.t("{{added}} added, {{alreadyMembers}} were already volunteers here", {
                added: result.added,
                alreadyMembers: result.alreadyMembers,
              }),
            );

            return options.reload().then(() => load(true));
          })
          .catch((error: unknown) => {
            showModalError("add-from-cart", errorMessage(error, i18next.t("The cart could not be added")), notifyError);
          });
      });
    }

    /**
     * One cell. The tick is applied straight away and rolled back on failure with
     * a toast — the §5.4 "optimistic UI + toast on failure" rule. The grid is NOT
     * re-fetched on success: a coordinator ticking fifteen boxes should not pay
     * for fifteen reloads.
     *
     * What it DOES do on success is write the answer back into the cached document
     * the grid is re-rendered from. Without that the tick lived only in the DOM and
     * the next `render()` — which happens every time the tab is activated again —
     * drew a document that predated the save, which is what the product owner saw
     * as "it is not persisting".
     *
     * The save confirms itself in the standard top-right notification, naming the
     * position and the person. It used to be a badge in a slot beside the box, which
     * widened the column for a second and a half and shifted every checkbox to its
     * right — so the cell now holds the checkbox and nothing else, and nothing in the
     * grid moves when a write lands. The box is disabled for the duration of the
     * request, which is the only in-place signal left and costs no layout.
     */
    document.addEventListener("change", (event) => {
      const target = event.target as HTMLInputElement | null;
      if (!target?.classList.contains("volunteer-qual-toggle")) {
        return;
      }

      const personId = Number(target.dataset.personId);
      const positionId = Number(target.dataset.positionId);
      const qualificationId = Number(target.dataset.qualificationId ?? 0);
      const nowChecked = target.checked;
      const names = cellNames(personId, positionId);
      target.disabled = true;

      const confirmSaved = (): void => {
        target.disabled = false;
        notifySuccess(
          nowChecked
            ? i18next.t("{{position}}: {{name}} qualified", { position: names.position, name: names.person })
            : i18next.t("{{position}}: {{name}} no longer qualified", {
                position: names.position,
                name: names.person,
              }),
        );
      };

      const rollback = (error: unknown, fallback: string): void => {
        target.checked = !nowChecked;
        target.disabled = false;
        notifyError(errorMessage(error, fallback));
      };

      if (nowChecked) {
        grantQualification(positionId, personId)
          .then((result) => {
            // Remember the row id so unticking can revoke it with no lookup — on the
            // element for this render, and in the cache for every render after it.
            const savedId = result.qualification.id;
            target.dataset.qualificationId = String(savedId);
            setCached(personId, positionId, savedId, true);
            refreshPoolHint(target, personId);
            confirmSaved();
          })
          .catch((error: unknown) => rollback(error, i18next.t("The qualification could not be saved")));

        return;
      }

      if (!qualificationId) {
        // Nothing to revoke — the box was never really on. No toast either: nothing
        // happened, and a notification saying so would be noise.
        target.disabled = false;

        return;
      }

      revokeQualification(qualificationId)
        .then(() => {
          // Revocation is deactivation, so the row id stays valid: re-ticking
          // reactivates the same row rather than making a second one (§2.7). The
          // cache drops it, which is the shape the server would send — an inactive
          // qualification is in neither list.
          setCached(personId, positionId, qualificationId, false);
          target.dataset.qualificationId = "0";
          refreshPoolHint(target, personId);
          confirmSaved();
        })
        .catch((error: unknown) => rollback(error, i18next.t("The qualification could not be removed")));
    });

    wireRemoveVolunteer();
    // The qualification grid is the one table that scrolls sideways.
    wireUnclippedRowMenus("volunteers-table-wrapper");
  }

  wire();

  return { load, invalidate, teamName };
}
