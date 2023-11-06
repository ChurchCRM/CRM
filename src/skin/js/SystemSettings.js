function getRender(key, value, depth) {
    var sr = $("<div>")
        .addClass("JSONObjectDiv")
        .data("nodeName", key)
        .css("margin-left", depth * 15 + "px");
    if (value instanceof Object) {
        $("<label>").text(key).appendTo(sr);
        $.each(value, function (key, value) {
            sr.append(getRender(key, value, depth + 1));
        });
    } else {
        $("<label>").text(key).css("margin-right", "15px").appendTo(sr);
        $("<input>").attr("type", "text").val(value).appendTo(sr);
    }
    return sr;
}
var cfgid = 0;
$(".jsonSettingsEdit").on("click", function (event) {
    event.preventDefault();
    cfgid = $(this).data("cfgid");
    var cfgvalue = jQuery.parseJSON(
        $("input[name='new_value[" + cfgid + "]']").val(),
    );
    console.log(cfgvalue);
    $("#JSONSettingsDiv").html("");
    $.each(cfgvalue, function (key, value) {
        $("#JSONSettingsDiv").append(getRender(key, value, 0));
    });

    $("#JSONSettingsModal").modal("show");
});

function getFormValue(object) {
    var tmp = {};
    if ($(object).children(".JSONObjectDiv").length > 0) {
        $(object)
            .children(".JSONObjectDiv")
            .each(function () {
                tmp[$(this).data("nodeName")] = getFormValue($(this));
            });
        return tmp;
    } else if ($(object).children("input").length > 0) {
        return $("input", object).val();
    }
}

function updateDropDrownFromAjax(selectObj) {
    $.ajax({
        method: "GET",
        url: window.CRM.root + selectObj.data("url"),
        dataType: "json",
        encode: true,
    }).done(function (data) {
        $.each(data, function (index, config) {
            var optSelected = config.id == selectObj.data("value");
            var opt = new Option(
                config.value,
                config.id,
                optSelected,
                optSelected,
            );
            selectObj.append(opt);
        });
    });
}

$(".jsonSettingsClose").on("click", function (event) {
    var settings = getFormValue($("#JSONSettingsDiv"));
    $("input[name='new_value[" + cfgid + "]']").val(JSON.stringify(settings));
    $("#JSONSettingsModal").modal("hide");
    $("input[name=save]").click();
});

$(".setting-tip").click(function () {
    bootbox.alert({
        message: $(this).data("tip"),
        backdrop: true,
        className: "setting-tip-box",
    });
});
