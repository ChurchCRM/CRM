module.exports = function (grunt) {


// Project configuration.
    grunt.initConfig({
        package: grunt.file.readJSON('package.json'),
        pkg: grunt.file.readJSON('package.json'),
        buildConfig: grunt.file.readJSON('BuildConfig.json'),
        languages: {
            'ar': 'ar_EG',
            'de': 'de_DE',
            'en-au': 'en_AU',
            'en-ca': 'en_CA',
            'en': 'en_GB',
            'es': 'es_ES',
            'fr': 'fr_FR',
            'hu': 'hu_HU',
            'it': 'it_IT',
            'nb': 'nb_NO',
            'nl': 'nl_NL',
            'pl': 'pl_PL',
            'pt-br': 'pt_BR',
            'ro': 'ro_RO',
            'ru': 'ru_RU',
            'sq': 'sq_AL',
            'sv': 'sv_SE',
            'th': 'th_TH',
            'vi': 'vi_VN',
            'zh-CN': 'zh_CN',
            'zh-TW': 'zh_TW',
            'en-US': 'en_US'
        },
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
            locale: ["src/skin/locale"],
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
        concat: {
            options: {
                separator: ';\n\n',
                banner: '/*! <%= package.version %> <%= grunt.template.today("dd-mm-yyyy") %> */\n',
                process: function(src, filepath) {
                  if (filepath.match(/JSONKeys/)){
                   src = "window.CRM.i18keys = " + src + ";";
                  }
                  return '// Source: ' + filepath + '\n' +
                    "try {"+src+"} catch(e) {}";
                },
            },
            ar_EG: {
                src: [
                    'locale/JSONKeys/ar_EG.json',
                    'node_modules/fullcalendar/dist/locale/ar.js',
                    'node_modules/admin-lte/plugins/datepicker/locales/bootstrap-datepicker.ar.js',
                    'node_modules/admin-lte/plugins/select2/i18n/ar.js'
                ],
                dest: 'src/skin/locale/ar_EG.js'
            },
            de_DE: {
                src: [
                  'locale/JSONKeys/de_DE.json', 
                  'node_modules/fullcalendar/dist/locale/de.js', 
                  'node_modules/admin-lte/plugins/datepicker/locales/bootstrap-datepicker.de.js',
                  'node_modules/admin-lte/plugins/select2/i18n/de.js'
                ],
                dest: 'src/skin/locale/de_DE.js'
            },
            en_AU: {
                src: [
                  'locale/JSONKeys/en_AU.json', 
                  'node_modules/fullcalendar/dist/locale/en-au.js'
                ],
                dest: 'src/skin/locale/en_AU.js'
            },
            en_CA: {
                src: [
                  'locale/JSONKeys/en_CA.json',
                  'node_modules/fullcalendar/dist/locale/en-ca.js'
                ],
                dest: 'src/skin/locale/en_CA.js'
            },
            en_GB: {
                src: [
                  'locale/JSONKeys/en_GB.json',
                  'node_modules/fullcalendar/dist/locale/en-gb.js'
                ],
                dest: 'src/skin/locale/en_GB.js'
            },
            en_US: {
                src: [],
                dest: 'src/skin/locale/en_US.js'
            },
            es_ES: {
                src: [
                  'locale/JSONKeys/es_ES.json',
                  'node_modules/fullcalendar/dist/locale/es.js', 
                  'node_modules/admin-lte/plugins/datepicker/locales/bootstrap-datepicker.es.js',
                  'node_modules/admin-lte/plugins/select2/i18n/es.js'
                ],
                dest: 'src/skin/locale/es_ES.js'
            },
            fr_FR: {
                src: [
                  'locale/JSONKeys/fr_FR.json', 
                  'node_modules/fullcalendar/dist/locale/fr.js', 
                  'node_modules/admin-lte/plugins/datepicker/locales/bootstrap-datepicker.fr.js', 
                  'node_modules/admin-lte/plugins/select2/i18n/fr.js'
                ],
                dest: 'src/skin/locale/fr_FR.js'
            },
            hu_HU: {
                src: [
                  'locale/JSONKeys/hu_HU.json',
                  'node_modules/fullcalendar/dist/locale/hu.js', 
                  'node_modules/admin-lte/plugins/datepicker/locales/bootstrap-datepicker.hu.js',
                  'node_modules/admin-lte/plugins/select2/i18n/hu.js'
                ],
                dest: 'src/skin/locale/hu_HU.js'
            },
            th_TH: {
                src: [
                    'locale/JSONKeys/th_TH.json',
                    'node_modules/fullcalendar/dist/locale/th.js',
                    'node_modules/admin-lte/plugins/datepicker/locales/bootstrap-datepicker.th.js',
                    'node_modules/admin-lte/plugins/select2/i18n/th.js'
                ],
                dest: 'src/skin/locale/ar_EG.js'
            },
            it_IT: {
                src: [
                  'locale/JSONKeys/it_IT.json',
                  'node_modules/fullcalendar/dist/locale/it.js', 
                  'node_modules/admin-lte/plugins/datepicker/locales/bootstrap-datepicker.it.js',
                  'node_modules/admin-lte/plugins/select2/i18n/it.js'
                ],
                dest: 'src/skin/locale/it_IT.js'
            },
            nb_NO: {
                src: [
                  'locale/JSONKeys/nb_NO.json',
                  'node_modules/fullcalendar/dist/locale/nb.js', 
                  'node_modules/admin-lte/plugins/datepicker/locales/bootstrap-datepicker.nb.js',
                  'node_modules/admin-lte/plugins/select2/i18n/nb.js'
                ],
                dest: 'src/skin/locale/nb_NO.js'
            },
            nl_NL: {
                src: [
                  'locale/JSONKeys/nl_NL.json',
                  'node_modules/fullcalendar/dist/locale/nl.js', 
                  'node_modules/admin-lte/plugins/datepicker/locales/bootstrap-datepicker.nl.js',
                  'node_modules/admin-lte/plugins/select2/i18n/nl.js'
                ],
                dest: 'src/skin/locale/nl_NL.js'
            },
            pl_PL: {
                src: [
                  'locale/JSONKeys/pl_PL.json',
                  'node_modules/fullcalendar/dist/locale/pl.js', 
                  'node_modules/admin-lte/plugins/datepicker/locales/bootstrap-datepicker.pl.js',
                  'node_modules/admin-lte/plugins/select2/i18n/pl.js'
                ],
                dest: 'src/skin/locale/pl_PL.js'
            },
            pt_BR: {
                src: [
                  'locale/JSONKeys/pt_BR.json',
                  'node_modules/fullcalendar/dist/locale/pt-br.js', 
                  'node_modules/admin-lte/plugins/datepicker/locales/bootstrap-datepicker.pt-BR.js',
                  'node_modules/admin-lte/plugins/select2/i18n/pt-BR.js'
                ],
                dest: 'src/skin/locale/pt_BR.js'
            },
            ro_RO: {
                src: [
                  'locale/JSONKeys/ro_RO.json',
                  'node_modules/fullcalendar/dist/locale/ro.js', 
                  'node_modules/admin-lte/plugins/datepicker/locales/bootstrap-datepicker.ro.js',
                  'node_modules/admin-lte/plugins/select2/i18n/ro.js'
                ],
                dest: 'src/skin/locale/ro_RO.js'
            },
            ru_RU: {
                src: [
                  'locale/JSONKeys/ru_RU.json',
                  'node_modules/fullcalendar/dist/locale/ru.js', 
                  'node_modules/admin-lte/plugins/datepicker/locales/bootstrap-datepicker.ru.js',
                  'node_modules/admin-lte/plugins/select2/i18n/ru.js'
                ],
                dest: 'src/skin/locale/ru_RU.js'
            },
            sq_AL: {
                src: [
                  'locale/JSONKeys/sq_AL.json',
                  'node_modules/admin-lte/plugins/datepicker/locales/bootstrap-datepicker.sq.js'
                ],
                dest: 'src/skin/locale/sq_AL.js'
            },
            sv_SE: {
                src: [
                  'locale/JSONKeys/sv_SE.json',
                  'node_modules/fullcalendar/dist/locale/sv.js', 
                  'node_modules/admin-lte/plugins/datepicker/locales/bootstrap-datepicker.sv.js',
                    'node_modules/admin-lte/plugins/select2/i18n/sv.js'
                ],
                dest: 'src/skin/locale/sv_SE.js'
            },
            vi_VN: {
                src: [
                  'locale/JSONKeys/vi_VN',
                  'node_modules/fullcalendar/dist/locale/vi.js', 
                  'node_modules/admin-lte/plugins/datepicker/locales/bootstrap-datepicker.vi.js',
                  'node_modules/admin-lte/plugins/select2/i18n/vi.js'
                ],
                dest: 'src/skin/locale/vi_VN.js'
            },
            zh_CN: {
                src: [
                  'locale/JSONKeys/zh_CN.json',
                  'node_modules/fullcalendar/dist/locale/zh-cn.js', 
                  'node_modules/admin-lte/plugins/datepicker/locales/bootstrap-datepicker.zh-CN.js',
                  'node_modules/admin-lte/plugins/select2/i18n/zh-CN.js'
                ],
                dest: 'src/skin/locale/zh_CN.js'
            },
            zh_TW: {
                src: [
                  'locale/JSONKeys/zh_TW.json',
                  'node_modules/fullcalendar/dist/locale/zh-tw.js', 
                  'node_modules/admin-lte/plugins/datepicker/locales/bootstrap-datepicker.zh-TW.js',
                  'node_modules/admin-lte/plugins/select2/i18n/zh-TW.js'
                ],
                dest: 'src/skin/locale/zh_TW.js'
            }
        },
        'curl-dir': {
            datatables: {
                src: ['https://cdn.datatables.net/plug-ins/1.10.12/i18n/{Albanian,Chinese-traditional,Chinese,Dutch,English,French,German,Hungarian,Italian,Norwegian-Bokmal,Polish,Portuguese,Romanian,Russian,Spanish,Swedish,Vietnamese}.json'],
                dest: 'src/skin/locale/datatables'
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
        rename: {
            datatables: {
                files: [
                    {src: ['src/skin/locale/datatables/English.json'], dest: 'src/skin/locale/datatables/en_US.json'},
                    {src: ['src/skin/locale/datatables/German.json'], dest: 'src/skin/locale/datatables/de_DE.json'},
                    {src: ['src/skin/locale/datatables/Spanish.json'], dest: 'src/skin/locale/datatables/es_ES.json'},
                    {src: ['src/skin/locale/datatables/French.json'], dest: 'src/skin/locale/datatables/fr_FR.json'},
                    {src: ['src/skin/locale/datatables/Hungarian.json'], dest: 'src/skin/locale/datatables/hu_HU.json'},
                    {src: ['src/skin/locale/datatables/Italian.json'], dest: 'src/skin/locale/datatables/it_IT.json'},
                    {
                        src: ['src/skin/locale/datatables/Norwegian-Bokmal.json'],
                        dest: 'src/skin/locale/datatables/nb_NO.json'
                    },
                    {src: ['src/skin/locale/datatables/Dutch.json'], dest: 'src/skin/locale/datatables/nl_NL.json'},
                    {src: ['src/skin/locale/datatables/Polish.json'], dest: 'src/skin/locale/datatables/pl_PL.json'},
                    {
                        src: ['src/skin/locale/datatables/Portuguese.json'],
                        dest: 'src/skin/locale/datatables/pt_BR.json'
                    },
                    {src: ['src/skin/locale/datatables/Romanian.json'], dest: 'src/skin/locale/datatables/ro_RO.json'},
                    {src: ['src/skin/locale/datatables/Russian.json'], dest: 'src/skin/locale/datatables/ru_RU.json'},
                    {src: ['src/skin/locale/datatables/Albanian.json'], dest: 'src/skin/locale/datatables/sq_AL.json'},
                    {src: ['src/skin/locale/datatables/Swedish.json'], dest: 'src/skin/locale/datatables/sv_SE.json'},
                    {
                        src: ['src/skin/locale/datatables/Vietnamese.json'],
                        dest: 'src/skin/locale/datatables/vi_VN.json'
                    },
                    {src: ['src/skin/locale/datatables/Chinese.json'], dest: 'src/skin/locale/datatables/zh_CN.json'},
                    {
                        src: ['src/skin/locale/datatables/Chinese-traditional.json'],
                        dest: 'src/skin/locale/datatables/zh_TW.json'
                    }
                ]
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
                    dest: 'src/locale/?/LC_MESSAGES/messages.po'
                    // grunt style dest files
                }
            },
            getMOTranslations: {
                download: {
                    project_id: '<%= poeditor.options.project_id %>',
                    filters: ["translated"],
                    tags: '<%= package.version %>',
                    type: 'mo', // export type (check out the doc)
                    dest: 'src/locale/?/LC_MESSAGES/messages.mo'
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
                languages: '<%= languages %>',
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
            isExpandedPair = filePair.orig.expand || false;

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


    grunt.registerMultiTask('updateVersions', 'Update Files to match NPM version', function () {
        var version = this.data.version;

      // php composer
        var file = 'src/composer.json';
        var curFile = grunt.file.readJSON(file);
        if (curFile.version !== version)
        {
          console.log("updating composer file to: " + version);
          curFile.version = version;
          var stringFile = JSON.stringify(curFile, null, 4);
          grunt.file.write(file, stringFile);
        }

        // db update file
        file = 'src/mysql/upgrade.json';
        curFile = grunt.file.readJSON(file);
        if (curFile.current.dbVersion !== version)
        {
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
    grunt.loadNpmTasks('grunt-contrib-concat');
    grunt.loadNpmTasks('grunt-contrib-compress');
    grunt.loadNpmTasks('grunt-contrib-rename');
    grunt.loadNpmTasks('grunt-curl');
    grunt.loadNpmTasks('grunt-poeditor-ab');
}
