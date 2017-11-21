/**
 * @license Copyright (c) 2003-2017, CKSource - Frederico Knabben. All rights reserved.
 * For licensing, see LICENSE.md or https://ckeditor.com/license
 */

/**
 * @fileOverview Defines the {@link CKEDITOR.lang} object, for the
 * Hungarian language.
 */

/**#@+
   @type String
   @example
*/

/**
 * Contains the dictionary of language entries.
 * @namespace
 */
CKEDITOR.lang[ 'hu' ] = {
	// ARIA description.
	editor: 'HTML szerkesztő',
	editorPanel: 'Rich Text szerkesztő panel',

	// Common messages and labels.
	common: {
		// Screenreader titles. Please note that screenreaders are not always capable
		// of reading non-English words. So be careful while translating it.
		editorHelp: 'Segítségért nyomjon ALT 0',

		browseServer: 'Böngészés a szerveren',
		url: 'Hivatkozás',
		protocol: 'Protokoll',
		upload: 'Feltöltés',
		uploadSubmit: 'Küldés a szerverre',
		image: 'Kép',
		flash: 'Flash',
		form: 'Űrlap',
		checkbox: 'Jelölőnégyzet',
		radio: 'Választógomb',
		textField: 'Szövegmező',
		textarea: 'Szövegterület',
		hiddenField: 'Rejtettmező',
		button: 'Gomb',
		select: 'Legördülő lista',
		imageButton: 'Képgomb',
		notSet: '<nincs beállítva>',
		id: 'Azonosító',
		name: 'Név',
		langDir: 'Írás iránya',
		langDirLtr: 'Balról jobbra',
		langDirRtl: 'Jobbról balra',
		langCode: 'Nyelv kódja',
		longDescr: 'Részletes leírás webcíme',
		cssClass: 'Stíluskészlet',
		advisoryTitle: 'Súgócimke',
		cssStyle: 'Stílus',
		ok: 'Rendben',
		cancel: 'Mégsem',
		close: 'Bezárás',
		preview: 'Előnézet',
		resize: 'Húzza az átméretezéshez',
		generalTab: 'Általános',
		advancedTab: 'További opciók',
		validateNumberFailed: 'A mezőbe csak számokat írhat.',
		confirmNewPage: 'Minden nem mentett változás el fog veszni! Biztosan be szeretné tölteni az oldalt?',
		confirmCancel: 'Az űrlap tartalma megváltozott, ám a változásokat nem rögzítette. Biztosan be szeretné zárni az űrlapot?',
		options: 'Beállítások',
		target: 'Cél',
		targetNew: 'Új ablak (_blank)',
		targetTop: 'Legfelső ablak (_top)',
		targetSelf: 'Aktuális ablakban (_self)',
		targetParent: 'Szülő ablak (_parent)',
		langDirLTR: 'Balról jobbra (LTR)',
		langDirRTL: 'Jobbról balra (RTL)',
		styles: 'Stílus',
		cssClasses: 'Stíluslap osztály',
		width: 'Szélesség',
		height: 'Magasság',
		align: 'Igazítás',
		alignLeft: 'Bal',
		alignRight: 'Jobbra',
		alignCenter: 'Középre',
		alignJustify: 'Sorkizárt',
		alignTop: 'Tetejére',
		alignMiddle: 'Középre',
		alignBottom: 'Aljára',
		alignNone: 'Semmi',
		invalidValue: 'Érvénytelen érték.',
		invalidHeight: 'A magasság mezőbe csak számokat írhat.',
		invalidWidth: 'A szélesség mezőbe csak számokat írhat.',
		invalidCssLength: '"%1"-hez megadott érték csakis egy pozitív szám lehet, esetleg egy érvényes CSS egységgel megjelölve(px, %, in, cm, mm, em, ex, pt vagy pc).',
		invalidHtmlLength: '"%1"-hez megadott érték csakis egy pozitív szám lehet, esetleg egy érvényes HTML egységgel megjelölve(px vagy %).',
		invalidInlineStyle: 'Az inline stílusnak megadott értéknek tartalmaznia kell egy vagy több rekordot a "name : value" formátumban, pontosvesszővel elválasztva.',
		cssLengthTooltip: 'Adjon meg egy számot értéknek pixelekben vagy egy számot érvényes CSS mértékegységben (px, %, in, cm, mm, em, ex, pt, vagy pc).',

		// Put the voice-only part of the label in the span.
		unavailable: '%1<span class="cke_accessibility">, nem elérhető</span>',

		// Keyboard keys translations used for creating shortcuts descriptions in tooltips, context menus and ARIA labels.
		keyboard: {
			8: 'Backspace',
			13: 'Enter',
			16: 'Shift',
			17: 'Ctrl',
			18: 'Alt',
			32: 'Space',
			35: 'End',
			36: 'Home',
			46: 'Delete',
			224: 'Command'
		},

		// Prepended to ARIA labels with shortcuts.
		keyboardShortcut: 'Gyorsbillentyű',

		optionDefault: 'Default' // MISSING
	}
};
