module.exports = function (grunt) {
    var poLocales = function () {
        var locales = grunt.file.readJSON("src/locale/locales.json");
        var poEditorLocales = {};
        for (var key in locales) {
            var locale = locales[key];
            var poLocaleName = locale["poEditor"];
            poEditorLocales[poLocaleName] = locale["locale"];
        }
        return poEditorLocales;
    };

    var dataTablesLang = function () {
        var locales = grunt.file.readJSON("src/locale/locales.json");
        var DTLangs = [];
        for (var key in locales) {
            var locale = locales[key];
            DTLangs.push(locale["dataTables"]);
        }
        return DTLangs.toString();
    };

    var momentLangs = function () {
        var locales = grunt.file.readJSON("src/locale/locales.json");
        var momentFiles = ["node_modules/moment/min/moment.min.js"];
        for (var key in locales) {
            var locale = locales[key];
            // Only include locale if momentLocale is defined AND the file exists
            if (locale["momentLocale"]) {
                var filePath = "node_modules/moment/locale/" + locale["momentLocale"] + ".js";
                if (grunt.file.exists(filePath)) {
                    momentFiles.push(filePath);
                }
            }
        }
        return momentFiles;
    };

    var dataTablesVer = "1.13.8";

    // Project configuration.
    grunt.initConfig({
        package: grunt.file.readJSON("package.json"),
        pkg: grunt.file.readJSON("package.json"),
        buildConfig: (function () {
            try {
                grunt.log.writeln("Using BuildConfig.json");
                return grunt.file.readJSON("BuildConfig.json");
            } catch (e) {
                grunt.log.writeln("BuildConfig.json not found, using defaults");
                return grunt.file.readJSON("BuildConfig.json.example");
            }
        })(),
        projectFiles: [
            "**",
            "**/.*",
            "!**/.gitignore",
            "!vendor/**/example/**",
            "!vendor/**/tests/**",
            "!vendor/**/docs/**",
            "!Images/{Family,Person}/**/*.{jpg,jpeg,png}",
            "!composer.lock",
            "!Include/Config.php",
            "!integrityCheck.json",
            "!logs/*.log",
            "!vendor/endroid/qr-code/assets/fonts/noto_sans.otf", // This closes #5099, but TODO: when https://github.com/endroid/qr-code/issues/224 is fixed, we can remove this exclusion.
        ],
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
                    {
                        expand: true,
                        filter: "isFile",
                        flatten: true,
                        src: momentLangs(),
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
                            "node_modules/bootstrap-validator/dist/validator.min.js",
                        ],
                        dest: "src/skin/external/bootstrap-validator/",
                    },
                    {
                        expand: true,
                        filter: "isFile",
                        flatten: true,
                        src: ["node_modules/jquery/dist/jquery.min.js"],
                        dest: "src/skin/external/jquery/",
                    },
                    {
                        expand: true,
                        filter: "isFile",
                        flatten: true,
                        src: [
                            "node_modules/jquery-steps/build/jquery.steps.min.js",
                            "node_modules/jquery-steps/demo/css/jquery.steps.css",
                        ],
                        dest: "src/skin/external/jquery.steps/",
                    },
                    {
                        expand: true,
                        filter: "isFile",
                        flatten: true,
                        src: [
                            "node_modules/jquery-validation/dist/jquery.validate.min.js",
                        ],
                        dest: "src/skin/external/jquery-validation/",
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
                        src: ["node_modules/pace/pace.js"],
                        dest: "src/skin/external/pace/",
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
                            "node_modules/bootstrap-show-password/dist/bootstrap-show-password.min.js",
                        ],
                        dest: "src/skin/external/bootstrap-show-password",
                    },
                    {
                        expand: true,
                        filter: "isFile",
                        flatten: true,
                        src: [
                            "node_modules/bootstrap-notify/bootstrap-notify.min.js",
                        ],
                        dest: "src/skin/external/bootstrap-notify",
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
                        dest: "src/skin/external/datatables/DataTables-" + dataTablesVer + "/",
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
                ],
            },
        },
        "curl-dir": {
            jqueryuicss: {
                src: [
                    "https://ajax.googleapis.com/ajax/libs/jqueryui/1.12.1/themes/base/jquery-ui.css",
                    "https://ajax.googleapis.com/ajax/libs/jqueryui/1.12.1/jquery-ui.min.js",
                ],
                dest: "src/skin/external/jquery-ui/",
            },
            // DataTables locale files still come from CDN (no npm package available)
            datatables_locale: {
                src: [
                    "https://cdn.datatables.net/plug-ins/" +
                        dataTablesVer +
                        "/i18n/{" +
                        dataTablesLang() +
                        "}.json",
                ],
                dest: "src/locale/datatables",
            },
        },
        compress: {
            zip: {
                options: {
                    archive: "temp/ChurchCRM-<%= package.version %>.zip",
                    mode: "zip",
                    pretty: true,
                },
                files: [
                    {
                        expand: true,
                        cwd: "src/",
                        src: "<%= projectFiles %>",
                        dest: "churchcrm/",
                    },
                ],
            }
        },
        generateSignatures: {
            sign: {
                version: "<%= package.version %>",
                files: [
                    {
                        expand: true,
                        cwd: "src/",
                        src: [
                            "**/*.php",
                            "**/*.js",
                            "!**/.htaccess",
                            "!**/.gitignore",
                            "!vendor/**/example/**",
                            "!vendor/**/tests/**",
                            "!vendor/**/docs/**",
                            "!Images/Person/thumbnails/*.jpg",
                            "!composer.lock",
                            "!Include/Config.php",
                            "!propel/propel.php",
                            "!integrityCheck.json",
                        ],
                        dest: "churchcrm/",
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

    grunt.registerMultiTask(
        "generateSignatures",
        "Generates SHA1 signatures of the release archive",
        function () {
            var sha1 = require("node-sha1");
            var signatures = {
                version: this.data.version,
                files: [],
            };
            this.files.forEach(function (filePair) {
                var isExpandedPair = filePair.orig.expand || false;

                filePair.src.forEach(function (src) {
                    if (grunt.file.isFile(src)) {
                        signatures.files.push({
                            filename: src.substring(4),
                            sha1: sha1(
                                grunt.file.read(src, { encoding: null }),
                            ),
                        });
                    }
                });
            });
            signatures.sha1 = sha1(JSON.stringify(signatures.files));
            grunt.file.write("src/signatures.json", JSON.stringify(signatures));
        },
    );

    grunt.registerTask("genLocaleJSFiles", "", function () {
        var locales = grunt.file.readJSON("src/locale/locales.json");
        for (var key in locales) {
            let localeConfig = locales[key];
            let locale = localeConfig["locale"];
            let languageCode = localeConfig["languageCode"];
            let enableFullCalendar = localeConfig["fullCalendar"];
            let enableDatePicker = localeConfig["datePicker"];
            let enableSelect2 = localeConfig["select2"];
            let momentLocale = localeConfig["momentLocale"];

            let tempFile = "locale/JSONKeys/" + locale + ".json";
            let poTerms = "{}";
            if (grunt.file.exists(tempFile)) {
                poTerms = grunt.file.read(tempFile);
                if (poTerms === "") { 
                    poTerms = "{}";
                }
            }
            let jsFileContent = "// Source POEditor: " + tempFile;
            jsFileContent =
                jsFileContent +
                "\ntry {window.CRM.i18keys = " +
                poTerms +
                ";} catch(e) {}\n";

            if (momentLocale) {
                tempFile = "node_modules/moment/locale/" + momentLocale + ".js";
                if (grunt.file.exists(tempFile)) {
                    let momentLocaleFile = grunt.file.read(tempFile);
                    jsFileContent = jsFileContent + "\n// Source moment: " + tempFile;
                    jsFileContent = jsFileContent + "\n" + "try {" + momentLocaleFile + "} catch(e) {}\n";
                }
            }

            if (enableFullCalendar) {
                let tempLangCode = languageCode.toLowerCase();
                if (localeConfig.hasOwnProperty("fullCalendarLocale")) {
                    tempLangCode = localeConfig["fullCalendarLocale"];
                }
                tempFile =
                    "node_modules/@fullcalendar/core/locales/" +
                    tempLangCode +
                    ".js";
                let fullCalendar = grunt.file.read(tempFile);
                jsFileContent =
                    jsFileContent + "\n// Source fullcalendar: " + tempFile;
                jsFileContent =
                    jsFileContent +
                    "\n" +
                    "try {" +
                    fullCalendar +
                    "} catch(e) {}\n";
            }
            if (enableDatePicker) {
                tempFile =
                    "node_modules/bootstrap-datepicker/dist/locales/bootstrap-datepicker." +
                    languageCode +
                    ".min.js";
                let datePicker = grunt.file.read(tempFile);
                jsFileContent =
                    jsFileContent + "\n// Source datepicker: " + tempFile;
                jsFileContent =
                    jsFileContent +
                    "\n" +
                    "try {" +
                    datePicker +
                    "} catch(e) {}\n";
            }
            if (enableSelect2) {
                tempFile =
                    "node_modules/select2/dist/js/i18n/" + languageCode + ".js";
                jsFileContent =
                    jsFileContent + "\n// Source select2: " + tempFile;
                let select2 = grunt.file.read(tempFile);
                jsFileContent =
                    jsFileContent + "\n" + "try {" + select2 + "} catch(e) {}";
            }
            grunt.file.write("src/locale/js/" + locale + ".js", jsFileContent);
        }
    });

    grunt.loadNpmTasks("grunt-contrib-copy");
    grunt.loadNpmTasks("grunt-contrib-compress");
    grunt.loadNpmTasks("grunt-curl");
};
