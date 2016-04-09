$("document").ready(function(){

    $(".groupSpecificProperties").click(function (e){
            var groupPropertyAction = e.currentTarget.id;
            if (groupPropertyAction == "enableGroupProps")
            {
                $("#groupSpecificPropertiesModal").modal("show");
                $("#gsproperties-label").text("Confirm Enable Group Specific Properties");
                $("#groupSpecificPropertiesModal .modal-body span").text("This will create a group-specific properties table for this group.  You should then add needed properties with the Group-Specific Properties Form Editor.");
                $("#setgroupSpecificProperties").text("Enable Group Specific Properties");
            }
            else
            {
                  $("#groupSpecificPropertiesModal").modal("show");
                $("#gsproperties-label").text("Confirm Disable Group Specific Properties");
                $("#groupSpecificPropertiesModal .modal-body span").text("Are you sure you want to remove the group-specific person properties?  All group member properties data will be lost!");
                $("#setgroupSpecificProperties").text("Disable Group Specific Properties");
            }
    });



    $("#selectGroupIDDiv").hide();

    $("#cloneGroupRole").click(function(e){
    if (e.target.checked)
        $("#selectGroupIDDiv").show();
    else
    {
        $("#selectGroupIDDiv").hide();
        $("#seedGroupID").prop('selectedIndex',0);
    }
    });

    $("#groupEditForm").submit(function(e) {
        e.preventDefault();

        var formData ={
            "groupName": $("input[name='Name']").val(),
            "description": $("textarea[name='Description']").val(),
            "groupType" : $("select[name='GroupType'] option:selected").val()

        };
        $.ajax({
            method: "POST",
            url:   window.CRM.root + "/api/groups/"+groupID,
            data:  JSON.stringify(formData)
        }).done(function(data){
           window.location.href = CRM.root + "/GroupList.php";
        });

    });

    $("#addNewRole").click(function(e) {
        var newRoleName = $("#newRole").val();

        $.ajax({
            method: "POST",
            url:    window.CRM.root + "/api/groups/"+groupID+"/roles",
            data:  '{"roleName":"'+newRoleName+'"}'
        }).done(function(data){
            var newRole = data.newRole;
            var newRow={"lst_OptionName":newRole.roleName,"lst_OptionID":newRole.roleID,"lst_OptionSequence":newRole.sequence};
            roleCount+=1;
            var node = dataT.row.add(newRow).node();
            dataT.rows().invalidate().draw(true);
            $("#newRole").val('');
            //location.reload(); // this shouldn't be necessary
        });

    });

    $(document).on('click','.deleteRole', function(e) {
        var roleID = e.currentTarget.id.split("-")[1];

        console.log("deleting group role: "+roleID);
        $.ajax({
            method: "DELETE",
            url:    window.CRM.root + "/api/groups/"+groupID+"/roles/"+roleID
        }).done(function(data){
            console.log(data);
            dataT.clear();
            dataT.rows.add(data);
            if (roleID == defaultRoleID)        // if we delete the default group role, set the default group role to 1 before we tell the table to re-render so that the buttons work correctly
                defaultRoleID =1;
            dataT.rows().invalidate().draw(true);



        });
    });

    $(document).on('click','.rollOrder', function (e) {

       var roleID = e.currentTarget.id.split("-")[1]; // get the ID of the role that we're manipulating
       var roleSequenceAction =  e.currentTarget.id.split("-")[0];  //determine whether we're increasing or decreasing this role's sequence number
       var newRoleSequence =0;      //create a variable at the function scope to store the new role's sequence
       var currentRoleSequence = dataT.cell(function(idx,data,node) { if  (data.lst_OptionID == roleID){console.log(data); return true;} } ,2).data(); //get the sequence number of the selected role
       console.log("current sequence: "+currentRoleSequence);
       if (roleSequenceAction == "roleUp")
       {
           newRoleSequence = Number(currentRoleSequence)-1;  //decrease the role's sequence number
       }
       else if(roleSequenceAction == "roleDown")
       {
           newRoleSequence = Number(currentRoleSequence)+1; // increase the role's sequenc number
       }
       //try
       //{
            replaceRow = dataT.row(function(idx,data,node) { if  (data.lst_OptionSequence == newRoleSequence){return true;}});
            console.log("------------");
            var d = replaceRow.data();
            console.log(d);
            d.lst_OptionSequence=currentRoleSequence;
            setGroupRoleOrder(groupID,d.lst_OptionID,d.lst_OptionSequence);
            console.log(d);
            replaceRow.data(d);
            console.log("************");
       //}
       //catch(err)
       //{
        //   console.log("no cells to replace - something was funky.");
       //}
      dataT.cell(function(idx,data,node) { if  (data.lst_OptionID == roleID){return true;}}, 2).data(newRoleSequence); // set our role to the new sequence number
      setGroupRoleOrder(groupID,roleID,newRoleSequence);
      dataT.rows().invalidate().draw(true);
      dataT.order([[ 2, "asc" ]]).draw();

    });

    $(document).on('change','.roleName',function(e){

        var groupRoleName = e.target.value;
        var roleID=e.target.id.split("-")[1];
        $.ajax({
            method: "POST",
            url:    window.CRM.root + "/api/groups/"+groupID+"/roles/"+roleID,
            data: '{"groupRoleName":"'+groupRoleName+'"}'
        }).done(function(data){
        });

    });

    $(document).on('click','.defaultRole', function(e){
       console.log(e);
        var roleID=e.target.id.split("-")[1];
        $.ajax({
            method: "POST",
            url:    window.CRM.root + "/api/groups/"+groupID+"/defaultRole",
            data: '{"roleID":"'+roleID+'"}'
        }).done(function(data){
            defaultRoleID=roleID; //update the local variable representing the default role id
            dataT.rows().invalidate().draw(true);
             // re-register the JQuery handlers since we changed the DOM, and new buttons will not have an action bound.
        });
    });

    dataT =  $("#groupRoleTable").DataTable({
    data:groupRoleData,
    columns: [
        {
            width: 'auto',
            title:'Role Name',
            data:'lst_OptionName',
            render: function  (data, type, full, meta ) {
                if ( type === 'display')
                    return '<input type="text" class="roleName" id="roleName-'+full.lst_OptionID+'" value="'+data+'">';
                else
                    return data;
            }
        },
        {
            width: 'auto',
            title:'Make Default',
            render: function  (data, type, full, meta ) {
                   if (full.lst_OptionID == defaultRoleID)
                   {
                       return "<strong><i class=\"fa fa-check\"></i> Default</strong>";
                   }
                   else
                   {
                       return '<button type="button" id="defaultRole-'+ full.lst_OptionID +'" class="btn btn-success defaultRole">Default</button>';
                   }
            }
        },
        {
            width: '200px',
            title:'Sequence',
            data:'lst_OptionSequence',
            className: "dt-body-center",
            render: function  (data, type, full, meta ) {
                if (type === 'display'){
                var sequenceCell = "";
                if( data > 1 )
                {
                    sequenceCell += '<button type="button" id="roleUp-'+full.lst_OptionID+'" class="btn rollOrder"> <i class="fa fa-arrow-up"></i></button>&nbsp;';
                }
                sequenceCell += data;
                if (data != roleCount)
                {
                    sequenceCell += '&nbsp;<button type="button" id="roleDown-'+full.lst_OptionID+'" class="btn rollOrder"> <i class="fa fa-arrow-down"></i></button>';
                }
                return sequenceCell;
                }
                else
                {
                    return data;
                }
            }
        },
         {
            width: 'auto',
            title:'Delete',
            render: function  (data, type, full, meta ) {
                return '<button type="button" id="roleDelete-'+full.lst_OptionID+'" class="btn btn-danger deleteRole">Delete</button>';

            }
        },

    ],
    "order": [[ 3, "asc" ]]
    });

     // initialize the event handlers when the document is ready.  Don't do it here, since we need to be able to initialize these handlers on the fly in response to user action.
});


function setGroupRoleOrder(groupID,roleID,groupRoleOrder)
{
    $.ajax({
        method: "POST",
        url:    window.CRM.root + "/api/groups/"+groupID+"/roles/"+roleID,
        data:   '{"groupRoleOrder":"'+groupRoleOrder+'"}'
    }).done(function(data){
    });
}
