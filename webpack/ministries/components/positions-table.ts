/**
 * The Positions table and its Add / Edit dialog (design §5.4).
 *
 * Extracted verbatim from `webpack/ministries/ministry.ts` for #9868 so the Member
 * Portal's My Teams page offers a team leader the same positions table, the same
 * dialog and the same "Recruit Volunteers" switch, in the portal layout.
 *
 * §4.6 already lets a **team leader** create and edit positions in their own team
 * — `POST /api/ministries/ministries/{id}/positions` deliberately carries no
 * ministry entity middleware for exactly that reason — so nothing about the
 * authorization changed here either.
 *
 * The caller supplies the team list the dialog's Team select offers. On the
 * portal that list is the one team the page is about, which makes the select a
 * single fixed option rather than a choice.
 */

import {
  createPosition,
  deletePosition,
  errorMessage,
  listQualifications,
  notifyError,
  notifySuccess,
  updatePosition,
  type VolunteerPosition,
  type VolunteerTeam,
} from "../api";
import {
  actionMenu,
  byId,
  confirmDelete,
  destroyDataTable,
  escapeHtml,
  hideModal,
  initDataTable,
  modal,
  renderState,
  show,
  showModalError,
  statusBadge,
  tText,
  wireModalFadeGuard,
} from "./ui";

export interface PositionsTableOptions {
  /** The ministry a new position is created under. */
  ministryId(): number;
  /** The teams the dialog's Team select offers, first one first. */
  teams(): VolunteerTeam[];
  /** Every position the caller knows about — the source of the "next order" default. */
  positions(): VolunteerPosition[];
  /** Re-fetch the caller's own document after a write. */
  reload(): Promise<void>;
  /** A position is a column of the qualification grid, so a write invalidates it. */
  invalidateMatrix(): void;
}

export interface PositionsTableHandle {
  render(positions: VolunteerPosition[]): void;
  openModal(position?: VolunteerPosition): void;
}

/** The admin shell's cart, typed locally: the core bundle sets it, the portal does not. */
function cartManager(): { addPerson(ids: number[], options?: { showNotification?: boolean }): unknown } | null {
  const crm = (window as unknown as { CRM?: { cartManager?: unknown } }).CRM;
  const manager = crm?.cartManager as { addPerson?: unknown } | undefined;

  return manager && typeof manager.addPerson === "function"
    ? (manager as { addPerson(ids: number[], options?: { showNotification?: boolean }): unknown })
    : null;
}

function hasCart(): boolean {
  return cartManager() !== null;
}

export function createPositionsTable(options: PositionsTableOptions): PositionsTableHandle {
  /** Which record the modal is editing; 0 means "new". */
  let editingPositionId = 0;

  function render(positions: VolunteerPosition[]): void {
    const body = document.querySelector("#volunteerPositionsTable tbody");
    if (!body) {
      return;
    }

    destroyDataTable("volunteerPositionsTable");

    if (positions.length === 0) {
      body.innerHTML = "";
      renderState("positions", "empty");

      return;
    }

    body.innerHTML = positions
      .map((position: VolunteerPosition) => {
        const menu = actionMenu([
          {
            type: "button",
            icon: "fa-solid fa-pencil",
            label: i18next.t("Edit"),
            className: "volunteer-position-edit",
            data: { "position-id": position.id },
          },
          // Activate / Deactivate is the Active switch inside Edit (review, 2026-09-18).
          // "Add Volunteers to Cart" puts everyone qualified for the position in the
          // cart for a mailing; it needs the admin shell's cart, which the portal's
          // My Teams page does not load.
          ...(hasCart()
            ? [
                {
                  type: "button" as const,
                  icon: "fa-solid fa-cart-plus",
                  label: i18next.t("Add Volunteers to Cart"),
                  className: "volunteer-position-cart",
                  data: { "position-id": position.id, "position-name": position.name },
                },
              ]
            : []),
          { type: "divider" as const },
          {
            type: "button",
            icon: "fa-solid fa-trash",
            label: i18next.t("Delete"),
            className: "volunteer-position-delete",
            danger: true,
            data: { "position-id": position.id, "position-name": position.name },
          },
        ]);

        // A green check or an empty cell. No cross for "off": a column of red
        // crosses reads as a column of faults, and most positions are not
        // advertised. The icon carries its own label, because a tick with no text
        // is nothing at all to a screen reader.
        const recruitingLabel = i18next.t("Recruiting");
        const recruiting = position.recruiting
          ? `<i class="fa-solid fa-check text-success" aria-label="${escapeHtml(recruitingLabel)}" title="${escapeHtml(recruitingLabel)}"></i>`
          : "";

        const selfLabel = i18next.t("Self sign-up");
        const selfAssignable =
          position.selfAssignable !== false
            ? `<i class="fa-solid fa-check text-success" aria-label="${escapeHtml(selfLabel)}" title="${escapeHtml(selfLabel)}"></i>`
            : "";

        return `<tr>
          <td class="text-center">${position.order}</td>
          <td class="fw-bold">${escapeHtml(position.name)}</td>
          <td>${position.description ? escapeHtml(position.description) : '<span class="text-body-secondary">—</span>'}</td>
          <td>${escapeHtml(position.teamName ?? "")}</td>
          <td class="text-center volunteer-position-recruiting">${recruiting}</td>
          <td class="text-center volunteer-position-self-assignable">${selfAssignable}</td>
          <td class="text-center">${statusBadge(position.active)}</td>
          <td class="w-1">${menu}</td>
        </tr>`;
      })
      .join("");

    renderState("positions", "loaded");
    initDataTable("volunteerPositionsTable");
  }

  function openModal(position?: VolunteerPosition): void {
    editingPositionId = position?.id ?? 0;
    show(byId("position-form-error"), false);

    const name = byId<HTMLInputElement>("position-form-name");
    const description = byId<HTMLInputElement>("position-form-description");
    const order = byId<HTMLInputElement>("position-form-order");
    const active = byId<HTMLInputElement>("position-form-active");
    const recruiting = byId<HTMLInputElement>("position-form-recruiting");
    const selfAssignable = byId<HTMLInputElement>("position-form-self-assignable");
    const teamSelect = byId<HTMLSelectElement>("position-form-team");
    const title = byId("positionModalTitle");

    if (name) {
      name.value = position?.name ?? "";
    }
    if (description) {
      description.value = position?.description ?? "";
    }
    if (order) {
      order.value = String(position?.order ?? options.positions().length + 1);
    }
    if (active) {
      active.checked = position?.active ?? true;
    }
    // A NEW position never advertises itself: publishing is a decision, not a default.
    if (recruiting) {
      recruiting.checked = position?.recruiting ?? false;
    }
    if (selfAssignable) {
      selfAssignable.checked = position?.selfAssignable ?? true;
    }
    if (title) {
      title.textContent = position ? i18next.t("Edit position") : i18next.t("Add position");
    }

    if (teamSelect) {
      // No "no team" entry: a position always belongs to one, and a new position
      // starts on the first team offered.
      const teams = options.teams();
      teamSelect.textContent = "";
      for (const team of teams) {
        const option = document.createElement("option");
        option.value = String(team.id);
        option.textContent = team.name;
        teamSelect.append(option);
      }
      teamSelect.value = String(position?.teamId ?? teams[0]?.id ?? "");
    }

    modal("positionModal")?.show();
  }

  function save(): void {
    const name = byId<HTMLInputElement>("position-form-name")?.value.trim() ?? "";
    const description = byId<HTMLInputElement>("position-form-description")?.value.trim() ?? "";
    const orderValue = byId<HTMLInputElement>("position-form-order")?.value ?? "0";
    const active = byId<HTMLInputElement>("position-form-active")?.checked ?? true;
    const recruiting = byId<HTMLInputElement>("position-form-recruiting")?.checked ?? false;
    const selfAssignable = byId<HTMLInputElement>("position-form-self-assignable")?.checked ?? true;
    const teamValue = byId<HTMLSelectElement>("position-form-team")?.value ?? "";
    const teamId = Number(teamValue);

    if (name === "") {
      showModalError("position", i18next.t("Give the position a name"), notifyError);

      return;
    }
    if (!teamId) {
      showModalError("position", i18next.t("Choose the team this position serves on"), notifyError);

      return;
    }

    const saved =
      editingPositionId === 0
        ? createPosition(options.ministryId(), {
            name,
            description,
            teamId,
            order: Number(orderValue) || 0,
            recruiting,
            selfAssignable,
          })
        : updatePosition(editingPositionId, {
            name,
            description,
            teamId,
            order: Number(orderValue) || 0,
            active,
            recruiting,
            selfAssignable,
          });

    saved
      .then(() => {
        hideModal("positionModal");
        notifySuccess(editingPositionId === 0 ? i18next.t("Position added") : i18next.t("Position saved"));
        // A position IS a column of the qualification grid, so the grid's cached
        // document no longer describes the screen.
        options.invalidateMatrix();

        return options.reload();
      })
      .catch((error: unknown) => {
        showModalError("position", errorMessage(error, i18next.t("The position could not be saved")), notifyError);
      });
  }

  function findPosition(id: number): VolunteerPosition | undefined {
    return options.positions().find((position) => position.id === id);
  }

  function wire(): void {
    wireModalFadeGuard("positionModal");

    // Focus the first field once the modal has finished animating. Without it
    // Bootstrap's own `shown.bs.modal` handler moves focus to the dialog partway
    // through the 150 ms fade and swallows whatever was typed in the meantime.
    byId("positionModal")?.addEventListener("shown.bs.modal", () => {
      byId<HTMLInputElement>("position-form-name")?.focus();
    });

    byId("position-add-btn")?.addEventListener("click", () => openModal());
    byId("position-form-save")?.addEventListener("click", save);

    // Delegated: the rows are re-rendered on every load, so per-row listeners
    // would go stale.
    document.addEventListener("click", (event) => {
      const target = (event.target as HTMLElement | null)?.closest<HTMLElement>(
        ".volunteer-position-edit, .volunteer-position-delete, .volunteer-position-cart",
      );
      if (!target) {
        return;
      }

      if (target.classList.contains("volunteer-position-edit")) {
        openModal(findPosition(Number(target.dataset.positionId)));

        return;
      }

      if (target.classList.contains("volunteer-position-cart")) {
        const positionId = Number(target.dataset.positionId);
        listQualifications(positionId, true)
          .then((result) => {
            const ids = [...new Set(result.qualifications.map((q) => q.personId))];
            if (ids.length === 0) {
              notifyError(
                tText("Nobody is qualified for {{position}} yet", { position: target.dataset.positionName ?? "" }),
              );

              return;
            }
            // The core cart shows its own "N added" notification and refreshes the badge.
            cartManager()?.addPerson(ids, { showNotification: true });
          })
          .catch((error: unknown) => {
            notifyError(errorMessage(error, i18next.t("The qualified volunteers could not be loaded")));
          });

        return;
      }

      const positionId = Number(target.dataset.positionId);
      confirmDelete(
        i18next.t("Delete position"),
        i18next.t(
          "Delete {{name}}? Its qualifications, staffing needs and assignments — past service records included — are removed with it. To keep the history, deactivate the position instead. This cannot be undone.",
          { name: target.dataset.positionName ?? "" },
        ),
        () => {
          deletePosition(positionId)
            .then(() => {
              notifySuccess(i18next.t("Position deleted"));
              options.invalidateMatrix();

              return options.reload();
            })
            .catch((error: unknown) => {
              notifyError(errorMessage(error, i18next.t("The position could not be deleted")));
            });
        },
      );
    });
  }

  wire();

  return { render, openModal };
}
