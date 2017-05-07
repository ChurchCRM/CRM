/*
 * ChurcmCRM JavaScript Object Model Initailizaion Script
 */

    window.CRM.APIRequest = function(options) {
      options.url=window.CRM.root +"/api/" + options.path;
      options.dataType = 'json';
      options.ontentType =  "application/json";
      return $.ajax(options);
    }

    window.CRM.DisplayErrorMessage = function(endpoint, error) {

      message = "<p>Error making API Call to: " + endpoint +
        "</p><p>Error text: " + error.message;
      if (error.trace)
      {
        message += "</p>Stack Trace: <pre>"+JSON.stringify(error.trace, undefined, 2)+"</pre>";
      }
      bootbox.alert({
        title: "ERROR",
        message: message
      });
    };

    window.CRM.VerifyThenLoadAPIContent = function(url) {
      var error = "There was a problem retrieving the requested object";
      $.ajax({
        type: 'HEAD',
        url: url,
        async: false,
        statusCode: {
          200: function() {
            window.open(url);
          },
          404: function() {
            window.CRM.DisplayErrorMessage(url, {message: error});
          },
          500: function() {
            window.CRM.DisplayErrorMessage(url, {message: error});
          }
        }
      });
    }
    
    window.CRM.cart={
      'empty' : function ()
      {
          $.ajax({
                  method: "DELETE",
                  url: window.CRM.root + "/api/cart/",
                  contentType: "application/json; charset=utf-8",
                  dataType: "json"
              }).done(function (data) {
                  window.CRM.cart.refresh();
              });
      },
      'emptyToGroup' : function (groupID,groupRoleID,callback)
      {
        window.CRM.groups.promptSelection(function(selectedRole){
          $.ajax({
            type: 'POST',
            url: window.CRM.root + '/api/cart/emptyToGroup',
            dataType: 'json',
            contentType: "application/json",
            data: JSON.stringify({"groupID":selectedRole.GroupID,"groupRoleID":selectedRole.RoleID})
            }).done(function(data) {
                window.CRM.cart.refresh();
                if(callback)
                {
                  callback(data);
                }

            });
        });
      },
      'emptytoFamily' : function ()
      {
          
      },
      'emptytoEvent' : function ()
      {
          
      },
      'addPerson' : function (Persons, callback)
      {
         $.ajax({
          type: 'POST',
          url: window.CRM.root + '/api/cart/',
          dataType: 'json',
          contentType: "application/json",
          data: JSON.stringify({"Persons":Persons})
        }).done(function(data) {
            window.CRM.cart.refresh();
            if(callback)
            {
              callback(data);
            }
            
        });
      },
      'removePerson' : function (Persons, callback)
      {
         $.ajax({
          type: 'DELETE',
          url: window.CRM.root + '/api/cart/',
          dataType: 'json',
          contentType: "application/json",
          data: JSON.stringify({"Persons":Persons})
        }).done(function(data) {
            window.CRM.cart.refresh();
            if(callback)
            {
              callback(data);
            }
            
        });
      },
      'addFamily' : function (FamilyID, callback)
      {
         $.ajax({
          type: 'POST',
          url: window.CRM.root + '/api/cart/',
          dataType: 'json',
          contentType: "application/json",
          data: JSON.stringify({"Family":FamilyID})
        }).done(function(data) {
            window.CRM.cart.refresh();
            if(callback)
            {
              callback(data);
            }
            
        });
      },
      'addGroup' : function (GroupID, callback)
      {
         $.ajax({
          type: 'POST',
          url: window.CRM.root + '/api/cart/',
          dataType: 'json',
          contentType: "application/json",
          data: JSON.stringify({"Group":GroupID})
        }).done(function(data) {
            window.CRM.cart.refresh();
            if(callback)
            {
              callback(data);
            }
            
        });
      },
      'refresh' : function () {
         $.ajax({
          type: 'GET',
          url: window.CRM.root + '/api/cart/',
          dataType: 'json',
          contentType: "application/json"
          } ).done(function(data) {
            window.scrollTo(0, 0);
            $("#iconCount").text(data.PeopleCart.length);
            $("#CartBlock")
            .animate({'left':(-10)+'px'},200)
            .animate({'left':(+10)+'px'},200)
            .animate({'left':(0)+'px'},200);

          });
        
      }

      }
    
    window.CRM.groups = {
      
      'get': function() {
        return window.CRM.APIRequest({
          path:"groups/",
          type:"GET"
        }); 
      },
      
      'getRoles': function(GroupID) {
        return window.CRM.APIRequest({
          path:"groups/"+GroupID+"/roles",
          type:"GET"
        }); 
      },
      'promptSelection': function(selectionCallback)
      {
        bootbox.dialog({
           title: 'Select Group and Role',
           message: '<div class="modal-body">\
                <input type="hidden" id="targetGroupAction">\
                <span style="color: red">Please select target group for members:</span>\
                <select name="targetGroupSelection" id="targetGroupSelection" class="form-control"></select>\
                <select name="targetRoleSelection" id="targetRoleSelection" class="form-control"></select>\
              </div>',
           buttons: {
             confirm: {
                 label: 'OK',
                 className: 'btn-success',
                 callback: function(){
                   selectionCallback({
                     'GroupID': $("#targetGroupSelection option:selected").val(),
                     'RoleID' : $("#targetRoleSelection option:selected").val()
                   });
                }
             },
             cancel: {
                 label: 'Cancel',
                 className: 'btn-danger'
             }
           }
        }).show();
        
        window.CRM.groups.get()
        .done(function(rdata){
          groupsList = $.map(rdata.Groups, function (item) {
            var o = {
              text: item.Name,
              id: item.Id
            };
            return o;
          });
          $groupSelect2 = $("#targetGroupSelection").select2({
            data: groupsList
          });
          
          $groupSelect2.on("select2:select", function (e) { 
             var targetGroupId = $("#targetGroupSelection option:selected").val();
             $parent = $("#targetRoleSelection").parent();
             $("#targetRoleSelection").empty();
             window.CRM.groups.getRoles(targetGroupId).done(function(rdata){
               rolesList = $.map(rdata.ListOptions, function (item) {
                  var o = {
                    text: item.OptionName,
                    id: item.OptionId
                  };
                  return o;
                });
               $("#targetRoleSelection").select2({
                 data:rolesList
               })
             })
          });
        });
      }
    };

    $(document).ajaxError(function (evt, xhr, settings) {
        try {
            var CRMResponse = JSON.parse(xhr.responseText);
            window.CRM.DisplayErrorMessage(settings.url, CRMResponse);
        } catch(err) {}
    });

    function LimitTextSize(theTextArea, size) {
        if (theTextArea.value.length > size) {
            theTextArea.value = theTextArea.value.substr(0, size);
        }
    }

    function popUp(URL) {
        var day = new Date();
        var id = day.getTime();
        eval("page" + id + " = window.open(URL, '" + id + "', 'toolbar=0,scrollbars=yes,location=0,statusbar=0,menubar=0,resizable=yes,width=600,height=400,left = 100,top = 50');");
    }