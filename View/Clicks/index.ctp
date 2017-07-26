<h3>Click Templates</h3>
<div class="row-fluid">
	<div class="span9">
		<div class="box">
			<table class="table table-normal">
				<thead>
					<tr>
						<td>Name</td>
						<td>Country</td>
						<td>Key</td>
						<td></td>
					</tr>
				</thead>
				<tbody>
					<?php foreach ($click_templates as $click_template): ?>
						<tr>
							<td><?php echo $click_template['ClickTemplate']['name'] ?></td>
							<td><?php echo $click_template['ClickTemplate']['country'] ?></td>
							<td><?php echo $click_template['ClickTemplate']['key'] ?></td>
							<td>
								<?php
								echo $this->Html->link('Add Distribution', array(
									'controller' => 'clicks',
									'action' => 'add_distributions',
									$click_template['ClickTemplate']['id']
								), array(
									'class' => 'btn btn-small btn-default'
								));
								?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	</div>
</div>