/**
 * @license Copyright (c) 2003-2017, CKSource - Frederico Knabben. All rights reserved.
 * For licensing, see LICENSE.md or https://ckeditor.com/license
 */

/**
 * @fileOverview Defines the {@link CKEDITOR.lang} object for the
 * Azerbaijani language.
 */

/**#@+
   @type String
   @example
*/

/**
 * Contains the dictionary of language entries.
 * @namespace
 */
CKEDITOR.lang[ 'az' ] = {
	// ARIA description.
	editor: 'Mətn Redaktoru',
	editorPanel: 'Mətn Redaktorun Paneli',

	// Common messages and labels.
	common: {
		// Screenreader titles. Please note that screenreaders are not always capable
		// of reading non-English words. So be careful while translating it.
		editorHelp: 'Yardım üçün ALT 0 düymələrini basın',

		browseServer: 'Fayların siyahı',
		url: 'URL',
		protocol: 'Protokol',
		upload: 'Serverə yüklə',
		uploadSubmit: 'Göndər',
		image: 'Şəkil',
		flash: 'Flash',
		form: 'Forma',
		checkbox: 'Çekboks',
		radio: 'Radio düyməsi',
		textField: 'Mətn xanası',
		textarea: 'Mətn',
		hiddenField: 'Gizli xana',
		button: 'Düymə',
		select: 'Opsiyaların seçilməsi',
		imageButton: 'Şəkil tipli düymə',
		notSet: '<seçilməmiş>',
		id: 'Id',
		name: 'Ad',
		langDir: 'Yaziların istiqaməti',
		langDirLtr: 'Soldan sağa (LTR)',
		langDirRtl: 'Sağdan sola (RTL)',
		langCode: 'Dilin kodu',
		longDescr: 'URL-ın ətraflı izahı',
		cssClass: 'CSS klassları',
		advisoryTitle: 'Başlıq',
		cssStyle: 'CSS',
		ok: 'Tədbiq et',
		cancel: 'İmtina et',
		close: 'Bağla',
		preview: 'Baxış',
		resize: 'Eni dəyiş',
		generalTab: 'Əsas',
		advancedTab: 'Əlavə',
		validateNumberFailed: 'Rəqəm deyil.',
		confirmNewPage: 'Yadda saxlanılmamış dəyişikliklər itiriləcək. Davam etmək istədiyinizə əminsinizmi?',
		confirmCancel: 'Dəyişikliklər edilib. Pəncərəni bağlamaq istəyirsizə əminsinizmi?',
		options: 'Seçimlər',
		target: 'Hədəf çərçivə',
		targetNew: 'Yeni pəncərə (_blank)',
		targetTop: 'Əsas pəncərə (_top)',
		targetSelf: 'Carı pəncərə (_self)',
		targetParent: 'Ana pəncərə (_parent)',
		langDirLTR: 'Soldan sağa (LTR)',
		langDirRTL: 'Sağdan sola (RTL)',
		styles: 'Üslub',
		cssClasses: 'Üslub klası',
		width: 'En',
		height: 'Uzunluq',
		align: 'Yerləşmə',
		alignLeft: 'Sol',
		alignRight: 'Sağ',
		alignCenter: 'Mərkəz',
		alignJustify: 'Eninə görə',
		alignTop: 'Yuxarı',
		alignMiddle: 'Orta',
		alignBottom: 'Aşağı',
		alignNone: 'Yoxdur',
		invalidValue: 'Yanlışdır.',
		invalidHeight: 'Hündürlük rəqəm olmalıdır.',
		invalidWidth: 'En rəqəm olmalıdır.',
		invalidCssLength: '"%1" xanasında göstərilən məzmun tam və müsbət olmalıdır, CSS-də olan ölçü vahidlərin (px, %, in, cm, mm, em, ex, pt, or pc) istifadısinə icazə verilir.',
		invalidHtmlLength: '"%1" xanasında göstərilən məzmun tam və müsbət olmalıdır HTML-də olan ölçü vahidlərin (px və ya %) istifadısinə icazə verilir.',
		invalidInlineStyle: 'Teq içində olan üslub "ad :  məzmun" şəklidə, nöqtə-verqül işarəsi ilə bitməlidir',
		cssLengthTooltip: 'Piksel sayı və ya digər CSS ölçü vahidləri (px, %, in, cm, mm, em, ex, pt, or pc) daxil edin.',

		// Put the voice-only part of the label in the span.
		unavailable: '%1<span class="cke_accessibility">, mövcud deyil</span>',

		// Keyboard keys translations used for creating shortcuts descriptions in tooltips, context menus and ARIA labels.
		keyboard: {
			8: 'Backspace',
			13: 'Enter',
			16: 'Shift',
			17: 'Ctrl',
			18: 'Alt',
			32: 'Boşluq',
			35: 'Son',
			36: 'Evə',
			46: 'Sil',
			224: 'Əmr'
		},

		// Prepended to ARIA labels with shortcuts.
		keyboardShortcut: 'Qısayol düymələri',

		optionDefault: 'Default' // MISSING
	}
};
