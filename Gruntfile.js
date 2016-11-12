module.exports = function (grunt) {


// Project configuration.
  grunt.initConfig({
    pkg: grunt.file.readJSON('src/composer.json'),
    concat: {
      options: {
        separator: ';',
        banner: '/*! <%= pkg.version %> <%= grunt.template.today("dd-mm-yyyy") %> */\n'
      },
      de_DE: {
        src: ['node_modules/fullcalendar/dist/locale/de.js'],
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
        src: ['node_modules/fullcalendar/dist/locale/es.js'],
        dest: 'src/skin/locale/es_ES.js'
      },
      fr_FR: {
        src: ['node_modules/fullcalendar/dist/locale/fr.js'],
        dest: 'src/skin/locale/fr_FR.js'
      },
      hu_HU: {
        src: ['node_modules/fullcalendar/dist/locale/hu.js'],
        dest: 'src/skin/locale/hu_HU.js'
      },
      it_IT: {
        src: ['node_modules/fullcalendar/dist/locale/it.js'],
        dest: 'src/skin/locale/it_IT.js'
      },
      nb_NO: {
        src: ['node_modules/fullcalendar/dist/locale/nb.js'],
        dest: 'src/skin/locale/nb_NO.js'
      },
      nl_NL: {
        src: ['node_modules/fullcalendar/dist/locale/nl.js'],
        dest: 'src/skin/locale/nl_NL.js'
      },
      pl_PL: {
        src: ['node_modules/fullcalendar/dist/locale/pl.js'],
        dest: 'src/skin/locale/pl_PL.js'
      },
      pt_BR: {
        src: ['node_modules/fullcalendar/dist/locale/pt-br.js'],
        dest: 'src/skin/locale/pt_BR.js'
      },
      ro_RO: {
        src: ['node_modules/fullcalendar/dist/locale/ro.js'],
        dest: 'src/skin/locale/ro_RO.js'
      },
      ru_RU: {
        src: ['node_modules/fullcalendar/dist/locale/ru.js'],
        dest: 'src/skin/locale/ru_RU.js'
      },
      sq_AL: {
        src: [],
        dest: 'src/skin/locale/sq_AL.js'
      },
      sv_SE: {
        src: ['node_modules/fullcalendar/dist/locale/sv.js'],
        dest: 'src/skin/locale/sv_SE.js'
      },
      vi_VN: {
        src: ['node_modules/fullcalendar/dist/locale/vi.js'],
        dest: 'src/skin/locale/vi_VN.js'
      },
      zh_CN: {
        src: ['node_modules/fullcalendar/dist/locale/zh-cn.js'],
        dest: 'src/skin/locale/zh_CN.js'
      },
      zh_TW: {
        src: ['node_modules/fullcalendar/dist/locale/zh-tw.js'],
        dest: 'src/skin/locale/zh_TW.js'
      }
    }
  });

  grunt.loadNpmTasks('grunt-contrib-concat');

};
