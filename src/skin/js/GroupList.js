$(document).ready(function () {
  window.CRM.groupsInCart = 0;
  $.ajax({
    method: "GET",
    url: window.CRM.root + "/api/groups/groupsInCart",
    dataType: "json"
  }).done(function (data) {
    window.CRM.groupsInCart = data.groupsInCart;
  });

  $("#addNewGroup").click(function (e) {
    var groupName = $("#groupName").val(); // get the name of the group from the textbox
    if (groupName) // ensure that the user entered a group name
    {
      var newGroup = {'groupName': groupName};    //create a newgroup JSON object, and prepare it for transport
      $.ajax({
        method: "POST",
        url: window.CRM.root + "/api/groups/",               //call the groups api handler located at window.CRM.root
        data: JSON.stringify(newGroup),                      // stringify the object we created earlier, and add it to the data payload
        contentType: "application/json; charset=utf-8",
        dataType: "json"
      }).done(function (data) {                               //yippie, we got something good back from the server
        dataT.row.add(data);                                //add the group data to the existing DataTable
        dataT.rows().invalidate().draw(true);               //redraw the dataTable
        $("#groupName").val(null);
        dataT.ajax.reload();// PL : We should reload the table after we add a group so the button add to group is disabled
      });
    }
    else {

    }
  });

  var dataTableConfig = {
    "initComplete": function( settings, json ) {
        if (window.groupSelect != null)
        {
          dataT.search(window.groupSelect).draw();
        }
    },
    ajax: {
      url: window.CRM.root + "/api/groups/",
      type: 'GET',
      dataSrc: "Groups"
    },
    columns: [
      {
        width: 'auto',
        title:i18next.t( 'Group Name'),
        data: 'Name',
        render: function (data, type, full, meta) {
          return '<a href=\'GroupView.php?GroupID=' + full.Id + '\'><span class="fa-stack"><i class="fa fa-square fa-stack-2x"></i><i class="fa fa-search-plus fa-stack-1x fa-inverse"></i></span></a><a href=\'GroupEditor.php?GroupID=' + full.Id + '\'><span class="fa-stack"><i class="fa fa-square fa-stack-2x"></i><i class="fa fa-pencil fa-stack-1x fa-inverse"></i></span></a>' + data;
        }
      },
      {
        width: 'auto',
        title:i18next.t( 'Members'),
        data: 'memberCount',
        searchable: false,
        defaultContent: "0"
      },
      {
        width: 'auto',
        title:i18next.t( 'Group Cart Status'),
        searchable: false,
        render: function (data, type, full, meta) {
          // we add the memberCount, so we could disable the button Add All
          return '<span class="cartStatusButton" data-groupid="' + full.Id + '" data-memberCount="'+ full.memberCount +'">'+i18next.t("Checking Cart Status")+'</span>';

        }
      },
      {
        width: 'auto',
        title:i18next.t( 'Group Type'),
        data: 'groupType',
        defaultContent: "",
        searchable: true,
        render: function (data, type, full, meta) {
      if (data)
      {
            return data;
          }
          else
          {
            return i18next.t('Unassigned');
          }
        }
      }
    ]
  }

  $.extend(dataTableConfig, window.CRM.plugin.dataTable);

  dataT = $("#groupsTable").DataTable(dataTableConfig).on('draw.dt', function () {
    $(".cartStatusButton").each(function (index, element) {
      var objectID = $(element).data("groupid");
      var numberOfMembers = $(element).data("membercount"); // PL : we know the number of members

      var activLink = '';
      if (numberOfMembers == 0){
        activLink=' disabled'; // PL : We disable the button Add All when there isn't any member in the group
      }

      if ($.inArray(objectID, window.CRM.groupsInCart) > -1) {
        $(element).html("<span>"+i18next.t("All members of this group are in the cart")+"</span><a onclick=\"saveScrollCoordinates()\" class=\"btn btn-danger \" id=\"removeGroupFromCart\" data-groupid=\"" + objectID + "\">" + i18next.t("Remove all") + "</a>");
      }
      else {
        $(element).html("<span>"+i18next.t("Not all members of this group are in the cart")+"</span><a onclick=\"saveScrollCoordinates()\" id=\"AddGroupToCart\" class=\"btn btn-primary"+activLink+"\" data-groupid=\"" + objectID + "\">" + i18next.t("Add all") + "</a>");
      }
    });
  });

  $('#table-filter').on('change', function(){
       dataT.search(this.value).draw();
       localStorage.setItem("groupSelect",this.selectedIndex);
  });

  $(document).on("click","#AddGroupToCart",function(link){
    var groupid = link.toElement.dataset.groupid;
    var parent = $(this).parent().find("span");
    window.CRM.cart.addGroup(groupid,function(data){
        link.target.id = "removeGroupFromCart";
        link.target.className = "btn btn-danger";
        link.target.innerText = i18next.t("Remove all");
        parent.text(i18next.t("All members of this group are in the cart"));
    });
  });

  $(document).on("click","#removeGroupFromCart",function(link){
    var groupid = link.toElement.dataset.groupid;
    var parent = $(this).parent().find("span");
    window.CRM.cart.removeGroup(groupid,function(data){
        link.target.id = "AddGroupToCart";
        link.target.className = "btn btn-primary";
        link.target.innerText = i18next.t("Add all");
        parent.text(i18next.t("Not all members of this group are in the cart"));
    });
  });

});
