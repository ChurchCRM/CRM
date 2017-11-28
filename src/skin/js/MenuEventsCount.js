  /* Philippe Logel 2017 */
  
  time=setInterval(function(){
      //your code
      window.CRM.APIRequest({
          method: 'GET',
          path: 'persons/numbers',
    }).done(function(data) {     
      var BirthdateNumber = document.getElementById('BirthdateNumber');
      BirthdateNumber.innerText=data;
    });  
  
    window.CRM.APIRequest({
          method: 'GET',
          path: 'families/numbers',
    }).done(function(data) {     
      var AnniversaryNumber = document.getElementById('AnniversaryNumber');
      AnniversaryNumber.innerText=data;
    });
  
  
    window.CRM.APIRequest({
          method: 'GET',
          path: 'events/numbers',
    }).done(function(data) {     
      var EventsNumber = document.getElementById('EventsNumber');
      EventsNumber.innerText=data;
    });

  },window.CRM.eventsInMenuBarIntervalTime*1000);
  

