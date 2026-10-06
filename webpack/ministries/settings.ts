/**
 * Admin → Ministry Settings: the background-job times the page renders on the server,
 * in ChurchCRM's locale. The settings panel itself is `system-settings-panel`.
 */
import { formatTimeElements } from "./components/ui";

document.addEventListener("DOMContentLoaded", () => formatTimeElements());
