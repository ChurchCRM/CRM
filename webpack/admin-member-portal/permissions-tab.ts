/**
 * Admin → Member Portal → Permissions (member sign-in design §8.6): every
 * portal capability, core and plugin, with where it comes from and how many
 * people hold it. Opening one lists its holders and the source of each grant.
 */
import {
  escapeAttribute,
  escapeHtml,
  familyLink,
  getJSON,
  muted,
  onTabShown,
  type PageResult,
  personLink,
  root,
  serverTable,
  t,
} from "./server-table";

interface CapabilitySource {
  source: "everyone" | "rule" | "given";
  ruleKey: string | null;
  label: string;
}

interface Capability {
  key: string;
  label: string;
  description: string;
  owner: string;
  ownerName: string;
  scopeType: string;
  scopeLabel: string;
  status: string;
  statusLabel: string | null;
  statusUrl: string | null;
  sources: CapabilitySource[];
  holderCount: number;
  givenCount: number;
}

interface HolderGrant {
  source: string;
  sourceLabel: string;
  scope: { type: string; id: number; name: string | null };
}

interface Holder {
  personId: number;
  name: string;
  familyId: number | null;
  familyName: string;
  grants: HolderGrant[];
}

let capabilities: Capability[] = [];

function sourceBadges(capability: Capability): string {
  return capability.sources
    .map((source) => {
      const tone =
        source.source === "given"
          ? "bg-azure text-white"
          : source.source === "everyone"
            ? "bg-green text-white"
            : "bg-purple text-white";
      // The server lists "given" only when someone holds it that way; the number is how many people.
      const label = source.source === "given" ? `${source.label}: ${capability.givenCount}` : source.label;
      return `<span class="badge ${tone} me-1 mb-1">${escapeHtml(label)}</span>`;
    })
    .join("");
}

function statusBadge(capability: Capability): string {
  if (capability.status === "on") {
    return `<span class="badge bg-success text-white">${escapeHtml(t("On"))}</span>`;
  }
  const why = escapeHtml(capability.statusLabel ?? "");
  const reason = capability.statusUrl
    ? `<a href="${escapeAttribute(`${root()}${capability.statusUrl}`)}" data-cy="permission-off-switch">${why}</a>`
    : why;
  return `<span class="badge bg-secondary text-white" title="${escapeAttribute(capability.statusLabel ?? "")}">${escapeHtml(t("Off"))}</span>
    <div class="small text-secondary mt-1">${reason}</div>`;
}

function drawCatalog(): void {
  const body = document.querySelector<HTMLElement>("#portalPermissionsTable tbody");
  if (!body) {
    return;
  }
  body.innerHTML = capabilities
    .map(
      (capability) => `
      <tr data-capability="${escapeAttribute(capability.key)}">
        <td>
          <div class="fw-medium">${escapeHtml(capability.label)}</div>
          <div class="text-secondary small">${escapeHtml(capability.description)}</div>
          <div class="small mt-1"><code>${escapeHtml(capability.key)}</code>
            ${capability.owner === "core" ? "" : `<span class="badge bg-secondary text-white ms-1">${escapeHtml(capability.ownerName)}</span>`}</div>
          <div class="d-lg-none mt-1">${sourceBadges(capability)}</div>
        </td>
        <td class="d-none d-md-table-cell text-nowrap">${escapeHtml(capability.scopeLabel)}</td>
        <td class="d-none d-lg-table-cell">${sourceBadges(capability)}</td>
        <td>${statusBadge(capability)}</td>
        <td class="text-end">
          <button type="button" class="btn btn-sm btn-outline-primary portal-holders-open" data-capability="${escapeAttribute(capability.key)}"
                  aria-label="${escapeAttribute(t("People who hold it"))}">
            <i class="fa-solid fa-users me-1"></i><span data-cy="holder-count">${capability.holderCount}</span>
          </button>
        </td>
      </tr>`,
    )
    .join("");
}

export async function loadPermissionsCatalog(): Promise<void> {
  try {
    capabilities = (await getJSON<{ capabilities: Capability[] }>("member-portal/permissions")).capabilities;
  } catch (error) {
    const body = document.querySelector<HTMLElement>("#portalPermissionsTable tbody");
    if (body) {
      body.innerHTML = `<tr><td colspan="5" class="text-danger">${escapeHtml(error instanceof Error ? error.message : t("The permissions could not be loaded."))}</td></tr>`;
    }
    return;
  }
  drawCatalog();
}

function grantList(holder: Holder): string {
  return holder.grants
    .map((grant) => {
      const scope =
        grant.scope.type !== "none" && grant.scope.name
          ? ` <span class="text-secondary">· ${escapeHtml(grant.scope.name)}</span>`
          : "";
      return `<div>${escapeHtml(grant.sourceLabel)}${scope}</div>`;
    })
    .join("");
}

function openHolders(key: string): void {
  const capability = capabilities.find((candidate) => candidate.key === key);
  const modalElement = document.getElementById("portalHoldersModal");
  const table = document.getElementById("portalHoldersTable") as HTMLTableElement | null;
  if (!capability || !modalElement || !table) {
    return;
  }
  const title = document.getElementById("portalHoldersTitle");
  if (title) {
    title.textContent = capability.label;
  }
  const description = document.getElementById("portalHoldersDescription");
  if (description) {
    description.textContent = capability.description;
  }

  const modal = window.bootstrap.Modal.getOrCreateInstance(modalElement);
  modalElement.addEventListener(
    "shown.bs.modal",
    () => {
      serverTable<Holder>(
        table,
        [
          { title: t("Name"), render: (row) => personLink(row.personId, row.name), priority: 1 },
          { title: t("Family"), render: (row) => familyLink(row.familyId, row.familyName), priority: 4 },
          { title: t("Why"), render: (row) => (row.grants.length ? grantList(row) : muted("—")), priority: 3 },
          {
            title: "",
            render: (row) =>
              window.CRM?.renderPersonActionMenu?.(row.personId, row.name, { familyId: row.familyId ?? undefined }) ??
              "",
            className: "text-end w-1 no-export",
            priority: 2,
          },
        ],
        (start, length, search) =>
          getJSON<PageResult<Holder>>(`member-portal/permissions/${encodeURIComponent(key)}/holders`, {
            start,
            length,
            search,
          }),
      );
    },
    { once: true },
  );
  modal.show();
}

export function wirePermissionsTab(): void {
  const table = document.getElementById("portalPermissionsTable");
  if (!table) {
    return;
  }
  table.addEventListener("click", (event) => {
    const button = (event.target as HTMLElement).closest<HTMLButtonElement>(".portal-holders-open");
    if (button?.dataset.capability) {
      openHolders(button.dataset.capability);
    }
  });
  onTabShown("portal-permissions-tab", () => void loadPermissionsCatalog(), true);
}
