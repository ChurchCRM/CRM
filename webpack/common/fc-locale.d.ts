/**
 * Types for `fc-locale.js`, which is plain JavaScript because it uses a webpack
 * dynamic-import context that TypeScript cannot express. `Calendar` is the only
 * thing `fullcalendar/all` exports a type for, so that is what this takes.
 */
import type { Calendar } from "fullcalendar/all";

export declare function applyFcLocale(cal: Calendar): Promise<void>;
