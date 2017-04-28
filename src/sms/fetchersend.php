<?php
$results = mysql_query("select id,receiver,msg,senttime from messageout order by senttime desc");
$get_total_rows = mysql_fetch_array($results); //total records

//break total records into pages
$item_per_page = 10;
$pages = ceil($get_total_rows[0]/$item_per_page);	

?>
<!DOCTYPE html>
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>Received Messages</title>
<script type="text/javascript" src="js/jquery-1.11.2.min.js"></script>
<script type="text/javascript" src="js/jquery.bootpag.min.js"></script>
<script type="text/javascript">
$(document).ready(function() {
$("#results").load("fetch_send.php");  //initial page number to load
	$(".pagination").bootpag({
	   total: <?php echo $pages; ?>,
	   page: 1,
	   maxVisible: 5 
	}).on("page", function(e, num){
		e.preventDefault();
		$("#results").prepend('<div class="loading-indication"><img src="ajax-loader.gif" /> Loading...</div>');
		$("#results").load("fetch_send.php", {'page':num});
	});

});
</script>
<!--<link href="css/style.css" rel="stylesheet" type="text/css"> -->
</head>
<body>
<div id="results"></div>
<div class="pagination"></div>
</body>
</html>

