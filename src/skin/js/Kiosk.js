//first, define the function that will render the active members
  $(document).click(function(){
    window.CRM.kiosk.enterFullScreen();
  })
   
  $(document).ready(function() {
    window.CRM.kioskEventLoop = setInterval(window.CRM.kiosk.heartbeat,2000);
  });
    
  $(document).on('click','.widget-user-header', function(event)
  {
    var personId  = $(event.currentTarget).data('personid')
    window.CRM.kiosk.displayPersonInfo(personId);
  });
    
  $(document).on('click','.parentAlertButton', function(event)
  {
    var personId  = $(event.currentTarget).data('personid')
    window.CRM.kiosk.triggerNotification(personId);
  });
    
  $(document).on('click','.checkinButton', function(event)
  {
    var personId  = $(event.currentTarget).data('personid');
    window.CRM.kiosk.checkInPerson(personId);
  });
    
  $(document).on('click','.checkoutButton', function(event)
  {
    var personId  = $(event.currentTarget).data('personid');
    window.CRM.kiosk.checkOutPerson(personId);
  });