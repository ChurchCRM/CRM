<?php
/*******************************************************************************
 *
 *  filename    : Include/Footer.php
 *  last change : 2002-04-22
 *  description : footer that appear on the bottom of all pages
 *
 *  http://www.infocentral.org/
 *  Copyright 2001-2002 Phillip Hullquist, Deane Barker
 *
 *  InfoCentral is free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 ******************************************************************************/
?>

			</section><!-- /.content -->
		</aside><!-- /.right-side -->
	</div><!-- ./wrapper -->

	<script type="text/javascript" src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.1/js/bootstrap.min.js"></script>

	<!-- AdminLTE App -->
	<script type="text/javascript" src="<?php echo $sURLPath."/"; ?>js/AdminLTE/app.js"></script>

	<script src="//code.jquery.com/ui/1.11.2/jquery-ui.js"></script>
	<script language="javascript" type="text/javascript">
		$("document").ready(function(){
			$(".searchPerson").autocomplete({
				source: "ajax/SearchMembers.php?searchtype=person",
				minLength: 2,
				select: function(event, ui) {
					var location = 'PersonView.php?PersonID='+ui.item.id;
					window.location.replace(location);
					$('#add_per_ID').val(ui.item.id);
				}
			});
		});
	</script>
</body>
</html>
<?php

// Turn OFF output buffering
ob_end_flush();

// Reset the Global Message
$_SESSION['sGlobalMessage'] = "";

?>
