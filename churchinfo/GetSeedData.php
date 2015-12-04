<?php
$count = 10;
$response = file_get_contents("http://api.randomuser.me/?results=".$count);
$data=json_decode($response);

$rs = $data->results;

foreach($rs as $index=>$u)
{
	$user=$u->user;
	print "<br>";
	print $index;
	$gender = $user->gender;
	$name = $user->name->title." ". $user->name->first." ". $user->name->last;
	$location = $user->location->street." ". $user->location->city." ". $user->location->state." ". $user->location->zip;
	print $gender."\r\n";
	print $name."\r\n";
	
	
	INSERT INTO `person_per` VALUES (1,NULL,'ChurchInfo',NULL,'Admin',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,0,0,0000,NULL,0,0,0,0,NULL,NULL,'0000-00-00 00:00:00',0,0,NULL,0),(2,'Mr.','Charles','','Klotz','','','','','','','United States','5555555555','','','someone@somewhere.com','',4,6,NULL,NULL,1,1,1,1,0,'2015-11-30 21:56:22','2015-11-21 21:04:50',1,2,'2015-11-21',0),
	
	#print_r($user->user);
	print "<br>";
}


?>