/**
 * @license Copyright (c) 2003-2017, CKSource - Frederico Knabben. All rights reserved.
 * For licensing, see LICENSE.md or https://ckeditor.com/license
 */

/**
 * @fileOverview Defines the {@link CKEDITOR.lang} object, for the
 * Latvian language.
 */

/**#@+
   @type String
   @example
*/

/**
 * Contains the dictionary of language entries.
 * @namespace
 */
CKEDITOR.lang[ 'lv' ] = {
	// ARIA description.
	editor: 'Bagātinātā teksta redaktors',
	editorPanel: 'Bagātinātā teksta redaktora panelis',

	// Common messages and labels.
	common: {
		// Screenreader titles. Please note that screenreaders are not always capable
		// of reading non-English words. So be careful while translating it.
		editorHelp: 'Palīdzībai, nospiediet ALT 0 ',

		browseServer: 'Skatīt servera saturu',
		url: 'URL',
		protocol: 'Protokols',
		upload: 'Augšupielādēt',
		uploadSubmit: 'Nosūtīt serverim',
		image: 'Attēls',
		flash: 'Flash',
		form: 'Forma',
		checkbox: 'Atzīmēšanas kastīte',
		radio: 'Izvēles poga',
		textField: 'Teksta rinda',
		textarea: 'Teksta laukums',
		hiddenField: 'Paslēpta teksta rinda',
		button: 'Poga',
		select: 'Iezīmēšanas lauks',
		imageButton: 'Attēlpoga',
		notSet: '<nav iestatīts>',
		id: 'Id',
		name: 'Nosaukums',
		langDir: 'Valodas lasīšanas virziens',
		langDirLtr: 'No kreisās uz labo (LTR)',
		langDirRtl: 'No labās uz kreiso (RTL)',
		langCode: 'Valodas kods',
		longDescr: 'Gara apraksta Hipersaite',
		cssClass: 'Stilu saraksta klases',
		advisoryTitle: 'Konsultatīvs virsraksts',
		cssStyle: 'Stils',
		ok: 'Darīts!',
		cancel: 'Atcelt',
		close: 'Aizvērt',
		preview: 'Priekšskatījums',
		resize: 'Mērogot',
		generalTab: 'Vispārīgi',
		advancedTab: 'Izvērstais',
		validateNumberFailed: 'Šī vērtība nav skaitlis',
		confirmNewPage: 'Jebkuras nesaglabātās izmaiņas tiks zaudētas. Vai tiešām vēlaties atvērt jaunu lapu?',
		confirmCancel: 'Daži no uzstādījumiem ir mainīti. Vai tiešām vēlaties aizvērt šo dialogu?',
		options: 'Uzstādījumi',
		target: 'Mērķis',
		targetNew: 'Jauns logs (_blank)',
		targetTop: 'Virsējais logs (_top)',
		targetSelf: 'Tas pats logs (_self)',
		targetParent: 'Avota logs (_parent)',
		langDirLTR: 'Kreisais uz Labo (LTR)',
		langDirRTL: 'Labais uz Kreiso (RTL)',
		styles: 'Stils',
		cssClasses: 'Stilu klases',
		width: 'Platums',
		height: 'Augstums',
		align: 'Nolīdzināt',
		alignLeft: 'Pa kreisi',
		alignRight: 'Pa labi',
		alignCenter: 'Centrēti',
		alignJustify: 'Izlīdzināt malas',
		alignTop: 'Augšā',
		alignMiddle: 'Vertikāli centrēts',
		alignBottom: 'Apakšā',
		alignNone: 'Nekas',
		invalidValue: 'Nekorekta vērtība',
		invalidHeight: 'Augstumam jābūt skaitlim.',
		invalidWidth: 'Platumam jābūt skaitlim',
		invalidCssLength: 'Laukam "%1" norādītajai vērtībai jābūt pozitīvam skaitlim ar vai bez korektām CSS mērvienībām (px, %, in, cm, mm, em, ex, pt, vai pc).',
		invalidHtmlLength: 'Laukam "%1" norādītajai vērtībai jābūt pozitīvam skaitlim ar vai bez korektām HTML mērvienībām (px vai %).',
		invalidInlineStyle: 'Iekļautajā stilā norādītajai vērtībai jāsastāv no viena vai vairākiem pāriem pēc forma\'ta "nosaukums: vērtība", atdalītiem ar semikolu.',
		cssLengthTooltip: 'Ievadiet vērtību pikseļos vai skaitli ar derīgu CSS mērvienību (px, %, in, cm, mm, em, ex, pt, vai pc).',

		// Put the voice-only part of the label in the span.
		unavailable: '%1<span class="cke_accessibility">, nav pieejams</span>',

		// Keyboard keys translations used for creating shortcuts descriptions in tooltips, context menus and ARIA labels.
		keyboard: {
			8: 'Backspace', // MISSING
			13: 'Enter', // MISSING
			16: 'Shift', // MISSING
			17: 'Ctrl', // MISSING
			18: 'Alt', // MISSING
			32: 'Space', // MISSING
			35: 'End', // MISSING
			36: 'Home', // MISSING
			46: 'Delete', // MISSING
			224: 'Command' // MISSING
		},

		// Prepended to ARIA labels with shortcuts.
		keyboardShortcut: 'Keyboard shortcut', // MISSING

		optionDefault: 'Default' // MISSING
	}
};
