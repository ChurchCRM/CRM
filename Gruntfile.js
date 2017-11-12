module.exports = function (grunt) {

    var poLocales = function() {
        var locales = grunt.file.readJSON("src/locale/locales.json");
        var poEditorLocales = {};
        for (var key in locales ) {
            var locale = locales[key];
            var poLocaleName = locale["poEditor"];
            poEditorLocales[poLocaleName] = locale["locale"];
        }
        return poEditorLocales;
    };

    var dataTablesLang = function() {
        var locales = grunt.file.readJSON("src/locale/locales.json");
        var DTLangs = [];
        for (var key in locales ) {
            var locale = locales[key];
            DTLangs.push(locale["dataTables"]);

        }
        return DTLangs.toString();
    };

// Project configuration.
    grunt.initConfig({
        package: grunt.file.readJSON('package.json'),
        pkg: grunt.file.readJSON('package.json'),
        buildConfig: grunt.file.readJSON('BuildConfig.json'),
        projectFiles: [
            '**',
            '**/.*',
            '!**/.gitignore',
            '!vendor/**/example/**',
            '!vendor/**/tests/**',
            '!vendor/**/docs/**',
            '!Images/{Family,Person}/thumbnails/*.{jpg,jpeg,png}',
            //'!Images/{Family,Person}/*.{jpg,jpeg,png}',
            '!composer.lock',
            '!Include/Config.php',
            '!integrityCheck.json',
            '!logs/*.log'
        ],
        clean: {
            skin: ["src/skin/{adminlte,font-awesome,ionicons,fullcalendar,moment,fastclick}"],
            release: ["target"]
        },
        copy: {
            skin: {
                files: [
                    // includes files within path and its sub-directories
                    {
                        expand: true,
                        cwd: 'node_modules/admin-lte',
                        src: [
                            '{dist,bootstrap,plugins}/**',
                            '!dist/img',
                            '!plugins/**/*.md',
                            '!plugins/**/examples/**',
                            '!plugins/fullcalendar/**',
                            '!plugins/moment/**',
                            '!plugins/fastclick/**',
                            '!plugins/bootstrap-wysihtml5/**',
                            '!plugins/ckeditor/**',
                            '!plugins/morris/**',
                            '!dist/img/**',
                            '!plugins/**/psd/**'],
                        dest: 'src/skin/adminlte/'
                    },
                    {
                        expand: true,
                        cwd: 'node_modules/font-awesome',
                        src: ['{css,fonts,less,scss}/**'],
                        dest: 'src/skin/font-awesome/'
                    },
                    {
                        expand: true,
                        cwd: 'node_modules/ionicons',
                        src: ['{css,fonts,less,png}/**'],
                        dest: 'src/skin/ionicons/'
                    },
                    {
                        expand: true,
                        filter: 'isFile',
                        flatten: true,
                        src: ['node_modules/fullcalendar/dist/*'],
                        dest: 'src/skin/fullcalendar/'
                    },
                    {
                        expand: true,
                        filter: 'isFile',
                        flatten: true,
                        src: ['node_modules/moment/min/*'],
                        dest: 'src/skin/moment/'
                    },
                    {
                        expand: true,
                        filter: 'isFile',
                        flatten: true,
                        src: ['node_modules/jquery-photo-uploader/dist/*'],
                        dest: 'src/skin/jquery-photo-uploader/'
                    },
                    {
                        expand: true,
                        filter: 'isFile',
                        flatten: true,
                        src: ['node_modules/randomcolor/randomColor.js'],
                        dest: 'src/skin/randomcolor/'
                    },
                    {
                        expand: true,
                        filter: 'isFile',
                        flatten: true,
                        src: ['node_modules/bootbox/bootbox.min.js'],
                        dest: 'src/skin/bootbox/'
                    },
                    {
                        expand: true,
                        filter: 'isFile',
                        flatten: true,
                        src: ['node_modules/bootstrap-toggle/css/bootstrap-toggle.css', 'node_modules/bootstrap-toggle/js/bootstrap-toggle.js'],
                        dest: 'src/skin/bootstrap-toggle/'
                    },
                    {
                        expand: true,
                        filter: 'isFile',
                        flatten: true,
                        src: ['node_modules/bootstrap-validator/dist/validator.min.js'],
                        dest: 'src/skin/bootstrap-validator/'
                    },
                    {
                        expand: true,
                        filter: 'isFile',
                        flatten: true,
                        src: ['node_modules/jquery-steps/build/jquery.steps.min.js', 'node_modules/jquery-steps/demo/css/jquery.steps.css'],
                        dest: 'src/skin/external/jquery.steps/'
                    },
                    {
                        expand: true,
                        filter: 'isFile',
                        flatten: true,
                        src: ['node_modules/jquery-validation/dist/jquery.validate.min.js'],
                        dest: 'src/skin/external/jquery-validation/'
                    },
                    {
                        expand: true,
                        filter: 'isFile',
                        flatten: true,
                        src: ['node_modules/i18next/dist/umd/i18next.min.js'],
                        dest: 'src/skin/i18next/'
                    },
                    {
                        expand: true,
                        filter: 'isFile',
                        flatten: true,
                        src: ['node_modules/i18next-xhr-backend/dist/umd/i18nextXHRBackend.min.js'],
                        dest: 'src/skin/i18next/'
                    }
                ]
            }
        },
        'curl-dir': {
            datatables: {
                src: ['https://cdn.datatables.net/plug-ins/1.10.12/i18n/{'+dataTablesLang()+'}.json'],
                dest: 'src/locale/datatables'
            },
            fastclick: {
                src: ['https://raw.githubusercontent.com/ftlabs/fastclick/569732a7aa5861d428731b8db022b2d55abe1a5a/lib/fastclick.js'],
                dest: 'src/skin/fastclick'
            },
            jqueryuicss: {
                src: ['https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css'],
                dest: 'src/skin/jquery-ui/'
            },
            datatableselect: {
                src: [
                    'https://cdn.datatables.net/select/1.2.2/css/select.bootstrap.min.css', 
                    'https://cdn.datatables.net/select/1.2.2/js/dataTables.select.min.js'
                ],
                dest: 'src/skin/adminlte/plugins/datatables/extensions/Select/'
            }
        },
        sass: {
            dist: {
                files: {
                    'src/skin/churchcrm.min.css': 'src/skin/churchcrm.scss'
                }
            }
        },
        compress: {
            'zip': {
                options: {
                    archive: 'target/ChurchCRM-<%= package.version %>.zip',
                    mode: "zip",
                    pretty: true
                },
                files: [
                    {
                        expand: true,
                        cwd: 'src/',
                        src: '<%= projectFiles %>',
                        dest: 'churchcrm/'
                    }
                ]
            },
            'tar': {
                options: {
                    archive: 'target/ChurchCRM-<%= package.version %>.tar.gz',
                    mode: "tgz",
                    pretty: true
                },
                files: [
                    {
                        expand: true,
                        cwd: 'src/',
                        src: '<%= projectFiles %>',
                        dest: 'churchcrm/'
                    }
                ]
            },
            'demo': {
                options: {
                    archive: 'target/Demo-ChurchCRM-<%= package.version %>.tar.gz',
                    mode: "tar",
                    pretty: true
                },
                files: [
                    {
                        expand: true,
                        cwd: 'demo/',
                        src: [
                            '**/*'
                        ]
                    }
                ]
            }
        },
        generateSignatures: {
            sign: {
                version: '<%= package.version %>',
                files: [{
                    expand: true,
                    cwd: 'src/',
                    src: [
                        '**/*.php',
                        '**/*.js',
                        '!**/.gitignore',
                        '!vendor/**/example/**',
                        '!vendor/**/tests/**',
                        '!vendor/**/docs/**',
                        '!Images/Person/thumbnails/*.jpg',
                        '!composer.lock',
                        '!Include/Config.php',
                        '!integrityCheck.json'
                    ],
                    dest: 'churchcrm/'
                }]
            }
        },
        poeditor: {
            getPOTranslations: {
                download: {
                    project_id: '<%= poeditor.options.project_id %>',
                    filters: ["translated"],
                    tags: '<%= package.version %>',
                    type: 'po', // export type (check out the doc)
                    dest: 'src/locale/textdomain/?/LC_MESSAGES/messages.po'
                    // grunt style dest files
                }
            },
            getMOTranslations: {
                download: {
                    project_id: '<%= poeditor.options.project_id %>',
                    filters: ["translated"],
                    tags: '<%= package.version %>',
                    type: 'mo', // export type (check out the doc)
                    dest: 'src/locale/textdomain/?/LC_MESSAGES/messages.mo'
                    // grunt style dest files
                }
            },
            getJSTranslations: {
                download: {
                    project_id: '<%= poeditor.options.project_id %>',
                    filters: ["translated"],
                    tags: '<%= package.version %>',
                    type: 'key_value_json', // export type (check out the doc)
                    dest: 'locale/JSONKeys/?.json'
                    // grunt style dest files
                }
            },
            options: {
                project_id: '77079',
                languages: poLocales(),
                api_token: '<%= buildConfig.POEditor.token %>'
            }
        },
        updateVersions: {
            update: {
                version: '<%= package.version %>'
            }
        }
    });

    grunt.registerTask('hash', 'gets a file hash', function (arg1) {
        var sha1 = require('node-sha1');
        grunt.log.writeln(sha1(grunt.file.read(arg1, {encoding: null})));
    });

    grunt.registerMultiTask('generateSignatures', 'A sample task that logs stuff.', function () {
        var sha1 = require('node-sha1');
        var signatures = {
            "version": this.data.version,
            "files": []
        };
        this.files.forEach(function (filePair) {
            var isExpandedPair = filePair.orig.expand || false;

            filePair.src.forEach(function (src) {
                if (grunt.file.isFile(src)) {
                    signatures.files.push({
                        "filename": src.substring(4),
                        "sha1": sha1(grunt.file.read(src, {encoding: null}))
                    });
                }
            });
        });
        signatures.sha1 = sha1(JSON.stringify(signatures.files));
        grunt.file.write("src/signatures.json", JSON.stringify(signatures));
    });

    grunt.registerTask('updateFromPOeditor', 'Description of the task', function (target) {
        grunt.config('clean', {pofiles: ["src/locale/*/**/*.po", "src/locale/*/**/*.mo", "locale/JSONKeys/*.json"]});
        grunt.task.run(['clean:pofiles']);
        grunt.loadNpmTasks('grunt-poeditor-ab');
        grunt.task.run(['poeditor']);
    });

    grunt.registerTask('genLocaleJSFiles', '', function () {
        var locales = grunt.file.readJSON("src/locale/locales.json");
        var poEditorLocales = {};
        for (var key in locales ) {
            var localeConfig = locales[key];
            var locale = localeConfig["locale"];
            var countryCode = localeConfig["countryCode"];
            var languageCode = localeConfig["languageCode"];
            var enableFullCalendar = localeConfig["fullCalendar"];
            var enableDatePicker = localeConfig["datePicker"];
            var enableSelect2 = localeConfig["select2"];

            var tempFile = 'locale/JSONKeys/'+locale+'.json';
            var poTerms = grunt.file.read(tempFile);
            if (poTerms == "") {
                poTerms = "{}";
            }
            var jsFileContent = '// Source: ' + tempFile;
            jsFileContent = jsFileContent + "\ntry {window.CRM.i18keys = " + poTerms + ";} catch(e) {};\n";

            if (enableFullCalendar) {
                var tempLangCode = languageCode.toLowerCase();
                if (localeConfig.hasOwnProperty("fullCalendarLocale")) {
                    tempLangCode = localeConfig["fullCalendarLocale"];
                }
                tempFile = 'node_modules/fullcalendar/dist/locale/'+tempLangCode+'.js';
                var fullCalendar = grunt.file.read(tempFile);
                jsFileContent = jsFileContent + '\n// Source: ' + tempFile;
                jsFileContent = jsFileContent + '\n' + "try {"+fullCalendar+"} catch(e) {};\n";
            }
            if (enableDatePicker) {
                tempFile = 'node_modules/admin-lte/plugins/datepicker/locales/bootstrap-datepicker.'+languageCode+'.js';
                var datePicker = grunt.file.read(tempFile);
                jsFileContent = jsFileContent + '\n// Source: ' + tempFile;
                jsFileContent = jsFileContent + '\n' + "try {"+datePicker+"} catch(e) {};\n"
            }
            if (enableSelect2) {
                tempFile = 'node_modules/admin-lte/plugins/select2/i18n/'+languageCode+'.js';
                jsFileContent = jsFileContent + '\n// Source: ' + tempFile;
                var select2 = grunt.file.read(tempFile);
                jsFileContent = jsFileContent + '\n' + "try {"+select2+"} catch(e) {}"
            }
            grunt.file.write('src/locale/js/'+locale+'.js', jsFileContent );
        }
    });

    grunt.registerMultiTask('updateVersions', 'Update Files to match NPM version', function () {
        var version = this.data.version;

        // php composer
        var file = 'src/composer.json';
        
        var curFile = grunt.file.readJSON(file);
        if (curFile.version !== version) {
            console.log("updating composer file to: " + version);
            curFile.version = version;
            var stringFile = JSON.stringify(curFile, null, 4);
            grunt.file.write(file, stringFile);
        }

        // db update file
        file = 'src/mysql/upgrade.json';
        curFile = grunt.file.readJSON(file);
        if (curFile.current.dbVersion !== version) {
            console.log("updating database upgrade file to: " + version);
            curFile.current.versions.push(curFile.current.dbVersion);
            curFile.current.dbVersion = version;
            stringFile = JSON.stringify(curFile, null, 4);
            grunt.file.write(file, stringFile);
        }
    });

    grunt.loadNpmTasks('grunt-contrib-sass');
    grunt.loadNpmTasks('grunt-contrib-copy');
    grunt.loadNpmTasks('grunt-contrib-clean');
    grunt.loadNpmTasks('grunt-contrib-compress');
    grunt.loadNpmTasks('grunt-curl');
    grunt.loadNpmTasks('grunt-poeditor-ab');
}
