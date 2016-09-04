$(document).ready(function() {

  dataT = $("#membersTable").DataTable({
  ajax:{
    url :window.CRM.root+"/api/groups/"+window.CRM.currentGroup+"/members",
    dataSrc:"Person2group2roleP2g2rs"
  },
  columns: [
     {
          width: 'auto',
          title: 'Name',
          data: 'PersonId',
          render: function (data,type,full,meta) {
              return '<img src="'+ full.photo + '" class="direct-chat-img"> &nbsp <a href="PersonView.php?PersonID="' +full.PersonId+ '"><a target="_top" href="PersonView.php?PersonID='+full.PersonId+'">'+ full.Person.FirstName+" "+full.Person.LastName+'</a>';
          }
      },
      {
          width: 'auto',
          title: 'Group Role',
          data: 'RoleId',
          render: function (data,type,full,meta) {
              return data+'<button class="changeMembership" id="changeRole-'+full.per_ID+'"><i class="fa fa-pencil"></i></button>';
          }
      },
      {
          width: 'auto',
          title: 'Address',
          render: function (data,type,full,meta) {
              return full.Person.Address1+" "+full.Person.Address2;
          }
      },
      {
          width: 'auto',
          title: 'City',
          data: 'Person.City'
      },
      {
          width: 'auto',
          title: 'State',
          data: 'Person.State'
      },
      {
          width: 'auto',
          title: 'ZIP',
          data: 'Person.Zip'
      },
      {
          width: 'auto',
          title: 'Cell Phone',
          data: 'Person.CellPhone'
      },
      {
          width: 'auto',
          title: 'E-mail',
          data: 'Person.Email'
      },
      {
          width: 'auto',
          title: 'Remove User from Group',
          render: function (data,type,full,meta) {
              return '<button type="button" class="btn btn-danger removeUserGroup" data-personid='+full.PersonId+'>Remove User from Group</button>';
          }
      }
  ]
  });
  
  $(".personSearch").select2({
      minimumInputLength: 2,
      ajax: {
          url: function (params){
                  return window.CRM.root + "/api/persons/search/"+params.term;
          },
          dataType: 'json',
          delay: 250,
          data: function (params) {
            return {
            q: params.term, // search term
            page: params.page
            };
          },
          processResults: function (rdata, page) {
              var idKey = 1;
              var results = new Array();
              data = JSON.parse(rdata);
              $.each(data[0].persons, function (index,cvalue) {
                var childObject = {
                    id: idKey,
                    objid:cvalue.id,
                    text: cvalue.displayName,
                     uri: cvalue.uri
                };
                idKey++;
                results.push(childObject);
              });
              return {results: results};
          },
          cache: true
      }
  });
    
  $(".personSearch").on("select2:select",function (e) {
    $.ajax({
      method: "POST",
      url: window.CRM.root + "/api/groups/"+window.CRM.currentGroup+"/adduser/"+e.params.data.objid,
      dataType: "json"
    }).done(function (data){
      var person = data.Person2group2roleP2g2rs[0];
      var node = dataT.row.add(person).node();
      dataT.rows().invalidate().draw(true);
    });
    $(".personSearch").select2("val", "");
  });
  
  $("#deleteGroupButton").on("click", function(e) {
      console.log(e);
      $.ajax({
            method: "POST",
            url: window.CRM.root + "/api/groups/"+window.CRM.currentGroup,
            dataType: "json",
            encode: true,
            data: {"_METHOD":"DELETE"}
        }).done(function(data){
            console.log(data);
            if (data.success)
                window.location.href = "GroupList.php";
        });
    });
    
     $("body").on("click" ,".removeUserGroup", function(e) {
        var userid=$(e.currentTarget).data("personid");
        console.log(userid);
        $.ajax({
            method: "POST",
            url: window.CRM.root + "/api/groups/"+window.CRM.currentGroup+"/removeuser/"+userid,
            dataType: "json"
        }).done(function(data){
            dataT.row(function(idx,data,node) { 
              if  (data.PersonId == userid)
              {
                return true;
              } 
            }).remove();
            dataT.rows().invalidate().draw(true);
        });
    });

});



function initHandlers()
{
     $("#chkClear").click(function(e){
             $("#deleteGroupButton").prop("disabled",!e.target.checked);
     });

    

     $(".changeMembership").click(function(e){
        var userid=e.currentTarget.id.split("-")[1];
        console.log(userid);
        $("#changeingMemberID").val(dataT.row(function(idx,data,node) { if  (data.per_ID == userid){return true;} }).data().per_ID);
        $("#changingMemberName").text(dataT.row(function(idx,data,node) { if  (data.per_ID == userid){return true;} }).data().displayName);
        $('#changeMembership').modal('show');

    });

    $("#confirmMembershipChange").click(function(e){
        var changeingMemberID = $("#changeingMemberID").val();
        $.ajax({
            method: "POST",
            url: window.CRM.root + "/api/groups/"+window.CRM.currentGroup+"/userRole/" + changeingMemberID,
            data: JSON.stringify({'roleID': $("#newRoleSelection option:selected").val()}),
            dataType: "json"
        }).done(function(data){
            console.log(data);
            dataT.row(function(idx,data,node) { if  (data.per_ID == changeingMemberID){return true;} }).data(data[0]);
            dataT.rows().invalidate().draw(true);
            initHandlers();
            $('#changeMembership').modal('hide');
        });
    });

    
}
