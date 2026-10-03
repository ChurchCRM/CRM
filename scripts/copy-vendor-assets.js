#!/usr/bin/env node

/**
 * Copy the vendor files that are still loaded as plain script or locale URLs.
 * package.json build:js:legacy runs this. Everything else is imported by the webpack skin bundle.
 * Gruntfile.js used to do this copy and is removed.
 */

const fs = require("fs");
const path = require("path");

const root = path.resolve(__dirname, "..");

function copyFile(from, to) {
  fs.mkdirSync(path.dirname(to), { recursive: true });
  fs.copyFileSync(from, to);
}

function copyTree(fromDir, toDir) {
  fs.cpSync(fromDir, toDir, { recursive: true });
}

const nm = path.join(root, "node_modules");

copyFile(
  path.join(nm, "leaflet/dist/leaflet.js"),
  path.join(root, "src/skin/external/leaflet/leaflet.js"),
);
copyFile(
  path.join(nm, "leaflet/dist/leaflet.css"),
  path.join(root, "src/skin/external/leaflet/leaflet.css"),
);
copyTree(
  path.join(nm, "leaflet/dist/images"),
  path.join(root, "src/skin/external/leaflet/images"),
);

copyFile(
  path.join(nm, "bs-stepper/dist/js/bs-stepper.min.js"),
  path.join(root, "src/skin/external/bs-stepper/bs-stepper.min.js"),
);

const localeCopies = [
  ["datatables.net-plugins/i18n", "src/locale/vendor/datatables", (name) => name.endsWith(".json")],
  ["moment/locale", "src/locale/vendor/moment", (name) => name.endsWith(".js")],
  [
    "bootstrap-datepicker/dist/locales",
    "src/locale/vendor/bootstrap-datepicker",
    (name) => name.endsWith(".js"),
  ],
];

for (const [fromRel, toRel, keep] of localeCopies) {
  const fromDir = path.join(nm, fromRel);
  const toDir = path.join(root, toRel);
  fs.mkdirSync(toDir, { recursive: true });
  for (const name of fs.readdirSync(fromDir)) {
    if (!keep(name)) {
      continue;
    }
    const from = path.join(fromDir, name);
    if (fs.statSync(from).isFile()) {
      fs.copyFileSync(from, path.join(toDir, name));
    }
  }
}
