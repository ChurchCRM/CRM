/*
 * ChurcmCRM JavaScript Object Model Initailizaion Script
 */

    window.CRM.APIRequest = function(options) {
      if (!options.method)
      {
        options.method="GET"
      }
      options.url=window.CRM.root+"/api/"+options.path;
      options.dataType = 'json';
      options.contentType =  "application/json";
      return $.ajax(options);
    }

    window.CRM.DisplayErrorMessage = function(endpoint, error) {

      message = "<p>" + i18next.t("Error making API Call to") + ": " + endpoint +
        "</p><p>" + i18next.t("Error text") + ": " + error.message;
      if (error.trace)
      {
        message += "</p>" + i18next.t("Stack Trace") + ": <pre>"+JSON.stringify(error.trace, undefined, 2)+"</pre>";
      }
      bootbox.alert({
        title:  i18next.t("ERROR"),
        message: message
      });
    };

    window.CRM.VerifyThenLoadAPIContent = function(url) {
      var error = i18next.t("There was a problem retrieving the requested object");
      $.ajax({
        method: 'HEAD',
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
      'empty' : function (callback)
      {
        window.CRM.APIRequest({
          method: "DELETE",
          path: "cart/"
        }).done(function (data) {
          if (callback)
          {
            callback()
          }
          else
          {
            window.CRM.cart.refresh();
          }
        });
      },
      'emptyToGroup' : function (callback)
      {
        window.CRM.groups.promptSelection({Type:window.CRM.groups.selectTypes.Group|window.CRM.groups.selectTypes.Role},function(selectedRole){
          window.CRM.APIRequest({
            method: 'POST',
            path: 'cart/emptyToGroup',
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
        window.CRM.APIRequest({
          method: 'POST',
          path: 'cart/',
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
         window.CRM.APIRequest({
          method: 'POST',
          path:'cart/',
          data: JSON.stringify({"_METHOD":"DELETE","Persons":Persons})
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
         window.CRM.APIRequest({
          method: 'POST',
          path:'cart/',
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
         window.CRM.APIRequest({
          method: 'POST',
          path: 'cart/',
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
        window.CRM.APIRequest({
          method: 'GET',
          path:"cart/"
        }).done(function(data) {
          window.scrollTo(0, 0);
          $("#iconCount").text(data.PeopleCart.length);
          var cartDropdownMenu;
          if (data.PeopleCart.length > 0) {
            cartDropdownMenu = '\
              <li id="showWhenCartNotEmpty">\
                  <ul class="menu">\
                      <li>\
                          <a href="CartView.php">\
                              <i class="fa fa-shopping-cart text-green"></i>' + i18next.t("View Cart") + '\
                          </a>\
                      </li>\
                      <li>\
                          <a class="emptyCart" >\
                              <i class="fa fa-trash text-danger"></i>' + i18next.t("Empty Cart") + ' \
                          </a>\
                      </li>\
                      <li>\
                          <a id="emptyCartToGroup">\
                              <i class="fa fa-object-ungroup text-info"></i>' + i18next.t("Empty Cart to Group") + '\
                          </a>\
                      </li>\
                      <li>\
                          <a href="CartToEvent.php">\
                              <i class="fa fa fa-users text-info"></i>' + i18next.t("Empty Cart to Family") + '\
                          </a>\
                      </li>\
                      <li>\
                          <a href="CartToEvent.php">\
                              <i class="fa fa fa-ticket text-info"></i>' + i18next.t("Empty Cart to Event") + '\
                          </a>\
                      </li>\
                      <li>\
                          <a href="MapUsingGoogle.php?GroupID=0">\
                              <i class="fa fa-map-marker text-info"></i>' + i18next.t("Map Cart") + '\
                          </a>\
                      </li>\
                  </ul>\
              </li>\
                        <!--li class="footer"><a href="#">' + i18next.t("View all") + '</a></li-->\
                    '
        }
          else {
            cartDropdownMenu = '\
              <li class="header">' + i18next.t("Your Cart is Empty" ) + '</li>';
          }
        $("#cart-dropdown-menu").html(cartDropdownMenu);
        $("#CartBlock")
          .animate({'left':(-10)+'px'},30)
          .animate({'left':(+10)+'px'},30)
          .animate({'left':(0)+'px'},30);
        });
      }

    };
    
    window.CRM.kiosks = {
        assignmentTypes: {
            "1":"Event Attendance",
            "2":"Self Registration",
            "3":"Self Checkin",
            "4":"General Attendance"
        },
        reload: function(id)
        {
          window.CRM.APIRequest({
            "path":"kiosks/"+id+"/reloadKiosk",
            "method":"POST"
          }).done(function(data){
            //todo: tell the user the kiosk was reloaded..?  maybe nothing...
          })
        },
        enableRegistration: function() {
          return window.CRM.APIRequest({
            "path":"kiosks/allowRegistration",
            "method":"POST"
          })  
        },
        accept: function (id)
        {
           window.CRM.APIRequest({
            "path":"kiosks/"+id+"/acceptKiosk",
            "method":"POST"
          }).done(function(data){
            window.CRM.kioskDataTable.ajax.reload()
          })
        },
        identify: function (id)
        {
           window.CRM.APIRequest({
            "path":"kiosks/"+id+"/identifyKiosk",
            "method":"POST"
          }).done(function(data){
              //do nothing...
          })
        },
        setAssignment: function (id,assignmentId)
        {
          assignmentSplit = assignmentId.split("-");
          if(assignmentSplit.length > 0)
          {
            assignmentType = assignmentSplit[0];
            eventId = assignmentSplit[1];
          }
          else
          {
            assignmentType = assignmentId;
          }

           window.CRM.APIRequest({
            "path":"kiosks/"+id+"/setAssignment",
            "method":"POST",
            "data":JSON.stringify({"assignmentType":assignmentType,"eventId":eventId})
          }).done(function(data){
          })
        }
    }
    
    window.CRM.events = {
       getFutureEventes: function()
        {
          //this could probably be done better, as this option may present a race condition by
          //populating a window variable with future events that future elements may rely on
          window.CRM.APIRequest({
            "path":"events/notDone"
          }).done(function(data){
            window.CRM.events.futureEvents=data.Events;
          });
        }
    };
    
    window.CRM.groups = {
      
      'get': function() {
        return  window.CRM.APIRequest({
          path:"groups/",
          method:"GET"
        }); 
      },
      'getRoles': function(GroupID) {
        return window.CRM.APIRequest({
          path:"groups/"+GroupID+"/roles",
          method:"GET"
        }); 
      },
      'selectTypes': {
        'Group': 1,
        'Role': 2,
      },
      'promptSelection': function(selectOptions,selectionCallback) {
          var options ={
            message: '<div class="modal-body">\
                  <input type="hidden" id="targetGroupAction">',
             buttons: {
               confirm: {
                   label: i18next.t('OK'),
                   className: 'btn-success'
               },
               cancel: {
                   label: i18next.t('Cancel'),
                   className: 'btn-danger'
               }
             }
          };
          initFunction = function() {};
          
          if (selectOptions.Type & window.CRM.groups.selectTypes.Group)
          {
            options.title = i18next.t("Select Group");
            options.message +='<span style="color: red">'+i18next.t('Please select target group for members')+':</span>\
                  <select name="targetGroupSelection" id="targetGroupSelection" class="form-control"></select>';
            options.buttons.confirm.callback = function(){
               selectionCallback({"GroupID": $("#targetGroupSelection option:selected").val()});
            };
          }
          if (selectOptions.Type & window.CRM.groups.selectTypes.Role )
          {
            options.title = "Select Role"
            options.message += '<span style="color: red">'+i18next.t('Please select target Role for members')+':</span>\
                  <select name="targetRoleSelection" id="targetRoleSelection" class="form-control"></select>';
            options.buttons.confirm.callback = function(){
              selectionCallback({"RoleID": $("#targetRoleSelection option:selected").val()});
            };
          }
          
          if (selectOptions.Type === window.CRM.groups.selectTypes.Role)
          {
            if (!selectOptions.GroupID)
            {
              throw i18next.t("GroupID required for role selection prompt");
            }
            initFunction = function() {
              window.CRM.groups.getRoles(selectOptions.GroupID).done(function(rdata){
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
            }
          }
          if (selectOptions.Type === (window.CRM.groups.selectTypes.Group | window.CRM.groups.selectTypes.Role) )
          {
            options.title = i18next.t("Select Group and Role");
            options.buttons.confirm.callback = function(){
              selection = {
                "RoleID": $("#targetRoleSelection option:selected").val(),
                "GroupID": $("#targetGroupSelection option:selected").val()
              };
              console.log(selection);
              selectionCallback(selection);
            }
          }
          options.message +='</div>';
          bootbox.dialog(options).init(initFunction).show();

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
      },
     'addPerson' : function(GroupID,PersonID,RoleID) {
        params = {
          method: 'POST', // define the type of HTTP verb we want to use (POST for our form)
          path:'groups/' + GroupID + '/addperson/'+PersonID
        };
        if (RoleID)
        {
          params.data = JSON.stringify({
            RoleID: RoleID
          });
        }
        return window.CRM.APIRequest(params);
      },
      'removePerson' : function(GroupID,PersonID) {
        return window.CRM.APIRequest({
          method: 'DELETE', // define the type of HTTP verb we want to use (POST for our form)
          path:'groups/' + GroupID + '/removeperson/' + PersonID,
        });
      }
    };
    
    window.CRM.system = {
      'runTimerJobs' : function () {
        $.ajax({
          url: window.CRM.root + "/api/timerjobs/run",
          type: "POST"
        });
      }
    }

    $(document).ajaxError(function (evt, xhr, settings,errortext) {
      if(errortext !== "abort") {
        try {
            var CRMResponse = JSON.parse(xhr.responseText);
            window.CRM.DisplayErrorMessage(settings.url, CRMResponse);
        } catch(err) {
          window.CRM.DisplayErrorMessage(settings.url,{"message":errortext});
        }
      }
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
