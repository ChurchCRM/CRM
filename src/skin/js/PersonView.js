$(document).ready(function () {

    $("#input-person-properties").on("select2:select", function (event) {
        promptBox = $("#prompt-box");
        promptBox.removeClass('form-group').html('');
        selected = $("#input-person-properties :selected");
        pro_prompt = selected.data('pro_prompt');
        pro_value = selected.data('pro_value');
        if (pro_prompt) {
            promptBox
                .addClass('form-group')
                .append(
                    $('<label></label>').html(pro_prompt)
                )
                .append(
                    $('<textarea rows="3" class="form-control" name="PropertyValue"></textarea>').val(pro_value)
                );
        }

    });

    $('#assign-property-form').submit(function (event) {
        event.preventDefault();
        var thisForm = $(this);
        var url = thisForm.attr('action');
        var dataToSend = thisForm.serialize();

        $.ajax({
            type: 'POST',
            url: url,
            data: dataToSend,
            dataType: 'json',
            success: function (data, status, xmlHttpReq) {
                if (data && data.success) {
                    location.reload();
                }
            }
        });

    });

    $('.remove-property-btn').click(function (event) {
        event.preventDefault();
        var thisLink = $(this);
        var dataToSend = {
            PersonId: thisLink.data('person_id'),
            PropertyId: thisLink.data('property_id')
        };
        var url = window.CRM.root + '/api/properties/persons/unassign';

        bootbox.confirm('Are you sure you want to unassign this property?', function (result) {
            if (result) {
                $.ajax({
                    type: 'DELETE',
                    url: url,
                    data: dataToSend,
                    dataType: 'json',
                    success: function (data, status, xmlHttpReq) {
                        if (data && data.success) {
                            location.reload();
                        }
                    }
                });
            }
        });

    });

    $('#delete-person').click(function (event) {
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
                            location.replace( window.CRM.root + "/");
                        }
                    });
                }
            }
        });
    });
});
