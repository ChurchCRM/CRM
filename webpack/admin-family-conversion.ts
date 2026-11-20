/**
 * Admin → Convert Individuals to Families: Convert Next gives the first person without a family a
 * family of their own, Convert All does it for everyone without one.
 */

import { confirmAction } from "./common/confirm-action";
import { escapeHtml } from "./utils/escape-html";

interface FamilyConversionConfig {
  unassignedCount: number;
  next: { id: number; name: string; url: string } | null;
  csrfToken: string;
}

const root = (): string => window.CRM?.root ?? "";
const config = (): FamilyConversionConfig => window.CRM?.familyConversion as FamilyConversionConfig;

function t(key: string, options?: Record<string, unknown>): string {
  if (typeof i18next === "undefined" || typeof i18next.t !== "function") {
    return key;
  }
  return i18next.t(key, { ...options, interpolation: { escapeValue: false } });
}

function setBusy(busy: boolean): void {
  for (const id of ["familyConversionConvertNext", "familyConversionConvertAll"]) {
    const button = document.getElementById(id) as HTMLButtonElement | null;
    if (button) {
      button.disabled = busy;
    }
  }
}

async function post(path: string): Promise<Record<string, unknown> | null> {
  setBusy(true);
  let response: Response;
  try {
    response = await fetch(`${root()}/admin/api/${path}`, {
      method: "POST",
      headers: { "content-type": "application/json", "X-CSRF-Token": config().csrfToken },
      body: "{}",
    });
  } catch {
    window.CRM?.notify?.(
      escapeHtml(t("The server could not be reached. Reload the page to see whether the conversion ran.")),
      { type: "error", delay: 5000 },
    );
    setBusy(false);
    return null;
  }
  const body = (await response.json().catch(() => null)) as Record<string, unknown> | null;
  if (!response.ok || body === null) {
    window.CRM?.notify?.(escapeHtml(String(body?.message || t("The conversion failed."))), {
      type: "error",
      delay: 5000,
    });
    setBusy(false);
    return null;
  }
  return body;
}

function reloadWith(param: string, value: number): void {
  window.location.assign(`${root()}/admin/people/convert-to-families?${param}=${value}`);
}

async function convertNext(): Promise<void> {
  const next = config().next;
  if (!next) {
    return;
  }
  const body = await post(`person/${next.id}/convert-to-family`);
  const family = body?.family as { id: number } | undefined;
  if (family) {
    reloadWith("family", family.id);
  }
}

function confirmConvertAll(): void {
  const { unassignedCount } = config();
  confirmAction(
    t("Convert All"),
    escapeHtml(
      t("Give each person without a family a family of their own? People without a family: {{total}}", {
        total: unassignedCount,
      }),
    ),
    () => {
      void post("people/convert-unassigned-to-families").then((body) => {
        if (body) {
          reloadWith("converted", Number(body.count ?? 0));
        }
      });
    },
    { tone: "warning", confirmLabel: t("Convert All"), cancelLabel: t("Cancel") },
  );
}

function start(): void {
  document.getElementById("familyConversionConvertNext")?.addEventListener("click", () => void convertNext());
  document.getElementById("familyConversionConvertAll")?.addEventListener("click", confirmConvertAll);
}

if (typeof window.CRM?.onLocalesReady === "function") {
  window.CRM.onLocalesReady(start);
} else if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", start);
} else {
  start();
}
