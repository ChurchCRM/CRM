  /* Philippe Logel 2017 */
  
  time=setInterval(function(){
    window.CRM.APIRequest({
          method: 'GET',
          path: 'events/numbers',
    }).done(function(data) {     
          var EventsNumber = document.getElementById('EventsNumber');
          EventsNumber.innerText=data.events;
          var BirthdateNumber = document.getElementById('BirthdateNumber');
          BirthdateNumber.innerText=data.birthdates;
          var AnniversaryNumber = document.getElementById('AnniversaryNumber');
          AnniversaryNumber.innerText=data.anniversaries;
    });

  },window.CRM.eventsInMenuBarIntervalTime*1000);
  

