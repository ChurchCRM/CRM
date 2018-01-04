  // Philippe Logel
  var anniversary = true;
  var birthday    = true;
  var withlimit   = false;
  
 
  var birthD = localStorage.getItem("birthday");
  if (birthD != null)
  {
    if (birthD == 'checked'){ 
      birthday=true;
    } else {
      birthday=false;
    }
      
    $('#isBirthdateActive').prop('checked', birthday);
  }

  var ann = localStorage.getItem("anniversary");
  if (ann != null)
  {
    if (ann == 'checked'){
      anniversary=true;
    } else {
      anniversary=false;
    }
    
    $('#isAnniversaryActive').prop('checked', anniversary);
  }
  
  var wLimit = localStorage.getItem("withlimit");
  if (wLimit != null)
  {
    if (wLimit == 'checked'){
      withlimit=true;
    } else {
      withlimit=false;
    }
    
    $('#isWithLimit').prop('checked', withlimit);
  }  
  
  
  $("#isBirthdateActive").on('change',function () {
     var _val = $(this).is(':checked') ? 'checked' : 'unchecked';
     
     if (_val == 'checked'){
       birthday = true;
     } else { 
      birthday = false;
     }
     $('#calendar').fullCalendar( 'refetchEvents' );
     
     localStorage.setItem("birthday",_val);     
  });
  
  $("#isAnniversaryActive").on('change',function () {
     var _val = $(this).is(':checked') ? 'checked' : 'unchecked';
     if (_val == 'checked'){
      anniversary = true;
     } else { 
      anniversary = false;
     }

     $('#calendar').fullCalendar( 'refetchEvents' );
     
     localStorage.setItem("anniversary",_val); 
  });
  
  $("#isWithLimit").on('change',function () {
     var _val = $(this).is(':checked') ? 'checked' : 'unchecked';
     if (_val == 'checked'){
        withlimit = true;
     } else { 
        withlimit = false;
     }
   
     var options = $('#calendar').fullCalendar('getView').options;
     options.eventLimit = withlimit;
     $('#calendar').fullCalendar('destroy');
     $('#calendar').fullCalendar(options);
     
     localStorage.setItem("withlimit",_val); 
  });
  
  window.groupFilterID     = 0;
  window.EventTypeFilterID = 0;
  
  localStorage.setItem("groupFilterID",groupFilterID);
  localStorage.setItem("EventTypeFilterID",EventTypeFilterID);  
  
  $("#EventGroupFilter").on('change',function () {
     var e = document.getElementById("EventGroupFilter");
     window.groupFilterID = e.options[e.selectedIndex].value;
   
    $('#calendar').fullCalendar( 'refetchEvents' );
    
    if (window.groupFilterID == 0)
      $("#ATTENDENCES").parents("tr").hide();
     
     localStorage.setItem("groupFilterID",groupFilterID); 
  });
  
  
  $("#EventTypeFilter").on('change',function () {
     var e = document.getElementById("EventTypeFilter");
     window.EventTypeFilterID = e.options[e.selectedIndex].value;
      
     $('#calendar').fullCalendar( 'refetchEvents' );
     
     localStorage.setItem("EventTypeFilterID",EventTypeFilterID); 
  });
  
  // I have to do this because EventGroup isn't yet present when you load the page the first time
  $(document).on('change','#EventGroup',function () {
     var e = document.getElementById("EventGroup");
     var _val = e.options[e.selectedIndex].value;
   
    if (_val == 0)
      $("#ATTENDENCES").parents("tr").hide();
    else
      $("#ATTENDENCES").parents("tr").show();
     
     localStorage.setItem("groupFilterID",groupFilterID); 
  });
  
  function addEventTypes()
  {
    window.CRM.APIRequest({
          method: 'GET',
          path: 'events/calendars',
    }).done(function(eventTypes) {    
      var elt = document.getElementById("eventType");          
      var len = eventTypes.length;
      
      for (i=0; i<len; ++i) {
        var option = document.createElement("option");
        option.text = eventTypes[i].name;
        option.value = eventTypes[i].eventTypeID;
        elt.appendChild(option);
      }       
      
    });  
  }
  
  function addCalendars()
  {
    window.CRM.APIRequest({
          method: 'GET',
          path: 'groups/calendars',
    }).done(function(groups) {    
      var elt = document.getElementById("EventGroup");          
      var len = groups.length;

      // We add the none option
      var option = document.createElement("option");
      option.text = i18next.t("None");
      option.value = 0;
      option.title = ""; 
      elt.appendChild(option);
      
      for (i=0; i<len; ++i) {
        var option = document.createElement("option");
        // there is a groups.type in function of the new plan of schema
        option.text = groups[i].name;
        option.title = groups[i].type;        
        option.value = groups[i].groupID;
        elt.appendChild(option);
      }       
      
    });  
  }
  
  function BootboxContent(){    
    var frm_str = '<form id="some-form">'
       + '<table class="table">'
            +'<tr>'
              +"<td class='LabelColumn'><span style='color: red'>*</span>" + i18next.t('Select your event type') + "</td>"
              +'<td colspan="3" class="TextColumn">'
              +'<select type="text" id="eventType" value="39"  width="100%" style="width: 100%">'
                   //+"<option value='0' >" + i18next.t("Personal") + "</option>"
                +'</select>'
              +'</td>'
            +'</tr>'
            +'<tr>'
              +"<td class='LabelColumn'><span style='color: red'>*</span>" + i18next.t('Event Title') + ":</td>"
              +'<td colspan="1" class="TextColumn">'
                +"<input type='text' id='EventTitle' value='" + i18next.t("Calendar Title") + "' size='30' maxlength='100' class='form-control'  width='100%' style='width: 100%' required>"
              +'</td>'
            +'</tr>'
            +'<tr>'
              +"<td class='LabelColumn'><span style='color: red'>*</span>" + i18next.t('Event Desc') + ":</td>"
              +'<td colspan="3" class="TextColumn">'
                +"<textarea id='EventDesc' rows='4' maxlength='100' class='form-control'  width='100%' style='width: 100%' required >" + i18next.t("Calendar description") + "</textarea>"
              +'</td>'
            +'</tr>'          
            +'<tr>'
              +"<td class='LabelColumn'><span style='color: red'>*</span>" + i18next.t('Event Group') + ":</td>"
              +'<td class="TextColumn">'
                +'<select type="text" id="EventGroup" value="39" width="100%" style="width: 100%">'
                +'</select>'
              +'</td>'
            +'</tr>'
            +'<tr>'
              +"<td class='LabelColumn'><span style='color: red'>*</span>" + i18next.t('Event Publicly Visible') + ":</td>"
              +'<td class="TextColumn">'
                +'<input type="checkbox" id="EventPubliclyVisible" />'
              +'</td>'
            +'</tr>'
            +'<tr>'
              +"<td class='LabelColumn' id='ATTENDENCES'>" + i18next.t('Attendance Counts') + "</td>"
              +'<td class="TextColumn" colspan="3">'
                +'<table>'
                +'<tr>'
                    +"<td><strong>" + i18next.t("Total") + ":&nbsp;</strong></td>"
                  +'<td>'
                  +'<input type="text" id="Total" value="0" size="8" class="form-control"  width="100%" style="width: 100%">'
                  +'</td>'
                  +'</tr>'
                +'<tr>'
                    +"<td><strong>" + i18next.t("Members") + ":&nbsp;</strong></td>"
                  +'<td>'
                  +'<input type="text" id="Members" value="0" size="8" class="form-control"  width="100%" style="width: 100%">'
                 +' </td>'
                  +'</tr>'
               +' <tr>'
                    +"<td><strong>" + i18next.t("Visitors") + ":&nbsp;</strong></td>"
                  +'<td>'
                  +'<input type="text" id="Visitors" value="0" size="8" class="form-control"  width="100%" style="width: 100%">'
                  +'</td>'
                  +'</tr>'
                      +'<tr>'
                +"<td><strong>" + i18next.t('Attendance Notes: ') + " &nbsp;</strong></td>"
                  +'<td><input type="text" id="EventCountNotes" value="" class="form-control">'
                  +'</td>'
                  +'</tr>'
                  +'</table>'
                      +'</td>'
            +'</tr>'
            +'<tr>'
              +'<td colspan="4" class="TextColumn">'+i18next.t('Event Sermon')+'<textarea name="EventText" rows="5" cols="80" class="form-control" id="eventPredication"  width="100%" style="width: 100%"></textarea></td>'
            +'</tr>'
            //+'<tr>'
              //+'<td class="LabelColumn"><span style="color: red">*</span>Statut de l&#39;événement:</td>'
              //+'<td colspan="3" class="TextColumn">'
                //+'<input type="radio" name="EventStatus" value="0" checked/> Actif      <input type="radio" name="EventStatus" value="1" /> Inactif    </td>'
            //+'</tr>'
          +'</table>'
       + '</form>';

        var object = $('<div/>').html(frm_str).contents();

        return object
    }

  
    $(document).ready(function () {
        //
        // initialize the calendar
        // -----------------------------------------------------------------
        $('#calendar').fullCalendar({
          header: {
              left: 'prev,next today',
              center: 'title',
              right: 'month,agendaWeek,agendaDay,listMonth'
          },
          height: 500,
          selectable: isModifiable,
          editable:isModifiable,
          eventDrop: function(event, delta, revertFunc) {
            if (event.type == 'event'){
              bootbox.confirm({
               title:  i18next.t("Move Event") + "?",
                message: i18next.t("Are you sure about this change?") + "\n"  + event.title + " " + i18next.t("will be dropped."),
                buttons: {
                  cancel: {
                    label: '<i class="fa fa-times"></i> ' + i18next.t("Cancel")
                  },
                  confirm: {
                    label: '<i class="fa fa-check"></i> ' + i18next.t("Confirm")
                  }
                },
                callback: function (result) {
                 if (result == true)// only event can be drag and drop, not anniversary or birthday
                 {
                  window.CRM.APIRequest({
                   method: 'POST',
                   path: 'events/',
                   data: JSON.stringify({"evntAction":'moveEvent',"eventID":event.eventID,"start":event.start.format()})
                  }).done(function(data) {
                     // now we can create the event
                     $('#calendar').fullCalendar('removeEvents',event._id);// delete old one
                     $('#calendar').fullCalendar('renderEvent', data, true); // add the new one
                     $('#calendar').fullCalendar('unselect'); 
                  });
                 } else {
                  revertFunc();
                 }
                 console.log('This was logged in the callback: ' + result);
                }        
            });
           } else {
            revertFunc();
           }
        },
        eventResize: function(event, delta, revertFunc) {
          if (event.type == 'event'){
            bootbox.confirm({
             title: i18next.t("Resize Event") + "?",
              message: i18next.t("Are you sure about this change?") + "\n"+event.title + " " + i18next.t("will be dropped."),
              buttons: {
                cancel: {
                  label: '<i class="fa fa-times"></i> ' + i18next.t("Cancel")
                },
                confirm: {
                  label: '<i class="fa fa-check"></i> ' + i18next.t("Confirm")
                }
              },
              callback: function (result) {
               if (result == true)// only event can be drag and drop, not anniversary or birthday
               {
                window.CRM.APIRequest({
                 method: 'POST',
                 path: 'events/',
                 data: JSON.stringify({"evntAction":'resizeEvent',"eventID":event.eventID,"end":event.end.format()})
                }).done(function(data) {
                   // now we can create the event
                   $('#calendar').fullCalendar('removeEvents',event._id);// delete old one
                   $('#calendar').fullCalendar('renderEvent', data, true); // add the new one
                   $('#calendar').fullCalendar('unselect'); 
                });
               } else {
                revertFunc();
               }
               console.log('This was logged in the callback: ' + result);
              }        
          });
         } else {
          revertFunc();
         }
      },
      selectHelper: true,        
      select: function(start, end) {        
       var modal = bootbox.dialog({
         message: BootboxContent,
         title: i18next.t("Event Creation"),
         buttons: [
          {
           label: i18next.t("Save"),
           className: "btn btn-primary pull-left",
           callback: function() {
              var e = document.getElementById("eventType");
              var eventTypeID = e.options[e.selectedIndex].value;
                                         
              var EventTitle =  $('form #EventTitle').val();
              var EventDesc =  $('form #EventDesc').val();
                             
              var e = document.getElementById("EventGroup");
              var EventGroupID = e.options[e.selectedIndex].value;
              var EventGroupType = e.options[e.selectedIndex].title;// we get the type of the group : personal or group for future dev
              var EventPubliclyVisible = $("form #EventPubliclyVisible").prop('checked');
                             
              var Total =  $('form #Total').val();
              var Members = $('form #Members').val();
              var Visitors = $('form #Visitors').val();
              var EventCountNotes = $('form #EventCountNotes').val();
                             
              var eventPredication = CKEDITOR.instances['eventPredication'].getData();//$('form #eventPredication').val();
              
              var add = false;
                                                            
              window.CRM.APIRequest({
                    method: 'POST',
                    path: 'events/',
                    data: JSON.stringify({"evntAction":'createEvent',"eventTypeID":eventTypeID,"EventGroupType":EventGroupType,"EventTitle":EventTitle,"EventDesc":EventDesc,"EventGroupID":EventGroupID,"EventPubliclyVisible":EventPubliclyVisible,"Total":Total,"Members":Members,"Visitors":Visitors,"EventCountNotes":EventCountNotes,"eventPredication":eventPredication,"start":moment(start).format(),"end":moment(end).format()})
              }).done(function(data) {                   
                $('#calendar').fullCalendar('renderEvent', data, true); // stick? = true             
                $('#calendar').fullCalendar('unselect');              
                add = true;              
                modal.modal("hide");   
                
                var box = bootbox.dialog({message : i18next.t("Event was added successfully.")});
                
                setTimeout(function() {
                    // be careful not to call box.hide() here, which will invoke jQuery's hide method
                    box.modal('hide');
                }, 3000);
                return true;
              });

              return add;      
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
       
       // we add the calendars
       addCalendars();
       addEventTypes();      
       
       // this will ensure that image and table can be focused
       $(document).on('focusin', function(e) {e.stopImmediatePropagation();});
       
       $('#EventTitle').on('click',function(){
       		if(this.defaultValue==i18next.t("Calendar Title")){ this.defaultValue=''; this.style.color='#000';};
       });
       
       $('#EventDesc').on('click',function(){	
       	   if(this.defaultValue==i18next.t("Calendar description")){ this.defaultValue=''; this.style.color='#000';};
			 });
       
       // this will create the toolbar for the textarea
       CKEDITOR.replace('eventPredication',{
        customConfig: window.CRM.root+'/skin/js/ckeditor/calendar_event_editor_config.js',
        language : window.CRM.lang,
        width : '100%'
     });
      
       $("#ATTENDENCES").parents("tr").hide();
      },
      eventLimit: withlimit, // allow "more" link when too many events
      locale: window.CRM.lang,
      events: window.CRM.root + '/api/calendar/events',
      eventRender: function (event, element, view) {
        groupFilterID = window.groupFilterID;
        EventTypeFilterID = window.EventTypeFilterID;
        
        if (event.hasOwnProperty('type')){
          if (event.type == 'event' 
            && (groupFilterID == 0 || (groupFilterID>0 && groupFilterID == event.groupID)) 
            && (EventTypeFilterID == 0 || (EventTypeFilterID>0 && EventTypeFilterID == event.eventTypeID))){
            return true;
          } else if(event.type == 'event' 
            && ((groupFilterID>0 && groupFilterID != event.groupID)
                || (EventTypeFilterID>0 && EventTypeFilterID != event.eventTypeID))){
            return false;
          } else if ((event.allDay || event.type != 'event')){// we are in a allDay event          
           if (event.type == 'anniversary' && anniversary == true || event.type == 'birthday' && birthday == true){
            var evStart = moment(view.intervalStart).subtract(1, 'days');
            var evEnd = moment(view.intervalEnd).subtract(1, 'days');
            if (!event.start.isAfter(evStart) || event.start.isAfter(evEnd)) {
              return false;
            }
           } else {
            return false;
           }
          }
         }
      }
    });
  });
