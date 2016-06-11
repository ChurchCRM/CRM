<HTML>
<BODY>
<H1> Send an SMS </H1>
<?php
$phonenum = $_GET['phonenum'];
if($phonenum!=''){
//eliminate every char except 0-9
$justNums = preg_replace("/[^0-9]/", '', $phonenum);

//eliminate leading 1 if its there
if (strlen($justNums) == 11) $justNums = preg_replace("/^1/", '',$justNums);

//if we have 10 digits left, it's probably valid.
if (strlen($justNums) == 10) {
$isPhoneNum = true;
$phonenum = $justNums;}
else die("Phone number is incorrect");
}

$messagetext = $_GET['messagetext'];
if ($phonenum !='') {
  $sql = "INSERT INTO messageout (receiver,msg,status) ".
         "VALUES ('$phonenum','$messagetext','send')";
  mysql_query($sql);

  echo 'The message has been submitted for sending';
}
?>

<FORM action=sendsms.php METHOD=POST>
  Mobile phone number:
  <INPUT TYPE="TEXT" SIZE="16" NAME="phonenum" VALUE="">
  <br>
  <TEXTAREA NAME="messagetext" ROWS=5 COLS=40></TEXTAREA>
  <br>
  <INPUT TYPE=SUBMIT VALUE=SEND>
</FORM>

</BODY>
</HTML>
