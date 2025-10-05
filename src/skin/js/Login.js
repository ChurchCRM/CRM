$(document).ready(function () {
    $("#Lock").hide();
    document.title = "Login";
});

var $buoop = {
    vs: { i: 13, f: -2, o: -2, s: 9, c: -2 },
    unsecure: true,
    api: 4,
};
function $buo_f() {
    var e = document.createElement("script");
    e.src = "//browser-update.org/update.min.js";
    document.body.appendChild(e);
}

try {
    document.addEventListener("DOMContentLoaded", $buo_f, false);
} catch (e) {
    window.attachEvent("onload", $buo_f);
}

$("#password").password("toggle");
$("#password").password({
    eyeOpenClass: "glyphicon-eye-open",
    eyeCloseClass: "glyphicon-eye-close",
});
