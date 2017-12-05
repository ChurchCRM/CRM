time=setInterval(function(){
      //your code
      window.CRM.APIRequest({
          method: 'GET',
          path: 'dashboard/page' + window.CRM.PageName,
    }).done(function(data) {     
      for (var key in data[0]){
        console.log(key);
        window["CRM"]["dashboard"][key](data[0][key]);
      }
    });  
  },1000);
  

