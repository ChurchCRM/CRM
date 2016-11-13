module.exports = function (grunt) {


// Project configuration.
  grunt.initConfig({
    pkg: grunt.file.readJSON('src/composer.json'),
    concat: {
      options: {
        separator: ';\n\n',
        banner: '/*! <%= pkg.version %> <%= grunt.template.today("dd-mm-yyyy") %> */\n'
      },
      de_DE: {
        src: ['node_modules/fullcalendar/dist/locale/de.js', 'node_modules/admin-lte/plugins/datepicker/locales/bootstrap-datepicker.de.js'],
        dest: 'src/skin/locale/de_DE.js'
      },
      en_AU: {
        src: ['node_modules/fullcalendar/dist/locale/en-au.js'],
        dest: 'src/skin/locale/en_AU.js'
      },
      en_GB: {
        src: ['node_modules/fullcalendar/dist/locale/en-gb.js'],
        dest: 'src/skin/locale/en_GB.js'
      },
      en_US: {
        src: [],
        dest: 'src/skin/locale/en_US.js'
      },
      es_ES: {
        src: ['node_modules/fullcalendar/dist/locale/es.js', 'node_modules/admin-lte/plugins/datepicker/locales/bootstrap-datepicker.es.js'],
        dest: 'src/skin/locale/es_ES.js'
      },
      fr_FR: {
        src: ['node_modules/fullcalendar/dist/locale/fr.js', 'node_modules/admin-lte/plugins/datepicker/locales/bootstrap-datepicker.fr.js'],
        dest: 'src/skin/locale/fr_FR.js'
      },
      hu_HU: {
        src: ['node_modules/fullcalendar/dist/locale/hu.js', 'node_modules/admin-lte/plugins/datepicker/locales/bootstrap-datepicker.hu.js'],
        dest: 'src/skin/locale/hu_HU.js'
      },
      it_IT: {
        src: ['node_modules/fullcalendar/dist/locale/it.js', 'node_modules/admin-lte/plugins/datepicker/locales/bootstrap-datepicker.it.js'],
        dest: 'src/skin/locale/it_IT.js'
      },
      nb_NO: {
        src: ['node_modules/fullcalendar/dist/locale/nb.js', 'node_modules/admin-lte/plugins/datepicker/locales/bootstrap-datepicker.nb.js'],
        dest: 'src/skin/locale/nb_NO.js'
      },
      nl_NL: {
        src: ['node_modules/fullcalendar/dist/locale/nl.js', 'node_modules/admin-lte/plugins/datepicker/locales/bootstrap-datepicker.nl.js'],
        dest: 'src/skin/locale/nl_NL.js'
      },
      pl_PL: {
        src: ['node_modules/fullcalendar/dist/locale/pl.js', 'node_modules/admin-lte/plugins/datepicker/locales/bootstrap-datepicker.pl.js'],
        dest: 'src/skin/locale/pl_PL.js'
      },
      pt_BR: {
        src: ['node_modules/fullcalendar/dist/locale/pt-br.js', 'node_modules/admin-lte/plugins/datepicker/locales/bootstrap-datepicker.pt-BR.js'],
        dest: 'src/skin/locale/pt_BR.js'
      },
      ro_RO: {
        src: ['node_modules/fullcalendar/dist/locale/ro.js', 'node_modules/admin-lte/plugins/datepicker/locales/bootstrap-datepicker.ro.js'],
        dest: 'src/skin/locale/ro_RO.js'
      },
      ru_RU: {
        src: ['node_modules/fullcalendar/dist/locale/ru.js', 'node_modules/admin-lte/plugins/datepicker/locales/bootstrap-datepicker.ru.js'],
        dest: 'src/skin/locale/ru_RU.js'
      },
      sq_AL: {
        src: ['node_modules/admin-lte/plugins/datepicker/locales/bootstrap-datepicker.sq.js'],
        dest: 'src/skin/locale/sq_AL.js'
      },
      sv_SE: {
        src: ['node_modules/fullcalendar/dist/locale/sv.js', 'node_modules/admin-lte/plugins/datepicker/locales/bootstrap-datepicker.sv.js'],
        dest: 'src/skin/locale/sv_SE.js'
      },
      vi_VN: {
        src: ['node_modules/fullcalendar/dist/locale/vi.js', 'node_modules/admin-lte/plugins/datepicker/locales/bootstrap-datepicker.vi.js'],
        dest: 'src/skin/locale/vi_VN.js'
      },
      zh_CN: {
        src: ['node_modules/fullcalendar/dist/locale/zh-cn.js', 'node_modules/admin-lte/plugins/datepicker/locales/bootstrap-datepicker.zh-CN.js'],
        dest: 'src/skin/locale/zh_CN.js'
      },
      zh_TW: {
        src: ['node_modules/fullcalendar/dist/locale/zh-tw.js', 'node_modules/admin-lte/plugins/datepicker/locales/bootstrap-datepicker.zh-TW.js'],
        dest: 'src/skin/locale/zh_TW.js'
      }
    },
    'curl-dir': {
      datatables: {
        src: ['https://cdn.datatables.net/plug-ins/1.10.12/i18n/{Albanian,Chinese-traditional,Chinese,Dutch,English,French,German,Hungarian,Italian,Norwegian-Bokmal,Polish,Portuguese,Romanian,Russian,Spanish,Swedish,Vietnamese}.json'],
        dest: 'src/skin/locale/datatables'
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
          {src: ['src/skin/locale/datatables/Norwegian-Bokmal.json'], dest: 'src/skin/locale/datatables/nb_NO.json'},
          {src: ['src/skin/locale/datatables/Dutch.json'], dest: 'src/skin/locale/datatables/nl_NL.json'},
          {src: ['src/skin/locale/datatables/Polish.json'], dest: 'src/skin/locale/datatables/pl_PL.json'},
          {src: ['src/skin/locale/datatables/Portuguese.json'], dest: 'src/skin/locale/datatables/pt_BR.json'},
          {src: ['src/skin/locale/datatables/Romanian.json'], dest: 'src/skin/locale/datatables/ro_RO.json'},
          {src: ['src/skin/locale/datatables/Russian.json'], dest: 'src/skin/locale/datatables/ru_RU.json'},
          {src: ['src/skin/locale/datatables/Albanian.json'], dest: 'src/skin/locale/datatables/sq_AL.json'},
          {src: ['src/skin/locale/datatables/Swedish.json'], dest: 'src/skin/locale/datatables/sv_SE.json'},
          {src: ['src/skin/locale/datatables/Vietnamese.json'], dest: 'src/skin/locale/datatables/vi_VN.json'},
          {src: ['src/skin/locale/datatables/Chinese.json'], dest: 'src/skin/locale/datatables/zh_CN.json'},
          {src: ['src/skin/locale/datatables/Chinese-traditional.json'], dest: 'src/skin/locale/datatables/zh_TW.json'}
        ]
      }
    }
  });

  grunt.loadNpmTasks('grunt-contrib-concat');
  grunt.loadNpmTasks('grunt-contrib-rename');
  grunt.loadNpmTasks('grunt-curl');
};
