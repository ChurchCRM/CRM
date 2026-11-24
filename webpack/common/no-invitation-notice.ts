import { escapeHtml } from "../utils/escape-html";
import { alertAction } from "./confirm-action";

export interface CannotSignIn {
  message: string;
  members: { name: string; reason: string }[];
}

/** Tells staff an approval went through but sent no member portal invitation, and why. */
export function showNoInvitationNotice(cannotSignIn: CannotSignIn, onClose?: () => void): void {
  const members = cannotSignIn.members
    .map((member) => `<li>${escapeHtml(member.name)}: ${escapeHtml(member.reason)}</li>`)
    .join("");
  alertAction(
    i18next.t("Approved, but no invitation was sent"),
    `<p>${escapeHtml(cannotSignIn.message)}</p>${members ? `<ul class="mb-0">${members}</ul>` : ""}`,
    { onClose },
  );
}
