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

    var datatTablesVer = "1.10.18";

    const sass = require('node-sass');

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
            '!composer.lock',
            '!Include/Config.php',
            '!integrityCheck.json',
            '!logs/*.log'
        ],
        clean: {
            skin: ["src/skin/external"],
            release: ["target"]
        },
        copy: {
            skin: {
                files: [
                    // includes files within path and its sub-directories
                    {
                        expand: true,
                        filter: 'isFile',
                        flatten: true,
                          cwd: 'node_modules/admin-lte',
                        src: [
                            'dist/css/*.min.*',
                            'dist/css/skins/**',
                            'dist/js/adminlte.min.js',
                            ],
                        dest: 'src/skin/external/adminlte/'
                    },
                    {
                        expand: true,
                        cwd: 'node_modules/font-awesome',
                        src: ['{css,fonts,less,scss}/**'],
                        dest: 'src/skin/external/font-awesome/'
                    },
                    {
                        expand: true,
                        filter: 'isFile',
                        flatten: true,
                        src: ['node_modules/fullcalendar/dist/*'],
                        dest: 'src/skin/external/fullcalendar/'
                    },
                    {
                        expand: true,
                        filter: 'isFile',
                        flatten: true,
                        src: ['node_modules/moment/min/*'],
                        dest: 'src/skin/external/moment/'
                    },
                    {
                        expand: true,
                        filter: 'isFile',
                        flatten: true,
                        src: ['node_modules/jquery-photo-uploader/dist/*'],
                        dest: 'src/skin/external/jquery-photo-uploader/'
                    },
                    {
                        expand: true,
                        cwd:'node_modules/ckeditor/',
                        src: ['*.js','*.css','*.json','lang/**/*','adapters/**/*','plugins/**/*','skins/**/*'],
                        dest: 'src/skin/external/ckeditor/'
                    },
                    {
                        expand: true,
                        filter: 'isFile',
                        flatten: true,
                        src: ['node_modules/bootbox/bootbox.min.js'],
                        dest: 'src/skin/external/bootbox/'
                    },
                    {
                        expand: true,
                        filter: 'isFile',
                        flatten: true,
                        src: ['node_modules/bootstrap/dist/css/bootstrap.min.css', 'node_modules/bootstrap/dist/css/bootstrap.min.css.map', 'node_modules/bootstrap/dist/js/bootstrap.min.js'],
                        dest: 'src/skin/external/bootstrap/'
                    },
                    {
                        expand: true,
                        filter: 'isFile',
                        flatten: true,
                        src: ['node_modules/bootstrap/fonts/**'],
                        dest: 'src/skin/external/fonts/'
                    },
                    {
                        expand: true,
                        filter: 'isFile',
                        flatten: true,
                        src: ['node_modules/admin-lte/plugins/iCheck/icheck.min.js', 'node_modules/admin-lte/plugins/iCheck/square/blue.**'],
                        dest: 'src/skin/external/iCheck/'
                    },
                    {
                        expand: true,
                        filter: 'isFile',
                        flatten: true,
                        src: ['node_modules/bootstrap-toggle/css/bootstrap-toggle.css', 'node_modules/bootstrap-toggle/js/bootstrap-toggle.js'],
                        dest: 'src/skin/external/bootstrap-toggle/'
                    },
                    {
                        expand: true,
                        filter: 'isFile',
                        flatten: true,
                        src: ['node_modules/bootstrap-timepicker/css/bootstrap-timepicker.min.css', 'node_modules/bootstrap-timepicker/js/bootstrap-timepicker.min.js'],
                        dest: 'src/skin/external/bootstrap-timepicker/'
                    },
                    {
                        expand: true,
                        filter: 'isFile',
                        flatten: true,
                        src: ['node_modules/bootstrap-daterangepicker/daterangepicker.css', 'node_modules/bootstrap-daterangepicker/daterangepicker.js'],
                        dest: 'src/skin/external/bootstrap-daterangepicker/'
                    },
                    {
                        expand: true,
                        filter: 'isFile',
                        flatten: true,
                        src: ['node_modules/inputmask/dist/min/jquery.inputmask.bundle.min.js', 'node_modules/inputmask/dist/min/inputmask/inputmask.date.extensions.min.js',
                        'node_modules/inputmask/dist/min/inputmask/inputmask.extensions.min.js'],
                        dest: 'src/skin/external/inputmask/'
                    },
                    {
                        expand: true,
                        filter: 'isFile',
                        flatten: true,
                        src: ['node_modules/bootstrap-validator/dist/validator.min.js'],
                        dest: 'src/skin/external/bootstrap-validator/'
                    },
                    {
                        expand: true,
                        filter: 'isFile',
                        flatten: true,
                        src: ['node_modules/jquery/dist/jquery.min.js'],
                        dest: 'src/skin/external/jquery/'
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
                        src: ['node_modules/chart.js/Chart.min.js'],
                        dest: 'src/skin/external/chartjs/'
                    },
                    {
                        expand: true,
                        filter: 'isFile',
                        flatten: true,
                        src: ['node_modules/pace/pace.js'],
                        dest: 'src/skin/external/pace/'
                    },
                    {
                        expand: true,
                        filter: 'isFile',
                        flatten: true,
                        src: ['node_modules/flot/jquery.flot*.js'],
                        dest: 'src/skin/external/flot/'
                    },
                    {
                        expand: true,
                        filter: 'isFile',
                        flatten: true,
                        src: [
                            'node_modules/i18next/dist/umd/i18next.min.js',
                            'node_modules/i18next-xhr-backend/dist/umd/i18nextXHRBackend.min.js'
                        ],
                        dest: 'src/skin/external/i18next/'
                    },
                    {
                        expand: true,
                        filter: 'isFile',
                        flatten: true,
                        src: ['node_modules/bootstrap-show-password/bootstrap-show-password.min.js'],
                        dest: 'src/skin/external/bootstrap-show-password'
                    },
                    {
                        expand: true,
                        filter: 'isFile',
                        flatten: true,
                        src: ['node_modules/bootstrap-notify/bootstrap-notify.min.js'],
                        dest: 'src/skin/external/bootstrap-notify'
                    },
                    {
                        expand: true,
                        filter: 'isFile',
                        flatten: true,
                        src: ['node_modules/bootstrap-datepicker/dist/js/bootstrap-datepicker.min.js',
                        'node_modules/bootstrap-datepicker/dist/css/bootstrap-datepicker.standalone.min.css'],
                        dest: 'src/skin/external/bootstrap-datepicker'
                    },
                    {
                        expand: true,
                        filter: 'isFile',
                        flatten: true,
                        src: ['node_modules/select2/dist/js/select2.min.js',
                        'node_modules/select2/dist/css/select2.min.css'],
                        dest: 'src/skin/external/select2'
                    }
                ]
            }
        },
        'curl-dir': {
            datatables: {
                src: [
                    "https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.36/pdfmake.min.js",
                    "https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.36/vfs_fonts.js",
                    "https://cdn.datatables.net/v/dt/jszip-2.5.0/dt-"+datatTablesVer+"/b-1.5.4/b-html5-1.5.4/b-print-1.5.4/r-2.2.2/sl-1.2.6/datatables.min.css",
                    "https://cdn.datatables.net/v/dt/jszip-2.5.0/dt-"+datatTablesVer+"/b-1.5.4/b-html5-1.5.4/b-print-1.5.4/r-2.2.2/sl-1.2.6/datatables.min.js"
                ],
                dest: 'src/skin/external/datatables/'
            },
            datatables_images: {
                src: [
                    'https://cdn.datatables.net/'+datatTablesVer+'/images/sort_asc.png',
                    'https://cdn.datatables.net/'+datatTablesVer+'/images/sort_asc_disabled.png',
                    'https://cdn.datatables.net/'+datatTablesVer+'/images/sort_both.png',
                    'https://cdn.datatables.net/'+datatTablesVer+'/images/sort_desc.png',
                    'https://cdn.datatables.net/'+datatTablesVer+'/images/sort_desc_disabled.png'
                ],
                dest: "src/skin/external/datatables/DataTables-"+datatTablesVer+"/images/"
            },
            datatables_locale: {
                src: ['https://cdn.datatables.net/plug-ins/'+datatTablesVer+'/i18n/{'+dataTablesLang()+'}.json'],
                dest: 'src/locale/datatables'
            },
            fastclick: {
                src: ['https://raw.githubusercontent.com/ftlabs/fastclick/569732a7aa5861d428731b8db022b2d55abe1a5a/lib/fastclick.js'],
                dest: 'src/skin/external/fastclick'
            },
            jqueryuicss: {
                src: [
                    'https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css',
                    "https://code.jquery.com/ui/1.12.1/jquery-ui.min.js"
                ],
                dest: 'src/skin/external/jquery-ui/'
            }
        },
        sass: {
            options: {
                implementation: sass,
                sourceMap: true,
                cacheLocation: process.env['HOME'] + "/node_cache"
            },
            dist: {
              files: {
                  'src/skin/churchcrm.min.css': 'src/skin/churchcrm.scss'
              }
            },
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
                        '**/.htaccess',
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
        },
        exec: {
            updatechangelog: {
                cmd: "gren changelog --generate --override --token=<%= buildConfig.GitHub.token %>"
            }
        }
    });

    grunt.registerTask('hash', 'gets a file hash', function (arg1) {
        var sha1 = require('node-sha1');
        grunt.log.writeln(sha1(grunt.file.read(arg1, {encoding: null})));
    });

    grunt.registerMultiTask('generateSignatures', 'Generates SHA1 signatures of the release archive', function () {
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
                tempFile = 'node_modules/bootstrap-datepicker/dist/locales/bootstrap-datepicker.'+languageCode+'.min.js';
                var datePicker = grunt.file.read(tempFile);
                jsFileContent = jsFileContent + '\n// Source: ' + tempFile;
                jsFileContent = jsFileContent + '\n' + "try {"+datePicker+"} catch(e) {};\n"
            }
            if (enableSelect2) {
                tempFile = 'node_modules/select2/dist/js/i18n/'+languageCode+'.js';
                jsFileContent = jsFileContent + '\n// Source: ' + tempFile;
                var select2 = grunt.file.read(tempFile);
                jsFileContent = jsFileContent + '\n' + "try {"+select2+"} catch(e) {}"
            }
            grunt.file.write('src/locale/js/'+locale+'.js', jsFileContent );
        }
    });

    grunt.registerMultiTask('updateVersions', 'Update Files to match NPM version', function () {
        var moment = require('moment');
        var version = this.data.version;

        // php composer
        var file = 'src/composer.json';

        var curFile = grunt.file.readJSON(file);
        curFile.version = version;
        curFile.time =  moment().format("YYYY-MM-DD HH:MM:SS");
        var stringFile = JSON.stringify(curFile, null, 4);
        grunt.file.write(file, stringFile);

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

    grunt.registerTask('cleanupLocalGit', 'clean local git', function () {
       grunt.loadNpmTasks('grunt-git');
       grunt.config('gitreset' ,{
          task: {
            options: {
              mode: "hard"
            }
          }
        });

       grunt.config('gitcheckout', {
          master: {
            options: {
              branch: "master"
            }
          }
        });

        grunt.config('gitpull', {
          master: {
            options: {
              branch: "master"
            }
          }
        });
      grunt.task.run('gitreset');
      //  make sure we're on master
      grunt.task.run('gitcheckout:master');
      //  ensure local and remote master are up to date
      grunt.task.run('gitpull:master');
      //  display local master's commit hash
    });

    grunt.loadNpmTasks('grunt-sass');
    grunt.loadNpmTasks('grunt-contrib-copy');
    grunt.loadNpmTasks('grunt-contrib-clean');
    grunt.loadNpmTasks('grunt-contrib-compress');
    grunt.loadNpmTasks('grunt-curl');
    grunt.loadNpmTasks('grunt-poeditor-ab');
    grunt.loadNpmTasks('grunt-exec');

}
