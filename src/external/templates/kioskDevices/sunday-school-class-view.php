<?php
// Set the page title and include HTML header
$sPageTitle = "ChurchCRM - Sunday School Device Kiosk";
require(__DIR__ ."/../../../Include/HeaderNotLoggedIn.php");
?>
<style>
  .widget-user-2 .widget-user-header {
    padding: 5px;
  }
  
</style>

<div class="container" id="classMemberContainer">
    
</div>

<script>
  window.CRM.thisDeviceGuid = "<?= $thisDeviceGuid ?>";
  //first, define the function that will render the active members
  
  window.CRM.displayPersonInfo = function (personId)
  {
    console.log(personId);
  }
  
  window.CRM.renderClassMember = function (classMember) {
      var outerDiv = $("<div>",{id:"personId-"+classMember.personId}).addClass("col-md-4");
      var innerDiv = $("<div>").addClass("box box-widget widget-user-2");
      var userHeaderDiv = $("<div>",{class :"widget-user-header bg-yellow"}).attr("data-personid",classMember.personId);
      var imageDiv = $("<div>", {class:"widget-user-image"})
              .append($("<img>",{
                src: window.CRM.root+"/external/kioskdevices/"+window.CRM.thisDeviceGuid+"/activeClassMember/"+classMember.personId+"/photo"
              }));
      userHeaderDiv.append(imageDiv);
      userHeaderDiv.append($("<h3>",{class:"widget-user-username", text:classMember.displayName})).append($("<h3>",{class:"widget-user-desc", text:classMember.classRole}));
      innerDiv.append(userHeaderDiv);
      innerDiv.append($("<div>", { class : "box-footer no-padding"})
              .append($("<ul>", {class:"nav navbar-nav", style:"width:100%"})
                .append($("<li>", {style:"width:50%"})
                  .append($("<button>",{class: "btn btn-danger parentAlertButton", style:"width:100%", text : "Trigger Parent Alert", "data-personid": classMember.personId}).prepend($("<i>",{class:"fa fa-exclamation-triangle",'aria-hidden':"true"}) )))
                .append($("<li>",{class: "btn btn-primary checkinButton", style:"width:50%", text : "Checkin", "data-personid": classMember.personId}))
              ));
      outerDiv.append(innerDiv);
      $("#classMemberContainer").append(outerDiv);   
      
    };
    
    window.CRM.updateActiveClassMembers = function()
    {
      $.ajax( {
        method: "GET",
        url: window.CRM.root+"/external/kioskdevices/"+window.CRM.thisDeviceGuid+"/activeClassMembers",
        dataType: "json"
      }).
        done(function(data){
          $(data.Person2group2roleP2g2rs).each(function(i,d){
            //console.log(d);
            window.CRM.renderClassMember({displayName:d.Person.FirstName+" "+d.Person.LastName, classRole:d.RoleId,personId:d.PersonId})
          });
      })
    };
    $(document).ready(function() {
      window.CRM.updateActiveClassMembers();
    });
    
    $(document).on('click','.widget-user-header', function(event)
    {
      var personId  = $(event.currentTarget).data('personid')
      window.CRM.displayPersonInfo(personId);
    });
    
    $(document).on('click','.parentAlertButton', function(event)
    {
      var personId  = $(event.currentTarget).data('personid')
      console.log("Parent Alert for: "+personId);
    });
    
    $(document).on('click','.checkinButton', function(event)
    {
      var personId  = $(event.currentTarget).data('personid');
      $(event.currentTarget).removeClass("checkinButton");
      $(event.currentTarget).addClass("checkoutButton");
      $(event.currentTarget).text("Checkout");
      $("#personId-"+personId).find(".widget-user-header").removeClass("bg-yellow");
      $("#personId-"+personId).find(".widget-user-header").addClass("bg-green");
      
      console.log("Checkin for: "+personId);
    });
    
     $(document).on('click','.checkoutButton', function(event)
    {
      var personId  = $(event.currentTarget).data('personid');
      $(event.currentTarget).removeClass("checkoutButton");
      $(event.currentTarget).addClass("checkinButton");
      $(event.currentTarget).text("CheckIn");
      $("#personId-"+personId).find(".widget-user-header").removeClass("bg-green");
      $("#personId-"+personId).find(".widget-user-header").addClass("bg-yellow");
      console.log("Checkout for: "+personId);
    });

</script>

<?php
// Add the page footer
require(__DIR__ ."/../../../Include/FooterNotLoggedIn.php");
