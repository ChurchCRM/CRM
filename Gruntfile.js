module.exports = function (grunt) {
    // Project configuration.
    grunt.initConfig({
        package: grunt.file.readJSON("package.json"),
        pkg: grunt.file.readJSON("package.json"),
        copy: {
            skin: {
                files: [
                    // includes files within path and its sub-directories
                    {
                        expand: true,
                        filter: "isFile",
                        flatten: true,
                        cwd: "node_modules/admin-lte",
                        src: [
                            "dist/css/*.min.*",
                            "dist/css/skins/**",
                            "dist/js/adminlte.min.js",
                        ],
                        dest: "src/skin/external/adminlte/",
                    },
                    {
                        expand: true,
                        filter: "isFile",
                        flatten: true,
                        src: ["node_modules/fullcalendar/index.global.min.js"],
                        dest: "src/skin/external/fullcalendar/",
                    },
                    // Moment.js core library
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
                        cwd: "node_modules/bootstrap/dist",
                        src: ["js/**"],
                        dest: "src/skin/external/bootstrap/",
                    },
                    {
                        expand: true,
                        filter: "isFile",
                        flatten: true,
                        src: [
                            "node_modules/bootstrap-toggle/css/bootstrap-toggle.css",
                            "node_modules/bootstrap-toggle/js/bootstrap-toggle.js",
                        ],
                        dest: "src/skin/external/bootstrap-toggle/",
                    },
                    {
                        expand: true,
                        filter: "isFile",
                        flatten: true,
                        cwd: "",
                        src: [
                            "node_modules/daterangepicker/daterangepicker.*",
                            "node_modules/daterangepicker/moment.min.js",
                        ],
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
                        src: [
                            "node_modules/just-validate/dist/just-validate.production.min.js",
                        ],
                        dest: "src/skin/external/just-validate/",
                    },
                    {
                        expand: true,
                        filter: "isFile",
                        flatten: true,
                        src: [
                            "node_modules/bs-stepper/dist/js/bs-stepper.min.js",
                            "node_modules/bs-stepper/dist/css/bs-stepper.min.css",
                        ],
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
                        src: [
                            "node_modules/bootstrap-datepicker/dist/js/bootstrap-datepicker.min.js",
                            "node_modules/bootstrap-datepicker/dist/css/bootstrap-datepicker.standalone.min.css",
                        ],
                        dest: "src/skin/external/bootstrap-datepicker",
                    },
                    {
                        expand: true,
                        filter: "isFile",
                        flatten: true,
                        src: [
                            "node_modules/select2/dist/js/select2.full.min.js",
                            "node_modules/select2/dist/css/select2.min.css",
                        ],
                        dest: "src/skin/external/select2",
                    },
                    // DataTables: Core library
                    {
                        expand: true,
                        filter: "isFile",
                        flatten: true,
                        src: [
                            "node_modules/datatables.net/js/jquery.dataTables.min.js",
                        ],
                        dest: "src/skin/external/datatables/",
                    },
                    // DataTables: Bootstrap 4 integration
                    {
                        expand: true,
                        filter: "isFile",
                        flatten: true,
                        src: [
                            "node_modules/datatables.net-bs4/js/dataTables.bootstrap4.min.js",
                            "node_modules/datatables.net-bs4/css/dataTables.bootstrap4.min.css",
                        ],
                        dest: "src/skin/external/datatables/",
                    },
                    // DataTables: Buttons extension
                    {
                        expand: true,
                        filter: "isFile",
                        flatten: true,
                        src: [
                            "node_modules/datatables.net-buttons/js/dataTables.buttons.min.js",
                            "node_modules/datatables.net-buttons-bs4/js/buttons.bootstrap4.min.js",
                            "node_modules/datatables.net-buttons/js/buttons.html5.min.js",
                            "node_modules/datatables.net-buttons/js/buttons.print.min.js",
                            "node_modules/datatables.net-buttons-bs4/css/buttons.bootstrap4.min.css",
                        ],
                        dest: "src/skin/external/datatables/",
                    },
                    // DataTables: Responsive extension
                    {
                        expand: true,
                        filter: "isFile",
                        flatten: true,
                        src: [
                            "node_modules/datatables.net-responsive/js/dataTables.responsive.min.js",
                            "node_modules/datatables.net-responsive-bs4/js/responsive.bootstrap4.min.js",
                            "node_modules/datatables.net-responsive-bs4/css/responsive.bootstrap4.min.css",
                        ],
                        dest: "src/skin/external/datatables/",
                    },
                    // DataTables: Select extension
                    {
                        expand: true,
                        filter: "isFile",
                        flatten: true,
                        src: [
                            "node_modules/datatables.net-select/js/dataTables.select.min.js",
                            "node_modules/datatables.net-select-bs4/js/select.bootstrap4.min.js",
                            "node_modules/datatables.net-select-bs4/css/select.bootstrap4.min.css",
                        ],
                        dest: "src/skin/external/datatables/",
                    },
                    // DataTables: Sort images
                    {
                        expand: true,
                        filter: "isFile",
                        flatten: false,
                        cwd: "node_modules/datatables.net-bs4",
                        src: ["images/**"],
                        dest: "src/skin/external/datatables/",
                    },
                    // PDF/Excel export dependencies
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
                    // JSZip for Excel export
                    {
                        expand: true,
                        filter: "isFile",
                        flatten: true,
                        src: [
                            "node_modules/jszip/dist/jszip.min.js",
                        ],
                        dest: "src/skin/external/datatables/",
                    },
                    // DataTables: Locale/i18n files
                    {
                        expand: true,
                        filter: "isFile",
                        flatten: true,
                        cwd: "node_modules/datatables.net-plugins",
                        src: ["i18n/*.json"],
                        dest: "src/locale/vendor/datatables/",
                    },
                    // Moment.js locale files
                    {
                        expand: true,
                        filter: "isFile",
                        flatten: true,
                        cwd: "node_modules/moment",
                        src: ["locale/*.js"],
                        dest: "src/locale/vendor/moment/",
                    },
                    // Bootstrap DatePicker locale files
                    {
                        expand: true,
                        filter: "isFile",
                        flatten: true,
                        cwd: "node_modules/bootstrap-datepicker/dist",
                        src: ["locales/*.js", "locales/*.min.js"],
                        dest: "src/locale/vendor/bootstrap-datepicker/",
                    },
                    // Select2 i18n files
                    {
                        expand: true,
                        filter: "isFile",
                        flatten: true,
                        cwd: "node_modules/select2/dist",
                        src: ["js/i18n/*.js"],
                        dest: "src/locale/vendor/select2/",
                    },
                    // FullCalendar locale files
                    {
                        expand: true,
                        filter: "isFile",
                        flatten: true,
                        cwd: "node_modules/@fullcalendar/core",
                        src: ["locales/*.global.min.js"],
                        dest: "src/locale/vendor/fullcalendar/",
                        rename: function (dest, src) {
                            // Remove .global.min suffix: el.global.min.js -> el.js
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

    grunt.registerTask(
        "patchDataTablesCSS",
        "Patches Absolute paths in DataTables CSS to relative Paths",
        function () {
            // Patch Bootstrap 4 DataTables CSS (from npm package)
            var filePath = "src/skin/external/datatables/dataTables.bootstrap4.min.css";
            if (grunt.file.exists(filePath)) {
                var fileContents = grunt.file.read(filePath);
                const pattern = /url\(\"\//gi;
                fileContents = fileContents.replace(pattern, 'url("');
                console.log("patched DataTables CSS files");
                grunt.file.write(filePath, fileContents);
            } else {
                console.log("DataTables CSS file not found: " + filePath);
            }
        },
    );

    grunt.loadNpmTasks("grunt-contrib-copy");
};
