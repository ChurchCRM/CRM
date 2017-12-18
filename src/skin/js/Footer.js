i18nextOpt = {
  lng:window.CRM.shortLocale,
  nsSeparator: false,
  keySeparator: false,
  pluralSeparator:false,
  contextSeparator:false,
  fallbackLng: false,
  resources: { }
};

i18nextOpt.resources[window.CRM.shortLocale] = {
  translation: window.CRM.i18keys
};
i18next.init(i18nextOpt);

$("document").ready(function(){
    $(".multiSearch").select2({
        language: window.CRM.shortLocale,
        minimumInputLength: 2,
        ajax: {
            url: function (params){
              return window.CRM.root + "/api/search/" + params.term;
            },
            dataType: 'json',
            delay: 250,
            data: "",
            processResults: function (data, params) {
              return {results: data};
            },
            cache: true
        }
    });
    $(".multiSearch").on("select2:select",function (e) { window.location.href= e.params.data.uri;});

    window.CRM.system.runTimerJobs();
       
    $(".date-picker").datepicker({format:window.CRM.datePickerformat, language: window.CRM.lang});

    $(".maxUploadSize").text(window.CRM.maxUploadSize);
  
    $(document).on("click", ".emptyCart", function (e) {
      window.CRM.cart.empty(function(data){
        window.CRM.cart.refresh();
        
        if (window.CRM.dataTableList) {
            window.CRM.dataTableList.ajax.reload();
        } else if (data.cartPeople) {// this part should be written like this        
          console.log(data.cartPeople);
          $(data.cartPeople).each(function(index,data){
            personButton = $("a[data-cartpersonid='" + data + "']");
            $(personButton).addClass("AddToPeopleCart");
            $(personButton).removeClass("RemoveFromPeopleCart");
            $('span i:nth-child(2)',personButton).removeClass("fa-remove");
            $('span i:nth-child(2)',personButton).addClass("fa-cart-plus");
          });
        }
      });
    });
    
    function BootboxContentCartTogroup(){    
      var frm_str = '<form id="some-form">'
        +'<table border=0 cellpadding=2 width="100%">'
        +'<tr>'
        +'<td>'+i18next.t('Select the method to add to a group')+'   </td>'
        +'<td><select id="GroupSelector" class="form-control">'
        +'<option>'+i18next.t('Select an existing Group')+'</option>'
        +'<option>'+i18next.t('or Create a new Group from the Cart')+'</option>'
        +'</select>'
        +'</td>'
        +'</tr>'
        +'</table>'
        +'<hr/>'
        +'<div id="GroupSelect">'
        +'    <p align="center">'+i18next.t('Select the group to which you would like to add your cart')+':</p>'
        +'      <table align="center">'
        +'        <tr>'
        +'          <td class="LabelColumn">'+i18next.t('Select Group')+':</td>'
        +'          <td class="TextColumn">'
        +'            <select id="GroupID" name="GroupID" style="width:100%" class="form-control">'
        +'            </select>'
        +'          </td>'
        +'        </tr>'
        +'        <tr><td colspan="2">&nbsp;</td></tr>'
        +'        <tr>'
        +'          <td class="LabelColumn">'+i18next.t('Select Role')+':</td>'
        +'          <td class="TextColumn">'
        +'            <select name="GroupRole" id="GroupRole" style="width:100%" class="form-control">'
        +'                <option>'+i18next.t('None')+'</option>'
        +'            </select>'
        +'          </td>'
        +'        </tr>'
        +'      </table>'
        +'      <br>'
        +'</div>'
        +'<div id="GroupCreation">'
        +'      <p align="center">'
        +'        <table border=0 cellpadding=2 width="100%">'
        +'        <tr>'
        +'           <td>'+ i18next.t('Group Name') + ':</td>'
        +'           <td><input type="text" id="GroupName" value="" size="30" maxlength="100" class="form-control"  width="100%" style="width: 100%" placeholder="'+i18next.t("Default Name Group")+'" required></td>'
        +'        </tr>'        
        +'        </table>'
        +'      </p>'
        +'</div>';

        var object = $('<div/>').html(frm_str).contents();

        return object
    }
    
    function addGroups()
    {
        window.CRM.APIRequest({
            path:"groups/",
            method:"GET"
        }).done(function(data) {
            var Groups = data.Groups;                 
            var elt = document.getElementById("GroupID");          
            var len = Groups.length;

            // We add the none option
            var option = document.createElement("option");
            option.text = i18next.t("None");
            option.value = 0;
            option.title = ""; 
            elt.appendChild(option);
      
            for (i=0; i<len; ++i) {
              var option = document.createElement("option");
              // there is a groups.type in function of the new plan of schema
              option.text = Groups[i].Name;
              option.title = Groups[i].RoleListId;        
              option.value = Groups[i].Id;
              elt.appendChild(option);
        }       
      
      });  
    }
    
    // I have to do this because EventGroup isn't yet present when you load the page the first time
    $(document).on('change','#GroupID',function () {
     var e = document.getElementById("GroupID");
     
     if (e.selectedIndex > 0) {
         var option = e.options[e.selectedIndex];
         var GroupID = option.value;
   
          window.CRM.APIRequest({
              path:"groups/"+GroupID+"/roles",
              method:"GET"
          }).done(function(data) {
              var ListOptions = data.ListOptions;                 
              $("#GroupRole").empty();        
              var elt = document.getElementById("GroupRole");  
              var len = ListOptions.length;

              // We add the none option
              var option = document.createElement("option");
              option.text = i18next.t("None");
              option.value = 0;
              option.title = ""; 
              elt.appendChild(option);
    
              for (i=0; i<len; ++i) {
                var option = document.createElement("option");
                // there is a groups.type in function of the new plan of schema
                option.text = i18next.t(ListOptions[i].OptionName);
                option.value = ListOptions[i].OptionId;
                elt.appendChild(option);
              }       
          });
      } 
    });
  
    // I have to do this because EventGroup isn't yet present when you load the page the first time
    $(document).on('change','#GroupSelector',function () {
       var e = document.getElementById("GroupSelector");
       if (e.selectedIndex == 0) {
           $("#GroupCreation").hide();
           $("#GroupSelect").show();
       } else {
           $("#GroupSelect").hide();
           $("#GroupCreation").show();
           
       }
    });

    
    $(document).on("click", "#emptyCartToGroup", function (e) {
      var modal = bootbox.dialog({
         message: BootboxContentCartTogroup,
         title: i18next.t("Add Cart to Group"),
         buttons: [
          {
           label: i18next.t("Save"),
           className: "btn btn-primary pull-left",
           callback: function() {
             var e = document.getElementById("GroupSelector");
             if (e.selectedIndex == 0) {
                 var e = document.getElementById("GroupID");
                 
                 if (e.selectedIndex > 0) {
                     var option = e.options[e.selectedIndex];
                     var GroupID = option.value;             

                     var e = document.getElementById("GroupRole");
                     var option = e.options[e.selectedIndex];
                     var RoleID = option.value;
                     
                     window.CRM.APIRequest({
                        method: 'POST',
                        path: 'cart/emptyToGroup',
                        data: JSON.stringify({"groupID":GroupID,"groupRoleID":RoleID})
                        }).done(function(data) {
                          window.CRM.cart.refresh();
                          location.href = window.CRM.root + 'GroupView.php?GroupID='+GroupID;
                      });
                      
                      return true
                } else {
                    var box = bootbox.dialog({title: "<span style='color: red;'>"+i18next.t("Error")+"</span>",message : i18next.t("You have to select one group and a group role if you want")});
                
                    setTimeout(function() {
                        // be careful not to call box.hide() here, which will invoke jQuery's hide method
                        box.modal('hide');
                    }, 3000);
                    
                    return false;
                }                    
              } else {
          
                  var newGroupName = document.getElementById("GroupName").value;
                  
                  if (newGroupName) {
                      window.CRM.APIRequest({
                        method: 'POST',
                        path: 'cart/emptyToNewGroup',               //call the groups api handler located at window.CRM.root
                        data: JSON.stringify({'groupName':newGroupName}),                      // stringify the object we created earlier, and add it to the data payload
                      }).done(function (data) {                               //yippie, we got something good back from the server
                          window.CRM.cart.refresh();
                          location.href = window.CRM.root + 'GroupView.php?GroupID='+data.Id;
                      });
                      
                      return true;
                  } else {
                    var box = bootbox.dialog({title: "<span style='color: red;'>"+i18next.t("Error")+"</span>",message : i18next.t("You have to set a Group Name")});
                
                    setTimeout(function() {
                        // be careful not to call box.hide() here, which will invoke jQuery's hide method
                        box.modal('hide');
                    }, 3000);
                    
                    return false;
                  }
              }
            }
          },
          {
           label: i18next.t("Close"),
           className: "btn btn-default pull-left",
           callback: function() {
              console.log("just do something on close");
           }
          }
         ],
         show: false,
         onEscape: function() {
            modal.modal("hide");
         }
       });
  
       modal.modal("show");
       
       // we hide by default the GroupCreation
       $("#GroupCreation").hide();
       
       // we add the group and roles
       addGroups();
    });
    
    $(document).on("click",".RemoveFromPeopleCart", function(){
      clickedButton = $(this);
      window.CRM.cart.removePerson([clickedButton.data("personid")],function()
      {
        $(clickedButton).addClass("AddToPeopleCart");
        $(clickedButton).removeClass("RemoveFromPeopleCart");
        $('span i:nth-child(2)',clickedButton).removeClass("fa-remove");
        $('span i:nth-child(2)',clickedButton).addClass("fa-cart-plus");
      });
    });
    
    $(document).on("click",".AddToPeopleCart", function(){
      clickedButton = $(this);
      window.CRM.cart.addPerson([clickedButton.data("personid")],function()
      {
        $(clickedButton).addClass("RemoveFromPeopleCart");
        $(clickedButton).removeClass("AddToPeopleCart");
        $('span i:nth-child(2)',clickedButton).addClass("fa-remove ");
        $('span i:nth-child(2)',clickedButton).removeClass("fa-cart-plus ");
      });
    });
    
    window.CRM.cart.refresh();
    window.CRM.dashboard.refresh();
    DashboardRefreshTimer=setInterval(window.CRM.dashboard.refresh, window.CRM.iDasbhoardServiceIntervalTime * 1000);

});

function showGlobalMessage(message, callOutClass) {
    $("#globalMessageText").text(message);
    $("#globalMessageCallOut").addClass("callout-"+callOutClass);
    $("#globalMessage").show("slow");
}

function suspendSession(){
  $.ajax({
        method: 'HEAD',
        url: window.CRM.root + "/api/session/lock",
        statusCode: {
          200: function() {
            window.open(window.CRM.root + "/Login.php");
          },
          404: function() {
            window.CRM.DisplayErrorMessage(url, {message: error});
          },
          500: function() {
            window.CRM.DisplayErrorMessage(url, {message: error});
          }
        }
      });
};