function contentExists(contentUrl, callback) {
    $.ajax({
        method :"HEAD",
        url: contentUrl,
        processData: false,
        global:false,
        success: function(data, textStatus, jqXHR){
            callback(true, data, textStatus, jqXHR);
        },
        error: function(jqXHR, textStatus, errorThrown) {
            callback(false, jqXHR, textStatus, errorThrown);
        }
    });
}

$('.delete-person').click(function (event) {
    event.preventDefault();
    var thisLink = $(this);
    bootbox.confirm({
        title: "Delete this person?",
        message: "Do you want to delete <b>" + thisLink.data('person_name')  + "</b>? This cannot be undone.",
        buttons: {
            cancel: {
                label: '<i class="fa fa-times"></i> Cancel'
            },
            confirm: {
                label: '<i class="fa fa-trash-o"></i> Delete'
            }
        },
        callback: function (result) {
            if(result) {
                $.ajax({
                    type: 'DELETE',
                    url: window.CRM.root + '/api/persons/' + thisLink.data('person_id'),
                    dataType: 'json',
                    success: function (data, status, xmlHttpReq) {
                        if (thisLink.data('view') == 'family') {
                            location.reload();
                        } else {
                            location.replace(window.CRM.root + "/");
                        }
                    }
                });
            }
        }
    });
});
