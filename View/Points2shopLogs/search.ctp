<h3><?php echo __('Points2shop API Search')?></h3>
<div class="row-fluid">
	<div class="span6">
		<?php echo $this->Form->create(null, array(
			'class' => 'clearfix form-inline',
			'type' => 'get',
			'url' => array(
				'controller' => 'points2shop_logs',
				'action' => 'search',
			),
		));
		?>
		<div class="form-group">
			<?php
			echo $this->Form->input('project_id', array(
				'label' => false,
				'type' => 'text',
				'placeholder' => 'Project #',
				'value' => isset($this->request->query['project_id']) ? $this->request->query['project_id'] : null
			));
			?>
		</div>
		<div class="form-group">
		<?php echo $this->Form->submit('Search', array('class' => 'btn btn-default')); ?>
		</div>
		<?php echo $this->Form->end(null); ?>
	</div>
</div>

<?php if (!empty($this->request->query['project_id']) && !empty($points2shop_project)) : ?>
	<div class="row-fluid">
		<pre><?php print_r($points2shop_project); ?></pre>
	</div>
<?php endif; ?>
<div class="clearfix"></div>