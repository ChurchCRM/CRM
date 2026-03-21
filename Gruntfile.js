module.exports = function (grunt) {
    grunt.initConfig({
        package: grunt.file.readJSON("package.json"),
        pkg: grunt.file.readJSON("package.json"),
        copy: {
            skin: {
                files: [
                    {
                        expand: true,
                        filter: "isFile",
                        flatten: true,
                        src: ["node_modules/fullcalendar/index.global.min.js"],
                        dest: "src/skin/external/fullcalendar/",
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
                        src: ["node_modules/select2/dist/js/select2.full.min.js"],
                        dest: "src/skin/external/select2",
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
                        src: [
                            "node_modules/pdfmake/build/pdfmake.min.js",
                            "node_modules/pdfmake/build/pdfmake.min.js.map",
                            "node_modules/pdfmake/build/vfs_fonts.js",
                        ],
                        dest: "src/skin/external/datatables/",
                    },
                    {
                        expand: true,
                        filter: "isFile",
                        flatten: true,
                        src: ["node_modules/jszip/dist/jszip.min.js"],
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
                        expand: true,
                        filter: "isFile",
                        flatten: true,
                        cwd: "node_modules/select2/dist",
                        src: ["js/i18n/*.js"],
                        dest: "src/locale/vendor/select2/",
                    },
                    {
                        expand: true,
                        filter: "isFile",
                        flatten: true,
                        cwd: "node_modules/@fullcalendar/core",
                        src: ["locales/*.global.min.js"],
                        dest: "src/locale/vendor/fullcalendar/",
                        rename: function (dest, src) {
                            return dest + src.replace(/\.global\.min\.js$/, ".js");
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
