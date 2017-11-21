/**
 * @license Copyright (c) 2003-2017, CKSource - Frederico Knabben. All rights reserved.
 * For licensing, see LICENSE.md or https://ckeditor.com/license
 */

/**
 * @fileOverview Defines the {@link CKEDITOR.lang} object, for the
 * Korean language.
 */

/**#@+
   @type String
   @example
*/

/**
 * Contains the dictionary of language entries.
 * @namespace
 */
CKEDITOR.lang[ 'ko' ] = {
	// ARIA description.
	editor: '리치 텍스트 편집기',
	editorPanel: '리치 텍스트 편집기 패널',

	// Common messages and labels.
	common: {
		// Screenreader titles. Please note that screenreaders are not always capable
		// of reading non-English words. So be careful while translating it.
		editorHelp: '도움이 필요하면 ALT 0 을 누르세요',

		browseServer: '서버 탐색',
		url: 'URL',
		protocol: '프로토콜',
		upload: '업로드',
		uploadSubmit: '서버로 전송',
		image: '이미지',
		flash: '플래시',
		form: '폼',
		checkbox: '체크 박스',
		radio: '라디오 버튼',
		textField: '한 줄 입력 칸',
		textarea: '여러 줄 입력 칸',
		hiddenField: '숨은 입력 칸',
		button: '버튼',
		select: '선택 목록',
		imageButton: '이미지 버튼',
		notSet: '<설정 안 됨>',
		id: 'ID',
		name: '이름',
		langDir: '언어 방향',
		langDirLtr: '왼쪽에서 오른쪽 (LTR)',
		langDirRtl: '오른쪽에서 왼쪽 (RTL)',
		langCode: '언어 코드',
		longDescr: '웹 주소 설명',
		cssClass: '스타일 시트 클래스',
		advisoryTitle: '보조 제목',
		cssStyle: '스타일',
		ok: '확인',
		cancel: '취소',
		close: '닫기',
		preview: '미리보기',
		resize: '크기 조절',
		generalTab: '일반',
		advancedTab: '자세히',
		validateNumberFailed: '이 값은 숫자가 아닙니다.',
		confirmNewPage: '저장하지 않은 모든 변경사항은 유실됩니다. 정말로 새로운 페이지를 부르겠습니까?',
		confirmCancel: '일부 옵션이 변경 되었습니다. 정말로 창을 닫겠습니까?',
		options: '옵션',
		target: '타겟',
		targetNew: '새 창 (_blank)',
		targetTop: '최상위 창 (_top)',
		targetSelf: '같은 창 (_self)',
		targetParent: '부모 창 (_parent)',
		langDirLTR: '왼쪽에서 오른쪽 (LTR)',
		langDirRTL: '오른쪽에서 왼쪽 (RTL)',
		styles: '스타일',
		cssClasses: '스타일 시트 클래스',
		width: '너비',
		height: '높이',
		align: '정렬',
		alignLeft: '왼쪽',
		alignRight: '오른쪽',
		alignCenter: '가운데',
		alignJustify: '양쪽 맞춤',
		alignTop: '위',
		alignMiddle: '중간',
		alignBottom: '아래',
		alignNone: '기본',
		invalidValue: '잘못된 값.',
		invalidHeight: '높이는 숫자여야 합니다.',
		invalidWidth: '넓이는 숫자여야 합니다.',
		invalidCssLength: '"%1" 값은 유효한 CSS 측정 단위(px, %, in, cm, mm, em, ex, pt, or pc)를 포함하거나 포함하지 않은 양수 여야 합니다.',
		invalidHtmlLength: '"%1" 값은 유효한 HTML 측정 단위(px or %)를 포함하거나 포함하지 않은 양수여야 합니다.',
		invalidInlineStyle: '인라인 스타일에 설정된 값은 "name : value" 형식을 가진 하나 이상의 투플(tuples)이 세미콜론(;)으로 구분되어 구성되어야 합니다.',
		cssLengthTooltip: '픽셀 단위의 숫자만 입력하시거나 유효한 CSS 단위(px, %, in, cm, mm, em, ex, pt, or pc)와 함께 숫자를 입력해주세요.',

		// Put the voice-only part of the label in the span.
		unavailable: '%1<span class="cke_accessibility">, 사용불가</span>',

		// Keyboard keys translations used for creating shortcuts descriptions in tooltips, context menus and ARIA labels.
		keyboard: {
			8: '백스페이스',
			13: '엔터',
			16: '시프트',
			17: '컨트롤',
			18: '알트',
			32: '간격',
			35: '엔드',
			36: '홈',
			46: '딜리트',
			224: '커맨드'
		},

		// Prepended to ARIA labels with shortcuts.
		keyboardShortcut: '키보드 단축키',

		optionDefault: 'Default' // MISSING
	}
};
