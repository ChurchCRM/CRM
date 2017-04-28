<?php
require "../Include/Config.php";
$query = "SELECT * FROM group_grp ORDER BY grp_ID";
$result = mysql_query($query);
if(!$result) die(mysql_error());
$send = $_GET['send'];
$group = $_GET['group'];
if(isset($group) and (isset($send))){
$query2 = "select * from group_grp where grp_ID='$group'";
$result2 = mysql_query($query2);
if (!$result2) die(mysql_error());
while ($row = mysql_fetch_array($result2)) 
$grpname = $row['grp_Name'];
echo "Messages have been send successfully to the group ".$grpname;
}
//echo $send;
//echo $group;
?>
<FORM action=sendgrpsms.php METHOD=POST>
<h1>Sending Group Message</h1>
Select group to send message to: <br>
<?php echo "<select name='grp_ID'>";
while ($row = mysql_fetch_array($result)) {
    echo "<option value='" . $row['grp_ID'] ."'>" . $row['grp_Name'] ."</option>";
}
echo "</select>";
?>
<br>
Type your message here:<br>
<TEXTAREA NAME="messagetext" ROWS=5 COLS=40></TEXTAREA>
  <br>
  <INPUT TYPE=SUBMIT VALUE=SEND>
</FORM>
<?php
mysql_close($conn);
?>
