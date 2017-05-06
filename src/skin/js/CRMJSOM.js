/*
 * ChurcmCRM JavaScript Object Model Initailizaion Script
 */

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
          $.ajax({
          type: 'POST',
          url: window.CRM.root + '/api/cart/emptyToGroup',
          dataType: 'json',
          contentType: "application/json",
          data: JSON.stringify({"groupID":groupID,"groupRoleID":groupRoleID})
          }).done(function(data) {
              window.CRM.cart.refresh();
              if(callback)
              {
                callback(data);
              }

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
      'promptSelection': function()
      {
        var dialog = bootbox.dialog({
           title: 'Select Group and Role',
           message: '<div class="modal-body">\
                <input type="hidden" id="targetGroupAction">\
                <span style="color: red">Please select target group for members:</span>\
                <select name="targetGroupSelection" id="targetGroupSelection" style="width: 50%">\
                </select>\
              </div>',
           buttons: {
             confirm: {
                 label: 'OK',
                 className: 'btn-success',
                 callback: function(){
                   console.log({
                     'GroupID': $("#targetGroupSelection option:selected").val(),
                     'RoleID' : 0
                   });
                }
             },
             cancel: {
                 label: 'Cancel',
                 className: 'btn-danger'
             }
           }
        }).show();
          
        $("#targetGroupSelection").select2({
           ajax: {
             url: window.CRM.root + "/api/groups/",
             dataType: 'json',
             processResults: function (rdata, page) {
               var p = $.map(rdata.Groups, function (item) {
                 var o = {
                   text: item.Name,
                   id: item.Id
                 };
                 return o;
               });
               return {results: p};
             }
           },
           minimumResultsForSearch: Infinity
         });
        /*
        <div class="modal fade" id="selectTargetGroupModal" tabindex="-1" role="dialog" aria-labelledby="deleteGroup" aria-hidden="true">
          <div class="modal-dialog">
            <div class="modal-content">
              <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title" id="upload-Image-label"><?= gettext('Select Target Group') ?></h4>
              </div>
              <div class="modal-body">
                <input type="hidden" id="targetGroupAction">
                <span style="color: red"><?= gettext('Please select target group for members') ?>:</span>
                <select name="targetGroupSelection" id="targetGroupSelection" style="width: 50%">
                </select>
              </div>
              <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal"><?= gettext('Close') ?></button>
                <button name="confirmTargetGroup" id="confirmTargetGroup" type="button" class="btn btn-danger"><?= gettext('Confirm Target Group') ?></button>
              </div>
            </div>
          </div>
        </div>
        <!--TARGET GROP SELECT MODAL-->*/
      }
    }

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