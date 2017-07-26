<script type="text/javascript">
	$(document).ready(function() {
		$("#sortable").sortable({
			axis: 'y',
			update: function(event, ui) {
				var sorted = $( "#sortable" ).sortable( "serialize");
				$.ajax({
					type: "POST",
					url: "/dashboards/save_order",
					data: sorted,
				}).done(function( msg ) {
				});
			}
		});
	});
</script>
<div id="sortable" class="box-content">
	<?php foreach ($items as $item): ?>
	<div id="items_<?php echo $item['Dashboard']['id']; ?>" class="box-section"><span><?php echo $item['Dashboard']['item_title']; ?> - <?php echo $item['Dashboard']['item_type'];?></span></div>
	<?php endforeach; ?>
</div>