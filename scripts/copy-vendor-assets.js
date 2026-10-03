#!/usr/bin/env node
// Copies vendor files that PHP pages load as static assets.
// Replaces `grunt copy`.

const { cpSync, globSync, mkdirSync, statSync } = require("node:fs");
const path = require("node:path");

const root = path.resolve(__dirname, "..");

const jobs = [
    { src: ["node_modules/moment/min/moment.min.js"], dest: "src/skin/external/moment", flatten: true },
    { src: ["node_modules/bootbox/dist/bootbox.min.js"], dest: "src/skin/external/bootbox", flatten: true },
    {
        cwd: "node_modules/leaflet/dist",
        src: ["leaflet.js", "leaflet.css", "images/**"],
        dest: "src/skin/external/leaflet",
    },
    {
        src: ["node_modules/daterangepicker/daterangepicker.js"],
        dest: "src/skin/external/bootstrap-daterangepicker",
        flatten: true,
    },
    {
        src: [
            "node_modules/inputmask/dist/jquery.inputmask.min.js",
            "node_modules/inputmask/dist/bindings/inputmask.binding.js",
        ],
        dest: "src/skin/external/inputmask",
        flatten: true,
    },
    {
        src: ["node_modules/just-validate/dist/just-validate.production.min.js"],
        dest: "src/skin/external/just-validate",
        flatten: true,
    },
    {
        src: ["node_modules/bs-stepper/dist/js/bs-stepper.min.js"],
        dest: "src/skin/external/bs-stepper",
        flatten: true,
    },
    { src: ["node_modules/i18next/dist/umd/i18next.min.js"], dest: "src/skin/external/i18next", flatten: true },
    {
        src: ["node_modules/bootstrap-datepicker/dist/js/bootstrap-datepicker.min.js"],
        dest: "src/skin/external/bootstrap-datepicker",
        flatten: true,
    },
    { src: ["node_modules/datatables.net/js/dataTables.min.js"], dest: "src/skin/external/datatables", flatten: true },
    {
        src: ["node_modules/datatables.net-bs5/js/dataTables.bootstrap5.min.js"],
        dest: "src/skin/external/datatables",
        flatten: true,
    },
    {
        src: [
            "node_modules/datatables.net-buttons/js/dataTables.buttons.min.js",
            "node_modules/datatables.net-buttons-bs5/js/buttons.bootstrap5.min.js",
            "node_modules/datatables.net-buttons/js/buttons.html5.min.js",
            "node_modules/datatables.net-buttons/js/buttons.print.min.js",
        ],
        dest: "src/skin/external/datatables",
        flatten: true,
    },
    {
        src: [
            "node_modules/datatables.net-responsive/js/dataTables.responsive.min.js",
            "node_modules/datatables.net-responsive-bs5/js/responsive.bootstrap5.min.js",
        ],
        dest: "src/skin/external/datatables",
        flatten: true,
    },
    {
        src: [
            "node_modules/datatables.net-select/js/dataTables.select.min.js",
            "node_modules/datatables.net-select-bs5/js/select.bootstrap5.min.js",
        ],
        dest: "src/skin/external/datatables",
        flatten: true,
    },
    {
        cwd: "node_modules/datatables.net-plugins",
        src: ["i18n/*.json"],
        dest: "src/locale/vendor/datatables",
        flatten: true,
    },
    { cwd: "node_modules/moment", src: ["locale/*.js"], dest: "src/locale/vendor/moment", flatten: true },
    {
        cwd: "node_modules/bootstrap-datepicker/dist",
        src: ["locales/*.js", "locales/*.min.js"],
        dest: "src/locale/vendor/bootstrap-datepicker",
        flatten: true,
    },
];

function filesFor(pattern, cwd) {
    return globSync(pattern, { cwd }).filter((rel) => {
        try {
            return statSync(path.join(cwd, rel)).isFile();
        } catch {
            return false;
        }
    });
}

let copied = 0;
for (const job of jobs) {
    const cwd = path.join(root, job.cwd ?? ".");
    for (const pattern of job.src) {
        const matches = filesFor(pattern, cwd);
        if (matches.length === 0) {
            console.error(`copy-vendor-assets: no files for ${path.join(job.cwd ?? ".", pattern)}`);
            process.exitCode = 1;
            continue;
        }
        for (const rel of matches) {
            const from = path.join(cwd, rel);
            const to = job.flatten
                ? path.join(root, job.dest, path.basename(rel))
                : path.join(root, job.dest, rel);
            mkdirSync(path.dirname(to), { recursive: true });
            cpSync(from, to);
            copied += 1;
        }
    }
}

if (process.exitCode) {
    process.exit(process.exitCode);
}
console.log(`copy-vendor-assets: ${copied} files`);
