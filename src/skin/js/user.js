/**
 * List of all the available skins
 *
 * @type Array
 */
var mySkins = [
    'skin-blue',
    'skin-black',
    'skin-red',
    'skin-yellow',
    'skin-purple',
    'skin-green',
    'skin-blue-light',
    'skin-black-light',
    'skin-red-light',
    'skin-yellow-light',
    'skin-purple-light',
    'skin-green-light'
]

/**
 * Replaces the old skin with the new skin
 * @param String cls the new skin class
 * @returns Boolean false to prevent link's default action
 */
function changeSkin(cls) {
    $.each(mySkins, function (i) {
        $('body').removeClass(mySkins[i])
    })

    $('body').addClass(cls)
    store('skin', cls)
    return false
}

// Add the change skin listener
$('[data-skin]').on('click', function (e) {
    $.ajax({
        type: 'POST',
        url: window.CRM.root + '/api/users/' + window.CRM.viewUserId + '/setting/style',
        data: {"value": $(this).data('skin')}
    })
    if (window.CRM.viewUserId == window.CRM.userId) {
        if ($(this).hasClass('knob'))
            return
        e.preventDefault()
        changeSkin($(this).data('skin'))
    }
})

$("#regenApiKey").click(function () {
    $.ajax({
        type: 'POST',
        url: window.CRM.root + '/api/users/' + window.CRM.viewUserId + '/apikey/regen'
    })
        .done(function (data, textStatus, xhr) {
            if (xhr.status == 200) {
                $("#apiKey").val(data.apiKey);
            } else {
                showGlobalMessage(i18next.t("Failed generate a new API Key"), "danger")
            }
        });
});
