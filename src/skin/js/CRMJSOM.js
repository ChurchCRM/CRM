/*
 * ChurcmCRM JavaScript Object Model Initailizaion Script
 */

    window.CRM.DisplayErrorMessage = function(endpoint, error) {

      message = "<p>Error making API Call to: " + endpoint +
        "</p><p>Error text: " + error.message;
      if (error.trace)
      {
        message += "</p>Stack Trace: <pre>"+JSON.stringify(error.trace, undefined, 2)+"</pre>";
      }
      bootbox.alert({
        title: "ERROR",
        message: message
      });
    };

    window.CRM.VerifyThenLoadAPIContent = function(url) {
      var error = "There was a problem retrieving the requested object";
      $.ajax({
        type: 'HEAD',
        url: url,
        async: false,
        statusCode: {
          200: function() {
            window.open(url);
          },
          404: function() {
            window.CRM.DisplayErrorMessage(url, {message: error});
          },
          500: function() {
            window.CRM.DisplayErrorMessage(url, {message: error});
          }
        }
      });
    }

    $(document).ajaxError(function (evt, xhr, settings) {
        try {
            var CRMResponse = JSON.parse(xhr.responseText);
            window.CRM.DisplayErrorMessage(settings.url, CRMResponse);
        } catch(err) {}
    });

    function LimitTextSize(theTextArea, size) {
        if (theTextArea.value.length > size) {
            theTextArea.value = theTextArea.value.substr(0, size);
        }
    }

    function popUp(URL) {
        var day = new Date();
        var id = day.getTime();
        eval("page" + id + " = window.open(URL, '" + id + "', 'toolbar=0,scrollbars=yes,location=0,statusbar=0,menubar=0,resizable=yes,width=600,height=400,left = 100,top = 50');");
    }