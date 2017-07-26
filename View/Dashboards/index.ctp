<script type="text/javascript">
	$(document).ready(function() {
		$('#dashboard_sortable').load('<?php echo Router::url(array('controller'=>'dashboards','action'=>'lst'));?>');
	});
</script>
<style type="text/css">
	span.label {
		font-weight: normal;
		text-transform: uppercase;
	}
	table td.id {
		width: 20px;
	}
	table tr.closed {
		color: #999;
	}
</style>

<h3>Dashboard</h3>
<div class="row-fluid">
	<div class="span6">
		<div class="box" style="height: 240px; overflow-y: auto">
			<div class="box-header"><span class="title">Offers</span></div>
			<table cellpadding="0" cellspacing="0" class="table table-normal">
				<tbody>
					<?php foreach ($offers as $offer): ?>
						<tr>
							<td>
							<input type="checkbox" name="offers[]" value="<?php echo $offer['Offer']['id'];?>" onclick="return MintVine.AddToDashboard(this, <?php echo $offer['Offer']['id'];?>, '<?php echo addslashes($offer['Offer']['offer_title']);?>', 'offer')" <?php if ($offer_ids && in_array($offer['Offer']['id'], $offer_ids)):?> checked="checked" <?php endif;?>>
							</td>
							<td><?php echo $offer['Offer']['offer_title'];?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<div class="box" style="height: 240px; overflow-y: auto">
			<div class="box-header"><span class="title">Polls</span></div>
			<table cellpadding="0" cellspacing="0" class="table table-normal">
				<tbody>
					<?php foreach ($polls as $poll): ?>
						<tr>
							<td>
							<input type="checkbox" name="polls[]" value="<?php echo $poll['Poll']['id'];?>" onclick="return MintVine.AddToDashboard(this, <?php echo $poll['Poll']['id'];?>, '<?php echo addslashes($poll['Poll']['poll_question']);?>', 'poll')" <?php if ($poll_ids && in_array($poll['Poll']['id'], $poll_ids)):?> checked="checked" <?php endif;?>>
							</td>
							<td><?php echo $poll['Poll']['poll_question'];?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<div class="box" style="height: 240px; overflow-y: auto">
			<div class="box-header"><span class="title">Surveys</span></div>
			<table cellpadding="0" cellspacing="0" class="table table-normal">
				<tbody>
					<?php foreach ($projects as $project): ?>
						<tr>
							<td>
							<input type="checkbox" name="surveys[]" value="<?php echo $project['Project']['id'];?>" onclick="return MintVine.AddToDashboard(this, <?php echo $project['Project']['id'];?>, '<?php echo addslashes($project['Project']['survey_name']);?>', 'survey')" <?php if ($survey_ids && in_array($project['Project']['id'], $survey_ids)):?> checked="checked" <?php endif;?>>
							</td>
							<td><?php echo $project['Project']['survey_name'];?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	</div>
	<div class="span6">
		<div class="box">
			<div class="box-header"><span class="title">Please change dashboard order here</span></div>
			<div id="dashboard_sortable"></div>
		</div>
	</div>
</div>