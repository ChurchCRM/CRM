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
  
  #event {
    display:none;
  }
  
  #noEvent {
    display:none;

    position: fixed; /* or absolute */
    top: 50%;
    left: 50%;
    /* bring your own prefixes */
    transform: translate(-50%, -50%);

  }
  
</style>

<div>
  <h1 id="noEvent">No active events for this kiosk</h1>
</div>

<div id="event">

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
</div>

<script src="<?= SystemURLs::getRootPath() ?>/skin/randomcolor/randomColor.js"></script>
<script src="<?= SystemURLs::getRootPath() ?>/skin/js/initial.js"></script>
<script src="<?= SystemURLs::getRootPath() ?>/skin/moment/moment.min.js"></script>
<script>
  //first, define the function that will render the active members
  
  window.CRM.displayPersonInfo = function (personId)
  {
    console.log(personId);
  }
  
  window.CRM.APIRequest = function(options) {
      if (!options.method)
      {
        options.method="GET"
      }
      options.url=window.CRM.root+"/kiosk/"+options.path;
      options.dataType = 'json';
      options.contentType =  "application/json";
      return $.ajax(options);
    }
  
  window.CRM.renderClassMember = function (classMember) {
      existingDiv = $("#personId-"+classMember.personId);
      console.log(classMember);
      if (existingDiv.length > 0)
      {

      }
      else
      {
        var outerDiv = $("<div>",{id:"personId-"+classMember.personId}).addClass("col-sm-3");
        var innerDiv = $("<div>").addClass("box box-widget widget-user-2");
        var userHeaderDiv = $("<div>",{class :"widget-user-header bg-yellow"}).attr("data-personid",classMember.personId);
        var imageDiv = $("<div>", {class:"widget-user-image"})
                .append($("<img>",{
                  class:"initials-image profile-user-img img-responsive img-circle no-border"
                }).data("name",classMember.displayName)
                  .data("src",window.CRM.root+"/kiosk/activeClassMember/"+classMember.personId+"/photo")
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
      }
      
      if (classMember.status == 1)
      {
        window.CRM.setCheckedIn(classMember.personId);
      }
      else
      {
        window.CRM.setCheckedOut(classMember.personId);
        
      }

    };
    
  window.CRM.updateActiveClassMembers = function()
  {
     window.CRM.APIRequest({
       path:"activeClassMembers"
     })
     .done(function(data){
          $(data.People).each(function(i,d){
            //console.log(d);
            window.CRM.renderClassMember({displayName:d.FirstName+" "+d.LastName, classRole:d.RoleName,personId:d.Id,status:d.status})
          });
          $(".initials-image").initial();
      })
  };
  
  window.CRM.heartbeat = function(){
    window.CRM.APIRequest({
       path:"heartbeat"
     }).
        done(function(data){
          if (data.Commands == "Reload")
          {
            location.reload();
          }
          
          thisEvent=JSON.parse(data.Event);
          if (thisEvent)
          {
            window.CRM.updateActiveClassMembers();
            $("#noEvent").hide();
            $("#event").show();
            $("#eventTitle").text(thisEvent.Title);
            $("#startTime").text(moment(thisEvent.Start).format('MMMM Do YYYY, h:mm:ss a'));
            $("#endTime").text(moment(thisEvent.End).format('MMMM Do YYYY, h:mm:ss a'));
          }
          else
          {
             $("#noEvent").show();
             $("#event").hide();
          }
          
      })
  }
  
  window.CRM.kioskEventLoop = function()
  {
    window.CRM.heartbeat();
    
  }
  
  window.CRM.checkInPerson = function(personId)
  {
    window.CRM.APIRequest({
      path:"checkin",
      method:"POST",
      data:JSON.stringify({"PersonId":personId})
    }).
    done(function(data){
      console.log("CheckIn for: "+personId);
      window.CRM.setCheckedIn(personId);
    });
    
  }
  
  window.CRM.checkOutPerson = function(personId)
  {
    window.CRM.APIRequest({
      path:"checkout",
      method:"POST",
      data:JSON.stringify({"PersonId":personId})
    }).
    done(function(data){
      console.log("CheckOut for: "+personId);
      window.CRM.setCheckedOut(personId);
    });
  }
  
  window.CRM.setCheckedOut = function (personId)
  {
    console.log("setting checked out" + personId);
    $personDiv = $("#personId-"+personId)
    $personDivButton = $("#personId-"+personId+" .checkoutButton")
    $personDivButton.addClass("checkinButton");
    $personDivButton.removeClass("checkoutButton");
    $personDivButton.text("Checkin");
    $personDiv.find(".widget-user-header").addClass("bg-yellow");
    $personDiv.find(".widget-user-header").removeClass("bg-green");
  }
  
  window.CRM.setCheckedIn = function (personId)
  {
    console.log("setting checked in" + personId);
    $personDiv = $("#personId-"+personId)
    
    $personDivButton = $("#personId-"+personId+" .checkinButton")
    $personDivButton.removeClass("checkinButton");
    $personDivButton.addClass("checkoutButton");
    $personDivButton.text("Checkout");
    
    $personDiv.find(".widget-user-header").removeClass("bg-yellow");
    $personDiv.find(".widget-user-header").addClass("bg-green");
    
  }
  
  window.CRM.triggerNotification = function(personId)
  {
     window.CRM.APIRequest({
      path:"triggerNotification",
      method:"POST",
      data:JSON.stringify({"PersonId":personId})
    }).
    done(function(data){
       console.log("Parent Alert for: "+personId);
    });
   
  }
  
  window.CRM.enterFullScreen = function() {
    if(document.documentElement.requestFullscreen) {
      document.documentElement.requestFullscreen();
    } else if(document.documentElement.mozRequestFullScreen) {
      document.documentElement.mozRequestFullScreen();
    } else if(document.documentElement.webkitRequestFullscreen) {
      document.documentElement.webkitRequestFullscreen();
    } else if(document.documentElement.msRequestFullscreen) {
      document.documentElement.msRequestFullscreen();
    }
  }
  window.CRM.exitFullScreen = function() {
    if(document.exitFullscreen) {
     document.exitFullscreen();
   } else if(document.mozCancelFullScreen) {
     document.mozCancelFullScreen();
   } else if(document.webkitExitFullscreen) {
     document.webkitExitFullscreen();
   }
  }
  
  $(document).click(function(){
    window.CRM.enterFullScreen();
  })
  
  
  
  $(document).ready(function() {
    window.CRM.kioskEventLoop();
    setInterval(window.CRM.kioskEventLoop,2000);
  });
    
  $(document).on('click','.widget-user-header', function(event)
  {
    var personId  = $(event.currentTarget).data('personid')
    window.CRM.displayPersonInfo(personId);
  });
    
  $(document).on('click','.parentAlertButton', function(event)
  {
    var personId  = $(event.currentTarget).data('personid')
    window.CRM.triggerNotification(personId);
  });
    
  $(document).on('click','.checkinButton', function(event)
  {
    var personId  = $(event.currentTarget).data('personid');
    window.CRM.checkInPerson(personId);
  });
    
  $(document).on('click','.checkoutButton', function(event)
  {
    var personId  = $(event.currentTarget).data('personid');
    window.CRM.checkOutPerson(personId);
  });
    
    

</script>

<?php
// Add the page footer
require(SystemURLs::getDocumentRoot(). "/Include/FooterNotLoggedIn.php");