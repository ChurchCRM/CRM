/**
 * @license Copyright (c) 2003-2017, CKSource - Frederico Knabben. All rights reserved.
 * For licensing, see LICENSE.md or https://ckeditor.com/legal/terms-of-use/#open-source-licences
 */

CKEDITOR.editorConfig = function( config ) {
  // Define changes to default configuration here. For example:
  // config.language = 'fr';
  // config.uiColor = '#AADC6E';
  // %REMOVE_START%
  config.height = '400px';
  
  config.plugins =
    'about,' +
    'a11yhelp,' +
    'basicstyles,' +
    'bidi,' +
    'blockquote,' +
    'clipboard,' +
    'colorbutton,' +
    'colordialog,' +
    'copyformatting,' +
    'contextmenu,' +
    'dialogadvtab,' +
    'div,' +
    'elementspath,' +
    'enterkey,' +
    'entities,' +
    'filebrowser,' +
    'find,' +
    'flash,' +
    'floatingspace,' +
    'font,' +
    'format,' +
    //'forms,' +
    'horizontalrule,' +
    //'htmlwriter,' +
    'image,' +
    //'iframe,' +
    'indentlist,' +
    'indentblock,' +
    'justify,' +
    'language,' +
    'link,' +
    'list,' +
    'liststyle,' +
    'magicline,' +
    'maximize,' +
    'newpage,' +
    'pagebreak,' +
    'pastefromword,' +
    'pastetext,' +
    'preview,' +
    'print,' +
    'removeformat,' +
    'resize,' +
    'save,' +
    'selectall,' +
    'showblocks,' +
    'showborders,' +
    'smiley,' +
    'sourcearea,' +
    'specialchar,' +
    'stylescombo,' +
    'tab,' +
    'table,' +
    'tableselection,' +
    'tabletools,' +
    'templates,' +
    'toolbar,' +
    'undo,' +
    'wysiwygarea';
  // %REMOVE_END%
};

// %LEAVE_UNMINIFIED% %REMOVE_LINE%
