// {{{ global constants

/**
 * Global constants (DO NOT EDIT)
 */

// browsers
var domLib_userAgent = navigator.userAgent.toLowerCase();
var domLib_isOpera = domLib_userAgent.indexOf("opera 7") !== -1 ? 1 : 0;
var domLib_isKonq = domLib_userAgent.indexOf("konq") !== -1 ? 1 : 0;
var domLib_isIE =
    !domLib_isKonq &&
    !domLib_isOpera &&
    (domLib_userAgent.indexOf("msie 5") !== -1 ||
        domLib_userAgent.indexOf("msie 6") !== -1);
var domLib_isIE5up = domLib_isIE;
var domLib_isIE50 = domLib_isIE && domLib_userAgent.indexOf("msie 5.0") !== -1;
var domLib_isIE55 = domLib_isIE && domLib_userAgent.indexOf("msie 5.5") !== -1;
var domLib_isIE5 = domLib_isIE50 || domLib_isIE55;
var domLib_isIE55up = domLib_isIE5up && !domLib_isIE50;
var domLib_isIE6up = domLib_isIE55up && !domLib_isIE55;
var domLib_isGecko = domLib_userAgent.indexOf("gecko") !== -1 ? 1 : 0;

// abilities
var domLib_useLibrary =
    domLib_isOpera || domLib_isKonq || domLib_isIE5up || domLib_isGecko ? 1 : 0;
var domLib_canTimeout = !(domLib_isKonq || domLib_isIE50);
var domLib_canFade = domLib_isGecko || domLib_isIE55up;

// event variables
var domLib_eventTarget = domLib_isIE ? "srcElement" : "currentTarget";
var domLib_eventButton = domLib_isIE ? "button" : "which";
var domLib_eventTo = domLib_isIE ? "toElement" : "relatedTarget";
var domLib_stylePointer = domLib_isIE ? "hand" : "pointer";
// :FIX: bug in Opera that it can't set maxWidth to 'none'
var domLib_styleNoMaxWidth = domLib_isOpera ? "10000px" : "none";
var domLib_hidePosition = "-1000px";
var domLib_scrollbarWidth = 14;
var domLib_autoId = 1;
var domLib_zIndex = 100;

// detection
var domLib_selectElements;

var domLib_timeoutStateId = 0;
var domLib_timeoutStates = new Hash();

// }}}
// {{{ Object.prototype.clone

Object.prototype.clone = function () {
    var copy = {};
    for (var i in this) {
        var value = this[i];
        try {
            if (
                value != null &&
                typeof value == "object" &&
                value != window &&
                !value.nodeType
            ) {
                // for IE5 which doesn't inherit prototype
                value.clone = Object.clone;
                copy[i] = value.clone();
            } else {
                copy[i] = value;
            }
        } catch (e) {
            copy[i] = value;
        }
    }

    return copy;
};

// }}}
// {{{ class Hash()

function Hash() {
    this.length = 0;
    this.elementData = [];
    for (var i = 0; i < arguments.length; i += 2) {
        if (typeof arguments[i + 1] != "undefined") {
            this.elementData[arguments[i]] = arguments[i + 1];
            this.length++;
        }
    }

    this.get = function (in_key) {
        return this.elementData[in_key];
    };

    this.set = function (in_key, in_value) {
        if (typeof in_value != "undefined") {
            if (typeof this.elementData[in_key] == "undefined") {
                this.length++;
            }

            return (this.elementData[in_key] = in_value);
        }

        return false;
    };

    this.remove = function (in_key) {
        var tmp_value;
        if (typeof this.elementData[in_key] != "undefined") {
            this.length--;
            tmp_value = this.elementData[in_key];
            delete this.elementData[in_key];
        }

        return tmp_value;
    };

    this.size = function () {
        return this.length;
    };

    this.has = function (in_key) {
        return typeof this.elementData[in_key] != "undefined";
    };
}

// }}}
// {{{ domLib_isDescendantOf()

function domLib_isDescendantOf(in_object, in_ancestor) {
    if (in_object == in_ancestor) {
        return true;
    }

    while (in_object != document.documentElement) {
        try {
            if (
                (tmp_object = in_object.offsetParent) &&
                tmp_object == in_ancestor
            ) {
                return true;
            } else if ((tmp_object = in_object.parentNode) == in_ancestor) {
                return true;
            } else {
                in_object = tmp_object;
            }
        } catch (e) {
            // in case we get some wierd error, just assume we haven't gone out yet
            return true;
        }
    }

    return false;
}

// }}}
// {{{ domLib_detectCollisions()

// :WARNING: hideList is being used as an object property and is not a string
function domLib_detectCollisions(in_object, in_recover) {
    // no need to do anything for opera
    if (domLib_isOpera) {
        return;
    }

    if (typeof domLib_selectElements == "undefined") {
        domLib_selectElements = document.getElementsByTagName("select");
    }

    // if we don't have a tip, then unhide selects
    if (in_recover) {
        for (var cnt = 0; cnt < domLib_selectElements.length; cnt++) {
            var thisSelect = domLib_selectElements[cnt];

            if (!thisSelect.hideList) {
                thisSelect.hideList = new Hash();
            }

            // if this is mozilla and it is a regular select or it is multiple and the
            // size is not set, then we don't need to unhide
            if (
                domLib_isGecko &&
                (!thisSelect.multiple || thisSelect.size < 0)
            ) {
                continue;
            }

            thisSelect.hideList.remove(in_object.id);
            if (!thisSelect.hideList.length) {
                domLib_selectElements[cnt].style.visibility = "visible";
            }
        }

        return;
    }

    // okay, we have a tip, so hunt and destroy
    var objectOffsets = domLib_getOffsets(in_object);

    for (var cnt = 0; cnt < domLib_selectElements.length; cnt++) {
        var thisSelect = domLib_selectElements[cnt];

        // if this is mozilla and not a multiple-select or the multiple select size
        // is not defined, then continue since mozilla does not have an issue
        if (domLib_isGecko && (!thisSelect.multiple || thisSelect.size < 0)) {
            continue;
        }

        // if the select is in the tip, then skip it
        // :WARNING: is this too costly?
        if (domLib_isDescendantOf(thisSelect, in_object)) {
            continue;
        }

        if (!thisSelect.hideList) {
            thisSelect.hideList = new Hash();
        }

        var selectOffsets = domLib_getOffsets(thisSelect);
        // for mozilla we only have to worry about the scrollbar itself
        if (domLib_isGecko) {
            selectOffsets.set(
                "left",
                selectOffsets.get("left") +
                    thisSelect.offsetWidth -
                    domLib_scrollbarWidth,
            );
            selectOffsets.set(
                "leftCenter",
                selectOffsets.get("left") + domLib_scrollbarWidth / 2,
            );
            selectOffsets.set(
                "radius",
                Math.max(thisSelect.offsetHeight, domLib_scrollbarWidth / 2),
            );
        }

        var center2centerDistance = Math.sqrt(
            Math.pow(
                selectOffsets.get("leftCenter") -
                    objectOffsets.get("leftCenter"),
                2,
            ) +
                Math.pow(
                    selectOffsets.get("topCenter") -
                        objectOffsets.get("topCenter"),
                    2,
                ),
        );
        var radiusSum =
            selectOffsets.get("radius") + objectOffsets.get("radius");
        // the encompassing circles are overlapping, get in for a closer look
        if (center2centerDistance < radiusSum) {
            // tip is left of select
            if (
                (objectOffsets.get("leftCenter") <=
                    selectOffsets.get("leftCenter") &&
                    objectOffsets.get("right") < selectOffsets.get("left")) ||
                // tip is right of select
                (objectOffsets.get("leftCenter") >
                    selectOffsets.get("leftCenter") &&
                    objectOffsets.get("left") > selectOffsets.get("right")) ||
                // tip is above select
                (objectOffsets.get("topCenter") <=
                    selectOffsets.get("topCenter") &&
                    objectOffsets.get("bottom") < selectOffsets.get("top")) ||
                // tip is below select
                (objectOffsets.get("topCenter") >
                    selectOffsets.get("topCenter") &&
                    objectOffsets.get("top") > selectOffsets.get("bottom"))
            ) {
                thisSelect.hideList.remove(in_object.id);
                if (!thisSelect.hideList.length) {
                    thisSelect.style.visibility = "visible";
                }
            } else {
                thisSelect.hideList.set(in_object.id, true);
                thisSelect.style.visibility = "hidden";
            }
        }
    }
}

// }}}
// {{{ domLib_getOffsets()

function domLib_getOffsets(in_object) {
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

    return new Hash(
        "left",
        offsetLeft,
        "top",
        offsetTop,
        "right",
        offsetLeft + originalWidth,
        "bottom",
        offsetTop + originalHeight,
        "leftCenter",
        offsetLeft + originalWidth / 2,
        "topCenter",
        offsetTop + originalHeight / 2,
        "radius",
        Math.max(originalWidth, originalHeight),
    );
}

// }}}
// {{{ domLib_setTimeout()

function domLib_setTimeout(in_function, in_timeout, in_args) {
    if (typeof in_args == "undefined") {
        in_args = [];
    }

    if (in_timeout == 0) {
        in_function(in_args);
        return 0;
    }

    // must make a copy of the arguments so that we release the reference
    if (typeof in_args.clone != "function") {
        in_args.clone = Object.clone;
    }

    var args = in_args.clone();

    if (domLib_canTimeout) {
        return setTimeout(function () {
            in_function(args);
        }, in_timeout);
    } else {
        var id = domLib_timeoutStateId++;
        var data = new Hash();
        data.set("function", in_function);
        data.set("args", args);
        domLib_timeoutStates.set(id, data);

        data.set(
            "timeoutId",
            setTimeout(
                "domLib_timeoutStates.get(" +
                    id +
                    ").get('function')(domLib_timeoutStates.get(" +
                    id +
                    ").get('args')); domLib_timeoutStates.remove(" +
                    id +
                    ");",
                in_timeout,
            ),
        );
        return id;
    }
}

// }}}
// {{{ domLib_clearTimeout()

function domLib_clearTimeout(in_id) {
    if (domLib_canTimeout) {
        clearTimeout(in_id);
    } else {
        if (domLib_timeoutStates.has(in_id)) {
            clearTimeout(domLib_timeoutStates.get(in_id).get("timeoutId"));
            domLib_timeoutStates.remove(in_id);
        }
    }
}

// }}}
// {{{ domLib_getEventPosition()

function domLib_getEventPosition(in_eventObj) {
    var eventPosition = new Hash();
    if (domLib_isKonq) {
        eventPosition.set("x", in_eventObj.x);
        eventPosition.set("y", in_eventObj.y);
    } else if (domLib_isIE) {
        if (document.documentElement.clientHeight) {
            eventPosition.set(
                "x",
                in_eventObj.clientX + document.documentElement.scrollLeft,
            );
            eventPosition.set(
                "y",
                in_eventObj.clientY + document.documentElement.scrollTop,
            );
        }
        // :WARNING: consider case where document.body doesn't yet exist for IE
        else {
            eventPosition.set(
                "x",
                in_eventObj.clientX + document.body.scrollLeft,
            );
            eventPosition.set(
                "y",
                in_eventObj.clientY + document.body.scrollTop,
            );
        }
    } else {
        eventPosition.set("x", in_eventObj.pageX);
        eventPosition.set("y", in_eventObj.pageY);
    }

    return eventPosition;
}

// }}}
// {{{ makeTrue()

function makeTrue() {
    return true;
}

// }}}
// {{{ makeFalse()

function makeFalse() {
    return false;
}

// }}}
