<?php
// Set the page title and include HTML header
$sPageTitle = "ChurchCRM - Sunday School Device Kiosk";
require(__DIR__ ."/../../../Include/HeaderNotLoggedIn.php");
?>

<div class="container" id="classMemberContainer">
    
</div>

<script>
  //first, define the function that will render the active members
  window.CRM.renderClassMember = function (classMember) {
      var outerDiv = $("<div>").addClass("col-md-4");
      var innerDiv = $("<div>").addClass("box box-widget widget-user-2");
      var userHeaderDiv = $("<div>",{class :"widget-user-header bg-yellow"});
      var imageDiv = $("<div>", {class:"widget-user-image"})
              .append($("<img>",{
                src: window.CRM.root+"/Images/Person/kid_boy-128.png"
              }));
      userHeaderDiv.append(imageDiv);
      userHeaderDiv.append($("<h3>",{class:"widget-user-username", text:classMember.displayName})).append($("<h3>",{class:"widget-user-desc", text:classMember.classRole}));
      innerDiv.append(userHeaderDiv);
      innerDiv.append($("<div>", { class : "box-footer no-padding"})
              .append($("<ul>", {class:"nav nav-stacked"})
                .append($("<li>",{class: "btn btn-block btn-danger", text : "Trigger Parent Alert"}).prepend($("<i>",{class:"fa fa-exclamation-triangle",'aria-hidden':"true"}) ))
                .append($("<li>",{class: "btn btn-block btn-primary", text : "Checkout Child"}))
                .append($("<li>",{class: "btn btn-block btn-info", text : "View Student Info"}))
              ));
      outerDiv.append(innerDiv);
      $("#classMemberContainer").append(outerDiv);   
      
    };
    
    window.CRM.updateActiveClassMembers = function()
    {
      $.ajax( {
        url: window.CRM.root+"/external/"+window.CRM.thisDeviceGuid+"/activeClassMembers"
      }).
        done(function(data){
            
      })
    };

</script>

<?php
// Add the page footer
require(__DIR__ ."/../../../Include/FooterNotLoggedIn.php");
