// {{{ docs <-- this is a VIM (text editor) text fold

/**
 * DOM Menu 0.3.2
 *
 * Summary: Allows developers to add dynamic drop down menus on webpages.  The
 *          menu can either be horizontal or vertical, and can open in either
 *          direction.  It has both edge detection and <select> tag detection
 *          (for browsers that cannot hide these form elements).  The styles
 *          for the menu items are controlled almost entirely through CSS and
 *          the menus are created and destroyed using the DOM.  Menu configuration
 *          is done using a custom Hash() class and is very portable from a PHP
 *          type array structure.
 *
 * Maintainer: Dan Allen <dan@mojavelinux.com>
 *
 * License: LGPL - however, if you use this library, please post to my forum where you
 *          use it so that I get a chance to see my baby in action.  If you are doing
 *          this for commercial work perhaps you could send me a few Starbucks Coffee
 *          gift dollars to encourage future developement (NOT REQUIRED).  E-mail me
 *          for and address.
 *
 * Homepage: http://www.mojavelinux.com/forum/viewtopic.php
 *
 * Freshmeat Project: http://freshmeat.net/projects/dommenu/?topic_id=92
 *
 * Updated: 2003/01/04
 *
 * Supported Browsers: Mozilla (Gecko), IE 5+, Konqueror, (not finished Opera 7), Netscape 4
 *
 * Usage: 
 *
 * Menu Options: Each option is followed by the value for that option. The options avaiable are:
 *            'contents'
 *            'rolloverContents',
 *            'uri' (may be javascript)
 *            'statusText'
 *            'target'
 *            [0-9] an index to create a submenu item
 *
 * API:
 *
 * menuElementObject {
 *     ** properties **
 *     data
 *       contents
 *       uri
 *       target
 *       statusText
 *       parentElement
 *       subMenu
 *       childElements
 *       level
 *       index (index within this level)
 *     id
 *     className
 *     style
 *     cellSpacing (Konq only)
 *     
 *     ** events **
 *     mouseover/click -> domMenu_openEvent
 *     mouseout        -> domMenu_closeEvent
 *     click           -> domMenu_resolveLink
 * }
 *
 * If there is a non-negative click open delay, then any uri of the element will be ignored
 *
 * The alternate contents for a hover element are treated by creating to <span> wrapper elements
 * and then alternating the display of them.  This avoids the need for innerHTML, which can
 * do nasty things to the browsers.  If <span> turns out to be a bad choice for tags, then a
 * non-HTML element can be used instead.
 *
**/

// }}}
// {{{ settings (editable)

var domMenu_data = new domMenu_Hash();
var domMenu_settings = new domMenu_Hash();

domMenu_settings.setItem('global', new domMenu_Hash(
    'menuBarClass', 'domMenu_menuBar',
    'menuElementClass', 'domMenu_menuElement',
    'menuElementHoverClass', 'domMenu_menuElementHover',
    'menuElementActiveClass', 'domMenu_menuElementHover',
    'subMenuBarClass', 'domMenu_subMenuBar',
    'subMenuElementClass', 'domMenu_subMenuElement',
    'subMenuElementHoverClass', 'domMenu_subMenuElementHover',
    'subMenuElementActiveClass', 'domMenu_subMenuElementHover',
    'subMenuElementHeadingClass', 'domMenu_subMenuElementHeading',
    'menuBarWidth', '100%',
    'subMenuMinWidth', 'inherit',
    'distributeSpace', true,
    'axis', 'horizontal',
    'verticalExpand', 'south',
    'horizontalExpand', 'east',
    'subMenuWidthCorrection', 0,
    'verticalSubMenuOffsetY', 0,
    'verticalSubMenuOffsetX', 0,
    'horizontalSubMenuOffsetX', 0,
    'horizontalSubMenuOffsetY', 0,
    'screenPadding', 0,
    'openMouseoverMenuDelay', 300,
    'openMousedownMenuDelay', -1,
    'closeMouseoutMenuDelay', 800,
    'closeClickMenuDelay', -1,
    'openMouseoverSubMenuDelay', 300,
    'openClickSubMenuDelay', -1,
    'closeMouseoutSubMenuDelay', 300,
    'closeClickSubMenuDelay', -1,
    'baseZIndex', 100
));

// }}}
// {{{ global variables

/**
 * Browser variables
 * @var domMenu_is{Browser}
 */
var domMenu_userAgent = navigator.userAgent.toLowerCase();
var domMenu_isOpera = domMenu_userAgent.indexOf('opera 7') != -1 ? 1 : 0;
var domMenu_isKonq = domMenu_userAgent.indexOf('konq') != -1 ? 1 : 0;
var domMenu_isIE = !domMenu_isKonq && !domMenu_isOpera && document.all ? 1 : 0;
var domMenu_isIE50 = domMenu_isIE && domMenu_userAgent.indexOf('msie 5.0') != -1;
var domMenu_isIE55 = domMenu_isIE && domMenu_userAgent.indexOf('msie 5.5') != -1;
var domMenu_isIE5 = domMenu_isIE50 || domMenu_isIE55;
var domMenu_isSafari = domMenu_userAgent.indexOf('safari') != -1 ? 1 : 0;
var domMenu_isGecko = !domMenu_isSafari && domMenu_userAgent.indexOf('gecko') != -1 ? 1 : 0;

/**
 * Passport to use the menu system, checked before performing menu manipulation
 * @var domMenu_useLibrary
 */
var domMenu_useLibrary = domMenu_isIE || domMenu_isGecko || domMenu_isKonq || domMenu_isOpera || domMenu_isSafari ? 1 : 0;

/**
 * The data for the menu is stored here, loaded from an external file
 * @hash domMenu_data
 */
var domMenu_data;

var domMenu_selectElements;
var domMenu_scrollbarWidth = 14;
var domMenu_eventTo = domMenu_isIE ? 'toElement' : 'relatedTarget';
var domMenu_eventFrom = domMenu_isIE ? 'fromElement' : 'relatedTarget';

var domMenu_activeElement = new domMenu_Hash();

/**
 * Array of hashes listing the timouts currently running for opening/closing menus
 * @array domMenu_timeouts
 */
var domMenu_timeouts = new Array();
domMenu_timeouts['open'] = new domMenu_Hash();
domMenu_timeouts['close'] = new domMenu_Hash();

var domMenu_timeoutStates = new Array();
domMenu_timeoutStates['open'] = new domMenu_Hash();
domMenu_timeoutStates['close'] = new domMenu_Hash();

/**
 * Style to use for a link pointer, which is different between Gecko and IE
 * @var domMenu_pointerStyle
 */
var domMenu_pointerStyle = domMenu_isIE ? 'hand' : 'pointer';

// }}}
// {{{ domMenu_Hash()

function domMenu_Hash() {
    var argIndex = 0;
    this.length = 0;
    this.numericLength = 0; 
    this.items = new Array();
    while (arguments.length > argIndex) {
        this.items[arguments[argIndex]] = arguments[argIndex + 1];
        if (arguments[argIndex] == parseInt(arguments[argIndex])) {
            this.numericLength++;
        }

        this.length++;
        argIndex += 2;
    }

    this.removeItem = function(in_key)
    {
        var tmp_value;
        if (typeof(this.items[in_key]) != 'undefined') {
            this.length--;
            if (in_key == parseInt(in_key)) {
                this.numericLength--;
            }

            tmp_value = this.items[in_key];
            delete this.items[in_key];
        }
        
        return tmp_value;
    }

    this.getItem = function(in_key)
    {
        return this.items[in_key];
    }

    this.setItem = function(in_key, in_value)
    {
        if (typeof(this.items[in_key]) == 'undefined') {
            this.length++;
            if (in_key == parseInt(in_key)) {
                this.numericLength++;
            }
        }
        
        this.items[in_key] = in_value;
    }

    this.hasItem = function(in_key)
    {
        return typeof(this.items[in_key]) != 'undefined';
    }
    
    this.merge = function(in_hash)
    {
        for (var tmp_key in in_hash.items) {
            if (typeof(this.items[tmp_key]) == 'undefined') {
                this.length++;
                if (tmp_key == parseInt(tmp_key)) {
                    this.numericLength++;
                }
            }

            this.items[tmp_key] = in_hash.items[tmp_key];
        }
    }

    this.compare = function(in_hash)
    {
        if (this.length != in_hash.length) {
            return false;
        }

        for (var tmp_key in this.items) {
            if (this.items[tmp_key] != in_hash.items[tmp_key]) {
                return false;
            }
        }
        
        return true;
    }
}

// }}}
// {{{ domMenu_activate()

function domMenu_activate(in_containerId)
{
    var container;
    var data;

    // make sure we can use the menu system and this is a valid menu
    if (!domMenu_useLibrary || !(container = document.getElementById(in_containerId)) || !(data = domMenu_data.items[in_containerId])) {
        return;
    }

    // start with the global settings and merge in the local changes
    if (!domMenu_settings.hasItem(in_containerId)) {
        domMenu_settings.setItem(in_containerId, new domMenu_Hash());
    }

    var settings = domMenu_settings.items[in_containerId];
    for (var i in domMenu_settings.items['global'].items) {
        if (!settings.hasItem(i)) {
            settings.setItem(i, domMenu_settings.items['global'].items[i]);
        }
    }

    // populate the zero level element
    container.data = new domMenu_Hash(
        'parentElement', false,
        'numChildren', data.numericLength,
        'childElements', new domMenu_Hash(),
        'level', 0,
        'index', 1
    );
    
    // if we choose to distribute either height or width, determine ratio of each cell
    var distributeRatio = Math.round(100/container.data.items['numChildren']) + '%';
    
    // the first menu is the rootMenu, which is a child of the zero level element
    var rootMenu = document.createElement('div');
    rootMenu.id = in_containerId + '[0]';
    rootMenu.className = settings.items['menuBarClass'];
    container.data.setItem('subMenu', rootMenu);

    var rootMenuTable = rootMenu.appendChild(document.createElement('table'));
    if (domMenu_isKonq) {
        rootMenuTable.cellSpacing = 0;
    }

    rootMenuTable.style.border = 0;
    rootMenuTable.style.borderCollapse = 'collapse';
    rootMenuTable.style.width = settings.items['menuBarWidth'];
    var rootMenuTableBody = rootMenuTable.appendChild(document.createElement('tbody'));

    var numSiblings = container.data.items['numChildren'];
    for (var index = 1; index <= numSiblings; index++) {
        // create a row the first time if horizontal or each time if vertical
        if (index == 1 || settings.items['axis'] == 'vertical') {
            var rootMenuTableRow = rootMenuTableBody.appendChild(document.createElement('tr'));
        }

        // create an instance of the root level menu element
        var rootMenuTableCell = rootMenuTableRow.appendChild(document.createElement('td'));
        rootMenuTableCell.style.padding = 0;
        rootMenuTableCell.id = in_containerId + '[' + index + ']';
        // add element to list of parent children
        container.data.items['childElements'].setItem(rootMenuTableCell.id, rootMenuTableCell);

        // assign the settings to the root level element
        // {!} this is a problem if two menus are using the same data {!}
        rootMenuTableCell.data = data.items[index];
        rootMenuTableCell.data.merge(new domMenu_Hash(
            'basename', in_containerId,
            'parentElement', container,
            'numChildren', rootMenuTableCell.data.numericLength,
            'childElements', new domMenu_Hash(),
            'offsets', new domMenu_Hash(),
            'level', container.data.items['level'] + 1,
            'index', index
        ));

        // assign the styles
        rootMenuTableCell.style.cursor = 'default';
        if (settings.items['axis'] == 'horizontal') {
            if (settings.items['distributeSpace']) {
                rootMenuTableCell.style.width = distributeRatio;
            }
        }

        var rootElement = rootMenuTableCell.appendChild(document.createElement('div'));
        rootElement.className = settings.items['menuElementClass'];
        // fill in the menu element contents
        rootElement.innerHTML = '<span>' + rootMenuTableCell.data.items['contents'] + '</span>' + (rootMenuTableCell.data.hasItem('contentsHover') ? '<span style="display: none;">' + rootMenuTableCell.data.items['contentsHover'] + '</span>' : '');

        // attach the events
        rootMenuTableCell.onmouseover = function(in_event) { domMenu_openEvent(this, in_event, settings.items['openMouseoverMenuDelay']); };
        rootMenuTableCell.onmouseout = function(in_event) { domMenu_closeEvent(this, in_event); };

        if (settings.items['openMousedownMenuDelay'] >= 0 && rootMenuTableCell.data.items['numChildren']) {
            rootMenuTableCell.onmousedown = function(in_event) { domMenu_openEvent(this, in_event, settings.items['openMousedownMenuDelay']); };
            // cancel mouseup so that it doesn't propogate to global mouseup event
            rootMenuTableCell.onmouseup = function(in_event) { var eventObj = domMenu_isIE ? event : in_event; eventObj.cancelBubble = true; };
            if (domMenu_isIE) {
                rootMenuTableCell.ondblclick = function(in_event) { domMenu_openEvent(this, in_event, settings.items['openMousedownMenuDelay']); };
            }
        }
        else if (rootMenuTableCell.data.items['uri']) {
            rootMenuTableCell.style.cursor = domMenu_pointerStyle;
            rootMenuTableCell.onclick = function(in_event) { domMenu_resolveLink(this, in_event); };
        }

        // prevent highlighting of text
        if (domMenu_isIE) {
            rootMenuTableCell.onselectstart = function() { return false; };
        }

        rootMenuTableCell.oncontextmenu = function() { return false; };
    }
    
    // add the menu rootMenu to the zero level element
    rootMenu = container.appendChild(rootMenu);

    // even though most cases the top level menu does not go away, it could
    // if this menu system is used by another process
    domMenu_detectCollisions(rootMenu);
}

// }}}
// {{{ domMenu_activateSubMenu()

function domMenu_activateSubMenu(in_parentElement)
{
    // see if submenu already exists
    if (in_parentElement.data.hasItem('subMenu')) {
        domMenu_toggleSubMenu(in_parentElement, 'visible');
        return;
    }

    var settings = domMenu_settings.items[in_parentElement.data.items['basename']];

    // build the submenu
    var menu = document.createElement('div');
    menu.id = in_parentElement.id + '[0]';
    menu.className = settings.items['subMenuBarClass'];
    menu.style.zIndex = settings.items['baseZIndex'];
    menu.style.position = 'absolute';
    // position the menu in the upper left corner hidden so that we can work on it
    menu.style.visibility = 'hidden';
    menu.style.top = 0;
    menu.style.left = 0;

    in_parentElement.data.setItem('subMenu', menu);

    var menuTable = menu.appendChild(document.createElement('table'));
    // ** opera wants to make absolute tables width 100% **
    if (domMenu_isOpera) {
        menuTable.style.width = '1px';
        menuTable.style.whiteSpace = 'nowrap';
    }

    if (domMenu_isKonq) {
        menuTable.cellSpacing = 0;
    }

    menuTable.style.border = 0;
    menuTable.style.borderCollapse = 'collapse';
    var menuTableBody = menuTable.appendChild(document.createElement('tbody'));

    var numSiblings = in_parentElement.data.items['numChildren'];
    for (var index = 1; index <= numSiblings; index++) {
        var dataIndex = in_parentElement.data.items['level'] == 1 && settings.items['verticalExpand'] == 'north' && settings.items['axis'] == 'horizontal' ? numSiblings + 1 - index : index;
        var menuTableCell = menuTableBody.appendChild(document.createElement('tr')).appendChild(document.createElement('td'));
        menuTableCell.style.padding = 0;
        menuTableCell.id = in_parentElement.id + '[' + dataIndex + ']';

        // add element to list of parent children
        in_parentElement.data.items['childElements'].setItem(menuTableCell.id, menuTableCell);

        // assign the settings to nth level element
        menuTableCell.data = in_parentElement.data.items[dataIndex];
        menuTableCell.data.merge(new domMenu_Hash(
            'basename', in_parentElement.data.items['basename'],
            'parentElement', in_parentElement,
            'numChildren', menuTableCell.data.numericLength,
            'childElements', new domMenu_Hash(),
            'offsets', new domMenu_Hash(),
            'level', in_parentElement.data.items['level'] + 1,
            'index', index
        ));
        
        // assign the styles
        var parentStyle = in_parentElement.data.items['level'] == 1 ? in_parentElement.parentNode.style : in_parentElement.style;
        menuTableCell.style.cursor = 'default';
        
        var element = menuTableCell.appendChild(document.createElement('div')); 
        var outerElement = element;
        outerElement.className = settings.items['subMenuElementClass']; 

        if (menuTableCell.data.items['numChildren']) {
            element = outerElement.appendChild(document.createElement('div'));
            // {!} depends on which way we are opening {!}
            element.style.backgroundImage = 'url(arrow.gif)';
            element.style.backgroundRepeat = 'no-repeat';
            element.style.backgroundPosition = 'right center';
            // add appropriate padding to fit the arrow
            element.style.paddingRight = '12px';
        }

        // fill in the menu item contents
        element.innerHTML = menuTableCell.data.items['contents'];

        // attach the events
        menuTableCell.onmouseover = function(in_event) { domMenu_openEvent(this, in_event, settings.items['openMouseoverSubMenuDelay']); };
        menuTableCell.onmouseout = function(in_event) { domMenu_closeEvent(this, in_event); };

        if (settings.items['openClickSubMenuDelay'] >= 0 && menuTableCell.data.items['numChildren']) {
            menuTableCell.onmousedown = function(in_event) { domMenu_openEvent(this, in_event, settings.items['openClickSubMenuDelay']); };
            menuTableCell.onmouseup = function(in_event) { var eventObj = domMenu_isIE ? event : in_event; eventObj.cancelBubble = true; };
            if (domMenu_isIE) {
                menuTableCell.ondblclick = function(in_event) { domMenu_openEvent(this, in_event, settings.items['openClickSubMenuDelay']); };
            }
        }
        else if (menuTableCell.data.items['uri']) {
            menuTableCell.style.cursor = domMenu_pointerStyle;
            menuTableCell.onclick = function(in_event) { domMenu_resolveLink(this, in_event); };
        }
        else if (!menuTableCell.data.items['numChildren']) {
            outerElement.className += ' ' + settings.items['subMenuElementHeadingClass'];
        }

        // prevent highlighting of text
        if (domMenu_isIE) {
            menuTableCell.onselectstart = function() { return false; };
        }

        menuTableCell.oncontextmenu = function() { return false; };
    }

    menu = document.body.appendChild(menu);
    domMenu_toggleSubMenu(in_parentElement, 'visible');
}

// }}}
// {{{ domMenu_changeActivePath()

/**
 * Close the old active path up to the new active element
 * and return the value of the new active element (or the same if unchanged)
 * If the new active element is not set, the top level is assumed
 *
 * @return mixed new active element or false if not set
 */
function domMenu_changeActivePath(in_newActiveElement, in_oldActiveElement, in_closeDelay)
{
    // protect against crap
    if (!in_oldActiveElement && !in_newActiveElement) {
        return false;
    }

    // cancel open timeouts since we know we are opening something different now
    for (var i in domMenu_timeouts['open'].items) {
        domMenu_cancelTimeout(i, 'open');
    }

    // grab some info about this menu system
    var basename = in_oldActiveElement ? in_oldActiveElement.data.items['basename'] : in_newActiveElement.data.items['basename'];
    var settings = domMenu_settings.items[basename];

    // build the old and new paths
    var oldActivePath = new domMenu_Hash();
    if (in_oldActiveElement) {
        var tmp_oldActivePathElement = in_oldActiveElement;
        do {
            oldActivePath.setItem(tmp_oldActivePathElement.id, tmp_oldActivePathElement); 
        } while ((tmp_oldActivePathElement = tmp_oldActivePathElement.data.items['parentElement']) && tmp_oldActivePathElement.id != basename);

        // unhighlight the old active element if it doesn't have children open
        if (!in_oldActiveElement.data.items['subMenu'] || in_oldActiveElement.data.items['subMenu'].style.visibility == 'hidden') {
            domMenu_toggleHighlight(in_oldActiveElement, false);
        }
    }

    var newActivePath = new domMenu_Hash();
    var intersectPoint;
    if (in_newActiveElement) {
        var actualActiveElement = in_newActiveElement;
        window.status = in_newActiveElement.data.items['statusText'] + ' ';

        // in the event we have no old active element, just highlight new one and return
        // without setting the new active element (handled later)
        if (!in_oldActiveElement) {
            domMenu_cancelTimeout(in_newActiveElement.id, 'close'); 
            domMenu_toggleHighlight(in_newActiveElement, true);
            return false;
        }
        // if the new element is in the path of the old element, then pretend event is
        // on the old active element
        else if (oldActivePath.hasItem(in_newActiveElement.id)) {
            in_newActiveElement = in_oldActiveElement;
        }

        var tmp_newActivePathElement = in_newActiveElement;
        do {
            // if we have met up with the old active path, then record merge point
            if (!intersectPoint && oldActivePath.hasItem(tmp_newActivePathElement.id)) {
                intersectPoint = tmp_newActivePathElement;
            }

            newActivePath.setItem(tmp_newActivePathElement.id, tmp_newActivePathElement); 
            domMenu_cancelTimeout(tmp_newActivePathElement.id, 'close'); 
            // {!} this is ugly {!}
            if (tmp_newActivePathElement != in_oldActiveElement || actualActiveElement == in_oldActiveElement) {
                domMenu_toggleHighlight(tmp_newActivePathElement, true);
            }
        } while ((tmp_newActivePathElement = tmp_newActivePathElement.data.items['parentElement']) && tmp_newActivePathElement.id != basename);

        // if we move to the child of the old active element
        if (in_newActiveElement.data.items['parentElement'] == in_oldActiveElement) {
            return in_newActiveElement;
        }
        // if the new active element is in the old active path
        else if (in_newActiveElement == in_oldActiveElement) {
            return in_newActiveElement;
        }

        // find the sibling element
        var intersectSibling;
        if (intersectPoint) {
            for (var i in oldActivePath.items) {
                if (oldActivePath.items[i].data.items['parentElement'] == intersectPoint) {
                    intersectSibling = oldActivePath.items[i];
                    break;
                }
            }
        }

        var isRootLevel = in_newActiveElement.data.items['level'] == 1 ? true : false;
        var closeDelay = isRootLevel ? settings.items['closeMouseoutMenuDelay'] : settings.items['closeMouseoutSubMenuDelay'];
    }
    else {
        var isRootLevel = false;
        var closeDelay = settings.items['closeMouseoutMenuDelay'];
        window.status = window.defaultStatus;
    }

    // override the close delay with that passed in
    if (typeof(in_closeDelay) != 'undefined') {
        closeDelay = in_closeDelay;
    }

    // if there is an intersect sibling, then we need to work from there up to 
    // preserve the active path
    if (intersectSibling) {
        // only if this is not the root level to we allow the scheduled close
        // events to persist...otherwise we close immediately
        if (!isRootLevel) {
            // toggle the sibling highlight (only one sibling highlighted at a time)
            domMenu_toggleHighlight(intersectSibling, false);
        }
        // we are moving to another top level menu
        // {!} clean this up {!}
        else {
            // add lingering menus outside of old active path to active path
            for (var i in domMenu_timeouts['close'].items) {
                if (!oldActivePath.hasItem(i)) {
                    var tmp_element = document.getElementById(i);
                    if (tmp_element.data.items['basename'] == basename) {
                        oldActivePath.setItem(i, tmp_element);
                    }
                }
            }
        }
    }

    // schedule the old active path to be closed
    for (var i in oldActivePath.items) {
        if (newActivePath.hasItem(i)) {
            continue;
        }

        // make sure we don't double schedule here
        domMenu_cancelTimeout(i, 'close');

        if (isRootLevel) {
            domMenu_toggleHighlight(oldActivePath.items[i], false); 
            domMenu_toggleSubMenu(oldActivePath.items[i], 'hidden');
        }
        else {
            var tmp_args = new Array();
            tmp_args[0] = oldActivePath.items[i];
            var tmp_function = 'domMenu_toggleHighlight(argv[0], false); domMenu_toggleSubMenu(argv[0], ' + domMenu_quote('hidden') + ');';
            // if this is the top level, then the menu is being deactivated
            if (oldActivePath.items[i].data.items['level'] == 1) {
                tmp_function += ' domMenu_activeElement.setItem(' + domMenu_quote(basename) + ', false);';
            }

            domMenu_callTimeout(tmp_function, closeDelay, tmp_args, i, 'close');
        }
    }
    
    return in_newActiveElement;
}

// }}}
// {{{ domMenu_deactivate()

function domMenu_deactivate(in_basename, in_delay)
{
    if (!in_delay) {
        in_delay = 0;
    }

    domMenu_changeActivePath(false, domMenu_activeElement.items[in_basename], in_delay);
}

// }}}
// {{{ domMenu_openEvent()

/**
 * Handle the mouse event to open a menu
 *
 * When an event is received to open the menu, this function is
 * called, handles reinitialization of the menu state and sets
 * a timeout interval for opening the submenu (if one exists)
 */
function domMenu_openEvent(in_this, in_event, in_openDelay)
{
    if (domMenu_isGecko) {
        window.getSelection().removeAllRanges();
    }

    // setup the cross-browser event object and target
    var eventObj = domMenu_isIE ? event : in_event;
    var currentTarget = domMenu_isIE ? in_this : eventObj.currentTarget;
    var basename = currentTarget.data.items['basename'];

    // if we are moving amoungst children of the same element, just ignore event
    if (eventObj.type != 'mousedown' && domMenu_getElement(eventObj[domMenu_eventFrom], basename) == currentTarget) {
        return;
    }

    // if we click on an open menu, close it
    if (eventObj.type == 'mousedown' && domMenu_activeElement.items[basename]) {
        var settings = domMenu_settings.items[basename];
        domMenu_changeActivePath(false, domMenu_activeElement.items[basename], currentTarget.data.items['level'] == 1 ? settings.items['closeClickMenuDelay'] : settings.items['closeClickSubMenuDelay']);
        return;
    }

    // if this element has children, popup the child menu
    if (currentTarget.data.items['numChildren']) {
        // the top level menus have no delay when moving between them
        // so activate submenu immediately
        if (currentTarget.data.items['level'] == 1 && domMenu_activeElement.items[basename]) {
            // ** I place changeActivePath() call here so the hiding of selects does not flicker **
            // {!} instead I could tell changeActivePath to clear select ownership but not
            // toggle visibility....hmmm....{!}
            domMenu_activateSubMenu(currentTarget);
            // clear the active path and initialize the new one
            domMenu_activeElement.setItem(basename, domMenu_changeActivePath(currentTarget, domMenu_activeElement.items[basename]));
        }
        else {
            // clear the active path and initialize the new one
            domMenu_activeElement.setItem(basename, domMenu_changeActivePath(currentTarget, domMenu_activeElement.items[basename]));
            var tmp_args = new Array();
            tmp_args[0] = currentTarget;
            var tmp_function = 'if (!domMenu_activeElement.items[' + domMenu_quote(basename) + ']) { domMenu_activeElement.setItem(' + domMenu_quote(basename) + ', argv[0]); } domMenu_activateSubMenu(argv[0]);';
            domMenu_callTimeout(tmp_function, in_openDelay, tmp_args, currentTarget.id, 'open');
        }
    }
    else {
        // clear the active path and initialize the new one
        domMenu_activeElement.setItem(basename, domMenu_changeActivePath(currentTarget, domMenu_activeElement.items[basename]));
    }
}

// }}}
// {{{ domMenu_closeEvent()

/**
 * Handle the mouse event to close a menu
 *
 * When an mouseout event is received to close the menu, this function is
 * called, sets a timeout interval for closing the menu.
 */
function domMenu_closeEvent(in_this, in_event)
{
    // setup the cross-browser event object and target
    var eventObj = domMenu_isIE ? event : in_event;
    var currentTarget = domMenu_isIE ? in_this : eventObj.currentTarget;
    var basename = currentTarget.data.items['basename'];
    var relatedTarget = domMenu_getElement(eventObj[domMenu_eventTo], basename);

    // if the related target is not a menu element then we left the menu system
    // at this point (or cannot discern where we are in the menu)
    if (domMenu_activeElement.items[basename]) {
        if (!relatedTarget) {
            domMenu_changeActivePath(false, domMenu_activeElement.items[basename]);
        }
    }
    // we are highlighting the top level, but menu is not yet 'active'
    else {
        if (currentTarget != relatedTarget) {
            domMenu_cancelTimeout(currentTarget.id, 'open');
            domMenu_toggleHighlight(currentTarget, false);
        }
    }
}    

// }}}
// {{{ domMenu_getElement()

function domMenu_getElement(in_object, in_basename)
{
    while (in_object) {
        try {
            if (in_object.id && in_object.id.search(new RegExp('^' + in_basename + '(\\[[0-9]\\])*\\[[1-9]\\]$')) == 0) {
                return in_object;
            }
            else {
                in_object = in_object.parentNode;
            }
        }
        catch(e) {
            return false;
        }
    }
    
    return false;
}

// }}}
// {{{ domMenu_detectCollisions()

function domMenu_detectCollisions(in_menuObj, in_recover)
{
    // no need to do anything for opera
    if (domMenu_isOpera) {
        return;
    }

    if (typeof(domMenu_selectElements) == 'undefined') {
        domMenu_selectElements = document.getElementsByTagName('select');
    }
    
    // if we don't have a menu, then unhide selects
    if (in_recover) {
        for (var cnt = 0; cnt < domMenu_selectElements.length; cnt++) {
            if (domMenu_isGecko && domMenu_selectElements[cnt].size <= 1 && !domMenu_selectElements[cnt].multiple) {
                continue;
            }

            var thisSelect = domMenu_selectElements[cnt];
            thisSelect.hideList.removeItem(in_menuObj.id);
            if (!thisSelect.hideList.length) {
                domMenu_selectElements[cnt].style.visibility = 'visible';
            }
        }

        return;
    }

    // okay, in_menu exists, let's hunt and destroy
    var menuOffsets = domMenu_getOffsets(in_menuObj);

    for (var cnt = 0; cnt < domMenu_selectElements.length; cnt++) {
        var thisSelect = domMenu_selectElements[cnt];

        // mozilla doesn't have a problem with regular selects
        if (domMenu_isGecko && thisSelect.size <= 1 && !thisSelect.multiple) {
            continue;
        }

        // {!} make sure this hash is congruent with domTT hash {!}
        if (!thisSelect.hideList) {
            thisSelect.hideList = new domMenu_Hash();
        }

        var selectOffsets = domMenu_getOffsets(thisSelect); 
        // for mozilla we only have to worry about the scrollbar itself
        if (domMenu_isGecko) {
            selectOffsets.setItem('left', selectOffsets.items['left'] + thisSelect.offsetWidth - domMenu_scrollbarWidth);
            selectOffsets.setItem('leftCenter', selectOffsets.items['left'] + domMenu_scrollbarWidth/2);
            selectOffsets.setItem('radius', Math.max(thisSelect.offsetHeight, domMenu_scrollbarWidth/2));
        }

        var center2centerDistance = Math.sqrt(Math.pow(selectOffsets.items['leftCenter'] - menuOffsets.items['leftCenter'], 2) + Math.pow(selectOffsets.items['topCenter'] - menuOffsets.items['topCenter'], 2));
        var radiusSum = selectOffsets.items['radius'] + menuOffsets.items['radius'];
        // the encompassing circles are overlapping, get in for a closer look
        if (center2centerDistance < radiusSum) {
            // tip is left of select
            if ((menuOffsets.items['leftCenter'] <= selectOffsets.items['leftCenter'] && menuOffsets.items['right'] < selectOffsets.items['left']) ||
            // tip is right of select
                (menuOffsets.items['leftCenter'] > selectOffsets.items['leftCenter'] && menuOffsets.items['left'] > selectOffsets.items['right']) ||
            // tip is above select
                (menuOffsets.items['topCenter'] <= selectOffsets.items['topCenter'] && menuOffsets.items['bottom'] < selectOffsets.items['top']) ||
            // tip is below select
                (menuOffsets.items['topCenter'] > selectOffsets.items['topCenter'] && menuOffsets.items['top'] > selectOffsets.items['bottom'])) {
                thisSelect.hideList.removeItem(in_menuObj.id);
                if (!thisSelect.hideList.length) {
                    thisSelect.style.visibility = 'visible';
                }
            }
            else {
                thisSelect.hideList.setItem(in_menuObj.id, true);
                thisSelect.style.visibility = 'hidden';
            }
        }
    }
}

// }}}
// {{{ domMenu_getOffsets()

function domMenu_getOffsets(in_object)
{
    var originalObject = in_object;
    var originalWidth = in_object.offsetWidth;
    var originalHeight = in_object.offsetHeight;
    var offsetLeft = 0;
    var offsetTop = 0;

    while (in_object) {
        offsetLeft += in_object.offsetLeft;
        offsetTop += in_object.offsetTop;
        in_object = in_object.offsetParent;
    }
    
    return new domMenu_Hash(
        'left',       offsetLeft,
        'top',        offsetTop,
        'right',      offsetLeft + originalWidth,
        'bottom',     offsetTop + originalHeight,
        'leftCenter', offsetLeft + originalWidth/2,
        'topCenter',  offsetTop + originalHeight/2,
        'radius',     Math.max(originalWidth, originalHeight) 
    );
}

// }}}
// {{{ domMenu_callTimeout()

function domMenu_callTimeout(in_function, in_timeout, in_args, in_basename, in_type)
{
    if (in_timeout == 0) {
        var tmp_function = new Function('argv', in_function);
        tmp_function(in_args);
    }
    else if (in_timeout > 0) {
        // after we complete the timeout call, we want to remove the reference, so always add that
        var tmp_function = new Function('argv', in_function + ' domMenu_timeouts[' + domMenu_quote(in_type) + '].removeItem(' + domMenu_quote(in_basename) + ');');

        var tmp_args = new Array();
        for (var i = 0; i < in_args.length; i++) {
            tmp_args[i] = in_args[i];
        }

        if (!domMenu_isKonq && !domMenu_isIE50) {
            domMenu_timeouts[in_type].setItem(in_basename, setTimeout(function() { tmp_function(tmp_args); }, in_timeout));
        }
        else {
            var tmp_data = new Array();
            tmp_data['function'] = tmp_function;
            tmp_data['args'] = tmp_args;
            domMenu_timeoutStates[in_type].setItem(in_basename, tmp_data);
            var tmp_type = domMenu_quote(in_type);
            var tmp_basename = domMenu_quote(in_basename);

            domMenu_timeouts[in_type].setItem(in_basename, setTimeout('domMenu_timeoutStates[' + tmp_type + '].items[' + tmp_basename + '][' + domMenu_quote('function') + '](domMenu_timeoutStates[' + tmp_type + '].items[' + tmp_basename + '][' + domMenu_quote('args') + ']); domMenu_timeoutStates[' + tmp_type + '].removeItem(' + tmp_basename + ');', in_timeout));
        }
    }
}

// }}}
// {{{ domMenu_cancelTimeout()

function domMenu_cancelTimeout(in_basename, in_type)
{
    // take advantage of browsers which use the anonymous function
    if (!domMenu_isKonq && !domMenu_isIE50) {
        clearTimeout(domMenu_timeouts[in_type].removeItem(in_basename));
    }
    else {
        // if konqueror, we only want to clearTimeout if it is still running
        if (domMenu_timeoutStates[in_type].hasItem(in_basename)) {
            clearTimeout(domMenu_timeouts[in_type].removeItem(in_basename));
            domMenu_timeoutStates[in_type].removeItem(in_basename);
        }
    }
}

// }}}
// {{{ domMenu_correctEdgeBleed()

function domMenu_correctEdgeBleed(in_width, in_height, in_x, in_y, in_padding, in_axis)
{
    if (domMenu_isIE && !domMenu_isIE5) {
        var pageHeight = document.documentElement.clientHeight;
    }
    else if (!domMenu_isKonq) {
        var pageHeight = document.body.clientHeight;
    }
    else {
        var pageHeight = window.innerHeight;
    }

    var pageYOffset = domMenu_isIE ? document.body.scrollTop : window.pageYOffset;
    var pageXOffset = domMenu_isIE ? document.body.scrollLeft : window.pageXOffset;
    

    if (in_axis == 'horizontal') {
        var bleedRight = (in_x - pageXOffset) + in_width - (document.body.clientWidth - in_padding);
        var bleedLeft = (in_x - pageXOffset) - in_padding;

        // we are bleeding off the right, move menu to stay on page
        if (bleedRight > 0) {
            in_x -= bleedRight;
        }

        // we are bleeding to the left, move menu over to stay on page
        // we don't want an 'else if' here, because if it doesn't fit we will bleed off the right
        if (bleedLeft < 0) {
            in_x += bleedLeft;
        }
    }
    else {
        var bleedTop = (in_y - pageYOffset) - in_padding;
        var bleedBottom = (in_y - pageYOffset) + in_height - (pageHeight - in_padding);
        
        // if we are bleeding off the bottom, move menu to stay on page
        if (bleedBottom > 0) {
            in_y -= bleedBottom;
        }

        // if we are bleeding off the top, move menu down
        // we don't want an 'else if' here, because if we just can't fit it, bleed off the bottom
        if (bleedTop < 0) {
            in_y += bleedTop;
        }
    }
    
    return new Array(in_x, in_y);
}

// }}}
// {{{ domMenu_toggleSubMenu()

function domMenu_toggleSubMenu(in_parentElement, in_style)
{
    var subMenu = in_parentElement.data.items['subMenu'];
    if (subMenu && subMenu.style.visibility != in_style) {
        var settings = domMenu_settings.items[in_parentElement.data.items['basename']];
        var prefix = in_parentElement.data.items['level'] == 1 ? 'menu' : 'subMenu';
        var className = settings.items[prefix + 'ElementClass'];
		// :BUG: this is a problem if submenus click to open, then it won't
		// have the right class when you click to close
		if (in_style == 'visible') {
            className += ' ' + settings.items[prefix + 'Element' + (in_style == 'visible' ? 'Active' : 'Hover') + 'Class'];
		}

        in_parentElement.firstChild.className = className;
        
        // position our submenu
        if (in_style == 'visible') {
            var tmp_offsets = domMenu_getOffsets(in_parentElement);
            if (in_parentElement.data.items['level'] == 1) {
                tmp_offsets.items['top'] += settings.items['verticalSubMenuOffsetY'];
                tmp_offsets.items['bottom'] += settings.items['verticalSubMenuOffsetY'];
                tmp_offsets.items['left'] += settings.items['verticalSubMenuOffsetX'];
                tmp_offsets.items['right'] += settings.items['verticalSubMenuOffsetX'];
            }

            // reposition if there was a change in the parent position/size
            if (!in_parentElement.data.items['offsets'].compare(tmp_offsets)) {
                in_parentElement.data.items['offsets'] = tmp_offsets;

                if (settings.items['axis'] == 'horizontal' && in_parentElement.data.items['level'] == 1) {
                    var xCoor = tmp_offsets.items['left'];
                    if (settings.items['verticalExpand'] == 'north') {
                        var yCoor = tmp_offsets.items['top'] - subMenu.offsetHeight - settings.items['verticalSubMenuOffsetY'];
                    }
                    else {
                        var yCoor = tmp_offsets.items['bottom'];
                    }
                }
                else {
                    var xCoor = tmp_offsets.items['right'] + settings.items['horizontalSubMenuOffsetX'];
                    var yCoor = tmp_offsets.items['top'] + settings.items['horizontalSubMenuOffsetY'];
                }

                var minWidth = settings.items['subMenuMinWidth'];
                var renderedWidth = subMenu.offsetWidth;
                if (minWidth == 'inherit') {
                    minWidth = in_parentElement.offsetWidth + settings.items['subMenuWidthCorrection'];
                }
                else if (minWidth == 'auto') {
                    minWidth = renderedWidth;
                }

                if (domMenu_isKonq) {
                    // change with width of the first cell
                    subMenu.firstChild.firstChild.firstChild.firstChild.style.width = Math.max(minWidth, renderedWidth) + 'px';
                }
                else {
                    // change the width of the table
                    subMenu.firstChild.style.width = Math.max(minWidth, renderedWidth) + 'px';
                }
                
                var coordinates = domMenu_correctEdgeBleed(subMenu.offsetWidth, subMenu.offsetHeight, xCoor, yCoor, settings.items['screenPadding'], settings.items['axis']);
                subMenu.style.left = coordinates[0] + 'px';
                subMenu.style.top = coordinates[1] + 'px';

                // ** if we inherit, it is necessary to check the parent element width again **
                if (settings.items['axis'] == 'horizontal' && settings.items['subMenuMinWidth'] == 'inherit') {
                    subMenu.firstChild.style.width = Math.max(in_parentElement.offsetWidth + settings.items['subMenuWidthCorrection'], renderedWidth) + 'px';
                }
            }
        }

        // force konqueror to change the styles
        if (domMenu_isKonq) {
            in_parentElement.firstChild.style.display = 'none';
            in_parentElement.firstChild.style.display = '';
        }

        subMenu.style.visibility = in_style;
        domMenu_detectCollisions(subMenu, (in_style == 'hidden'));
    }
}

// }}}
// {{{ domMenu_toggleHighlight()

function domMenu_toggleHighlight(in_element, in_status)
{
    // if this is a heading, don't change the style
    if (!in_element.data.items['numChildren'] && !in_element.data.items['uri']) {
        return;
    }

    var settings = domMenu_settings.items[in_element.data.items['basename']];
    var prefix = in_element.data.items['level'] == 1 ? 'menu' : 'subMenu';
    var className = settings.items[prefix + 'ElementClass'];
    var highlightElement = in_element.firstChild;

    var pseudoClass;
    if (in_status) {
        if (in_element.data.hasItem('subMenu') && in_element.data.items['subMenu'].style.visibility == 'visible') {
            pseudoClass = 'Active';
        }
        else if (in_element.data.items['numChildren'] || in_element.data.items['uri']) {
            pseudoClass = 'Hover';
        }
    }

    if (pseudoClass) {
        className += ' ' + settings.items[prefix + 'Element' + pseudoClass + 'Class'];
        // if we are changing to hover, change the alt contents (only change if needs it)
        if (highlightElement.childNodes.length == 2 && highlightElement.lastChild.style.display == 'none') {
            highlightElement.firstChild.style.display = 'none';
            highlightElement.lastChild.style.display = '';
        }
    }
    else {
        // if we are changing to non-hover, change the alt contents (only change if needs it)
        if (highlightElement.childNodes.length == 2 && highlightElement.firstChild.style.display == 'none') {
            highlightElement.lastChild.style.display = 'none';
            highlightElement.firstChild.style.display = '';
        }
    }

    highlightElement.className = className;

    // force konqueror to change the styles
    if (domMenu_isKonq) {
        highlightElement.style.display = 'none';
        highlightElement.style.display = '';
    }
}

// }}}
// {{{ domMenu_resolveLink()

function domMenu_resolveLink(in_this, in_event)
{
    var eventObj = domMenu_isIE ? event : in_event;
    var currentTarget = domMenu_isIE ? in_this : eventObj.currentTarget;
    var basename = currentTarget.data.items['basename'];

    // close the menu system immediately when we resolve the uri
    domMenu_changeActivePath(false, domMenu_activeElement.items[basename], 0);

    if (currentTarget.data.items['uri']) {
        window.status = 'Resolving Link...';

        // open in current window
        if (!currentTarget.data.items['target'] || currentTarget.data.items['target'] == '_self') {
            window.location = currentTarget.data.items['uri'];
        }
        // open in new window
        else {
            window.open(currentTarget.data.items['uri'], currentTarget.data.items['target']);
        }
    }
}

// }}}
// {{{ domMenu_quote()

function domMenu_quote(in_string)
{
    return "'" + in_string.replace(new RegExp("'", 'g'), "\\'") + "'";
}

// }}}
