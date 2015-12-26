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
				source: function (request, response) {
					$.ajax({
						url: 'api/persons/search/'+request.term,
						dataType: 'json',
						type: 'GET',
						success: function (data) {
							response($.map(data.persons, function (item) {
								return {
                                    label: item.fullName,
									value: item.id
								}
							}));
						}
					})
				},
				select: function (event, ui) {
                    var location = 'PersonView.php?PersonID='+ui.item.value;
                    window.location.replace(location);
					return false;
				},
				minLength: 2
			});

            $(".searchFamily").autocomplete({
                source: function (request, response) {
                    $.ajax({
                        url: 'api/families/search/'+request.term,
                        dataType: 'json',
                        type: 'GET',
                        success: function (data) {
                            response($.map(data.families, function (item) {
                                return {
                                    label: item.displayName,
                                    value: item.id
                                }
                            }));
                        }
                    })
                },
                select: function (event, ui) {
                    var location = 'FamilyView.php?FamilyID='+ui.item.value;
                    window.location.replace(location);
                    return false;
                },
                minLength: 2
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
