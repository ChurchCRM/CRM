module.exports = function (grunt) {
    grunt.initConfig({
        package: grunt.file.readJSON("package.json"),
        pkg: grunt.file.readJSON("package.json"),
        copy: {
            skin: {
                files: [
                    {
                        // FullCalendar v7: all-in-one global bundle (includes forma theme JS).
                        // v6 shipped index.global.min.js; v7 ships all/global.js.
                        expand: true,
                        filter: "isFile",
                        flatten: true,
                        src: ["node_modules/fullcalendar/all/global.js"],
                        dest: "src/skin/external/fullcalendar/",
                        rename: function () {
                            return "src/skin/external/fullcalendar/index.global.js";
                        },
                    },
                    {
                        // FullCalendar v7: CSS is no longer auto-injected by the global bundle.
                        // Load skeleton (structure), Forma theme, and blue palette explicitly.
                        expand: true,
                        filter: "isFile",
                        flatten: true,
                        src: [
                            "node_modules/fullcalendar/skeleton.css",
                            "node_modules/fullcalendar/themes/forma/theme.css",
                            "node_modules/fullcalendar/themes/forma/palettes/blue.css",
                        ],
                        dest: "src/skin/external/fullcalendar/",
                        rename: function (dest, src) {
                            const map = {
                                "theme.css": "forma-theme.css",
                                "blue.css": "forma-palette-blue.css",
                            };
                            return dest + (map[src] || src);
                        },
                    },
                    {
                        // temporal-polyfill: sets globalThis.Temporal — required by FullCalendar v7.
                        // Must be loaded before the FullCalendar script.
                        expand: true,
                        filter: "isFile",
                        flatten: true,
                        src: ["node_modules/temporal-polyfill/global.js"],
                        dest: "src/skin/external/temporal-polyfill/",
                    },
                    {
                        expand: true,
                        filter: "isFile",
                        flatten: true,
                        src: ["node_modules/moment/min/moment.min.js"],
                        dest: "src/skin/external/moment/",
                    },
                    {
                        expand: true,
                        filter: "isFile",
                        flatten: true,
                        src: ["node_modules/bootbox/dist/bootbox.min.js"],
                        dest: "src/skin/external/bootbox/",
                    },
                    {
                        expand: true,
                        cwd: "node_modules/leaflet/dist",
                        src: ["leaflet.js", "leaflet.css", "images/**"],
                        dest: "src/skin/external/leaflet/",
                    },
                    {
                        expand: true,
                        filter: "isFile",
                        flatten: true,
                        src: ["node_modules/daterangepicker/daterangepicker.js"],
                        dest: "src/skin/external/bootstrap-daterangepicker/",
                    },
                    {
                        expand: true,
                        filter: "isFile",
                        flatten: true,
                        src: [
                            "node_modules/inputmask/dist/jquery.inputmask.min.js",
                            "node_modules/inputmask/dist/bindings/inputmask.binding.js",
                        ],
                        dest: "src/skin/external/inputmask/",
                    },
                    {
                        expand: true,
                        filter: "isFile",
                        flatten: true,
                        src: ["node_modules/just-validate/dist/just-validate.production.min.js"],
                        dest: "src/skin/external/just-validate/",
                    },
                    {
                        expand: true,
                        filter: "isFile",
                        flatten: true,
                        src: ["node_modules/bs-stepper/dist/js/bs-stepper.min.js"],
                        dest: "src/skin/external/bs-stepper/",
                    },
                    {
                        expand: true,
                        filter: "isFile",
                        flatten: true,
                        src: ["node_modules/chart.js/dist/chart.umd.js"],
                        dest: "src/skin/external/chartjs/",
                    },
                    {
                        expand: true,
                        filter: "isFile",
                        flatten: true,
                        src: ["node_modules/i18next/dist/umd/i18next.min.js"],
                        dest: "src/skin/external/i18next/",
                    },
                    {
                        expand: true,
                        filter: "isFile",
                        flatten: true,
                        src: ["node_modules/bootstrap-datepicker/dist/js/bootstrap-datepicker.min.js"],
                        dest: "src/skin/external/bootstrap-datepicker",
                    },
                    {
                        expand: true,
                        filter: "isFile",
                        flatten: true,
                        src: ["node_modules/datatables.net/js/dataTables.min.js"],
                        dest: "src/skin/external/datatables/",
                    },
                    {
                        expand: true,
                        filter: "isFile",
                        flatten: true,
                        src: ["node_modules/datatables.net-bs5/js/dataTables.bootstrap5.min.js"],
                        dest: "src/skin/external/datatables/",
                    },
                    {
                        expand: true,
                        filter: "isFile",
                        flatten: true,
                        src: [
                            "node_modules/datatables.net-buttons/js/dataTables.buttons.min.js",
                            "node_modules/datatables.net-buttons-bs5/js/buttons.bootstrap5.min.js",
                            "node_modules/datatables.net-buttons/js/buttons.html5.min.js",
                            "node_modules/datatables.net-buttons/js/buttons.print.min.js",
                        ],
                        dest: "src/skin/external/datatables/",
                    },
                    {
                        expand: true,
                        filter: "isFile",
                        flatten: true,
                        src: [
                            "node_modules/datatables.net-responsive/js/dataTables.responsive.min.js",
                            "node_modules/datatables.net-responsive-bs5/js/responsive.bootstrap5.min.js",
                        ],
                        dest: "src/skin/external/datatables/",
                    },
                    {
                        expand: true,
                        filter: "isFile",
                        flatten: true,
                        src: [
                            "node_modules/datatables.net-select/js/dataTables.select.min.js",
                            "node_modules/datatables.net-select-bs5/js/select.bootstrap5.min.js",
                        ],
                        dest: "src/skin/external/datatables/",
                    },
                    {
                        expand: true,
                        filter: "isFile",
                        flatten: false,
                        cwd: "node_modules/datatables.net-bs5",
                        src: ["images/**"],
                        dest: "src/skin/external/datatables/",
                    },
                    {
                        expand: true,
                        filter: "isFile",
                        flatten: true,
                        cwd: "node_modules/datatables.net-plugins",
                        src: ["i18n/*.json"],
                        dest: "src/locale/vendor/datatables/",
                    },
                    {
                        expand: true,
                        filter: "isFile",
                        flatten: true,
                        cwd: "node_modules/moment",
                        src: ["locale/*.js"],
                        dest: "src/locale/vendor/moment/",
                    },
                    {
                        expand: true,
                        filter: "isFile",
                        flatten: true,
                        cwd: "node_modules/bootstrap-datepicker/dist",
                        src: ["locales/*.js", "locales/*.min.js"],
                        dest: "src/locale/vendor/bootstrap-datepicker/",
                    },
                    {
                        // FullCalendar v7: locales moved from @fullcalendar/core/locales/*.global.min.js
                        // to fullcalendar/locales/<lang>/global.js (one directory per locale).
                        // rename: "en-gb/global.js" -> "en-gb.js" by stripping /global.js suffix.
                        expand: true,
                        filter: "isFile",
                        cwd: "node_modules/fullcalendar/locales",
                        src: ["*/global.js"],
                        dest: "src/locale/vendor/fullcalendar/",
                        rename: function (dest, src) {
                            return dest + src.replace(/\/global\.js$/, ".js");
                        },
                    },
                ],
            },
        },
    });

    grunt.registerTask("hash", "gets a file hash", function (arg1) {
        var sha1 = require("node-sha1");
        grunt.log.writeln(sha1(grunt.file.read(arg1, { encoding: null })));
    });

    grunt.loadNpmTasks("grunt-contrib-copy");
};
