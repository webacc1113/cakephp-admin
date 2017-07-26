<p>Note: Your changes will auto-save as you sort. Once you are done reordering the answers, simply close the dialog and refresh the page to see your changes.</p>
<?php if (isset($question['ProfileAnswer'])): ?>
	<ol class="sortable">
	<?php foreach ($question['ProfileAnswer'] as $answer): ?>
		<li data-id="<?php echo $answer['id'];?>"><?php echo $answer['name']; ?></li>
	<?php endforeach; ?>
	</ul>
<?php endif; ?>

<script type="text/javascript">
var adjustment

$("ol.sortable").sortable({
  group: 'sortable',
  pullPlaceholder: false,
  // animation on drop
  onDrop: function  (item, targetContainer, _super) {
    var clonedItem = $('<li/>').css({height: 0});
    item.before(clonedItem);
    clonedItem.animate({'height': item.height()});
    
    item.animate(clonedItem.position(), function() {
      clonedItem.detach();
      _super(item);
    });

			
	var $items = $('.sortable').sortable("serialize").get();
	
	var $data = new Array();
	for (var $i = 0; $i < $items.length; $i++) {
		$data.push($items[$i].id);
	}
	
	$.ajax({
		type: 'POST',
		url: '/profile_questions/ajax_reorder_answers/' + <?php echo $question['ProfileQuestion']['id']; ?>,
		data: 'order='+$data.join(','),
		statusCode: {
			201: function(data) {
			},
		}
	});	
  },

  // set item relative to cursor position
  onDragStart: function ($item, container, _super) {
    var offset = $item.offset(),
    pointer = container.rootGroup.pointer

    adjustment = {
      left: pointer.left - offset.left,
      top: pointer.top - offset.top
    }

    _super($item, container)
  },
  onDrag: function ($item, position) {
    $item.css({
      left: position.left - adjustment.left,
      top: position.top - adjustment.top
    })
  }
})
</script>