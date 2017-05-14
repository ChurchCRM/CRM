<?php
use ChurchCRM\dto\SystemURLs;
// Set the page title and include HTML header
$sPageTitle = "ChurchCRM - Sunday School Device Kiosk";
require(SystemURLs::getDocumentRoot(). "/Include/HeaderNotLoggedIn.php");
?>
<style>
  .widget-user-2 .widget-user-header {
    padding: 5px;
  }
  
  #eventDetails {
    display:block;
    width: 100%;
    background-color:rgb(60,141,188);
    min-height:50px;
  }
  
  #eventDetails  span {
    font-size: 15px;
    text-align: center;
    color: white;
    display:block;
  }
  
  #eventTitle {
    font-size: 30px !important;
    font-weight: bold;
  }
  
  #newStudent {
    position: fixed;
    left: 20px;
    bottom: 80px;
    width:30px;
    height:30px;
    z-index: 10000;
    font-size:48pt;
    color: green;
  }
  
</style>

<div class="container" id="eventDetails">
  <div class="col-md-6">
    <span id="eventTitle" ></span>
  </div>
  <div class="col-md-2">
    <span>Start Time</span>
    <span id="startTime"></span>  
  </div>
  <div class="col-md-2">
    <span>End Time</span>
    <span id="endTime"></span> 
  </div>
  
    
</div>

<div class="container" id="classMemberContainer">
    
</div>

<a id="newStudent"><i class="fa fa-plus-circle" aria-hidden="true"></i></a>

<script src="<?= SystemURLs::getRootPath() ?>/skin/randomcolor/randomColor.js"></script>
<script src="<?= SystemURLs::getRootPath() ?>/skin/js/initial.js"></script>
<script src="<?= SystemURLs::getRootPath() ?>/skin/moment/moment.min.js"></script>
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
                class:"initials-image profile-user-img img-responsive img-circle no-border"
              }).data("name",classMember.displayName)
                .data("src",window.CRM.root+"/external/kioskdevices/"+window.CRM.thisDeviceGuid+"/activeClassMember/"+classMember.personId+"/photo")
              );
      userHeaderDiv.append(imageDiv);
      userHeaderDiv.append($("<h3>",{class:"widget-user-username", text:classMember.displayName})).append($("<h3>",{class:"widget-user-desc", style:"clear:both", text:classMember.classRole}));
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
          $(data.People).each(function(i,d){
            //console.log(d);
            window.CRM.renderClassMember({displayName:d.FirstName+" "+d.LastName, classRole:d.RoleName,personId:d.Id})
          });
          $(".initials-image").initial();
      })
  };
  
  window.CRM.uppdateActiveEvent = function(){
    $.ajax( {
        method: "GET",
        url: window.CRM.root+"/external/kioskdevices/"+window.CRM.thisDeviceGuid+"/activeEvent",
        dataType: "json"
      }).
        done(function(data){
          thisEvent=data.Events[0];
          $("#eventTitle").text(thisEvent.Title);
          $("#startTime").text(moment(thisEvent.Start).format('MMMM Do YYYY, h:mm:ss a'));

          $("#endTime").text(moment(thisEvent.End).format('MMMM Do YYYY, h:mm:ss a'));
      })
  }
  
  $(document).ready(function() {
    window.CRM.uppdateActiveEvent();
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
require(SystemURLs::getDocumentRoot(). "/Include/FooterNotLoggedIn.php");