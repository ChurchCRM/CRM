/**
 * Stylesheets imported for their side effect.
 *
 * webpack extracts them into the entry's `.min.css`; TypeScript only needs to
 * know the import is legitimate.
 */
declare module "*.scss";
declare module "*.css";
