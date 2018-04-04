$(document).ready(function () {
  
  $('.changeRole').click(function(event) {
    var GroupID = $(this).data("groupid");
    window.CRM.groups.promptSelection({Type:window.CRM.groups.selectTypes.Role,GroupID:GroupID},function(selection){
      window.CRM.groups.addPerson(GroupID,window.CRM.currentPersonID,selection.RoleID).done(function(){
        location.reload();
      })
      
    });
  });

  $(".groupRemove").click(function(event){
    var targetGroupID = event.currentTarget.dataset.groupid;
    var targetGroupName = event.currentTarget.dataset.groupname;
    
    bootbox.confirm({
      message: i18next.t("Are you sure you want to remove this person's membership from") + " " + targetGroupName + "?",
      buttons: {
        confirm: {
          label: i18next.t('Yes'),
            className: 'btn-success'
        },
        cancel: {
          label: i18next.t('No'),
          className: 'btn-danger'
        }
      },
      callback: function (result)
      {
        if (result)
        {
          window.CRM.groups.removePerson(targetGroupID,window.CRM.currentPersonID).done(
            function(){
              location.reload()
            }
          ); 
        }
      }
    });
  })

  $("#addGroup").click(function() {
    var target = window.CRM.groups.promptSelection({Type:window.CRM.groups.selectTypes.Group | window.CRM.groups.selectTypes.Role}, function(data){
      window.CRM.groups.addPerson(data.GroupID,window.CRM.currentPersonID,data.RoleID).done(function(){
          location.reload()
        }
      );
    });
  });
  
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

        bootbox.confirm(i18next.t('Are you sure you want to unassign this property?'), function (result) {
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
    
    $('#edit-role-btn').click(function (event) {
        event.preventDefault();
        var thisLink = $(this);
        var personId = thisLink.data('person_id');
        var familyRoleId = thisLink.data('family_role_id');
        var familyRole = thisLink.data('family_role');
        
        $.ajax({
            type: 'GET',
            dataType: 'json',
            url: window.CRM.root + '/api/persons/roles',
            success: function (data, status, xmlHttpReq) {
                if (data.length) {
                    roles = [{text: familyRole, value: ''}];
                    for (var i=0; i < data.length; i++) {
                        if (data[i].OptionId == familyRoleId) {
                            continue;
                        }
                        
                        roles[roles.length] = {
                            text: data[i].OptionName,
                            value: data[i].OptionId
                        };
                    }
                    
                    bootbox.prompt({
                        title:i18next.t( 'Change role'),
                        inputType: 'select',
                        inputOptions: roles,
                        callback: function (result) {
                            if (result) {
                                $.ajax({
                                    type: 'POST',
                                    dataType: 'json',
                                    url: window.CRM.root + '/api/person/'+ personId +'/role/' +result,
                                    success: function (data, status, xmlHttpReq) {
                                        if (data.success) {
                                            location.reload();
                                        }
                                    }
                                });
                            }
                            
                        }
                    });
                    
                }
            }
        });
        
    });
    
    
    
});
