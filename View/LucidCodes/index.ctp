<h3>Lucid Codes</h3>
<div class="box">
	<div class="box-header">
		<span class="title">Filters</span>
		<ul class="box-toolbar">
			<li>
				<?php echo $this->Html->link('<i class="icon-remove-sign"></i> Clear filters', array('action' => 'index'), array('escape' => false)); ?>
			</li>
		</ul>
	</div>
	<div class="box-content">
		<?php echo $this->Form->create('LucidCode', array('type' => 'get', 'class' => 'filter', 'url' => array('action' => 'index'))); ?>
			<div class="padded separate-sections">					
				<div class="row-fluid">
					<div class="filter">
						<?php echo $this->Form->input('project_id', array(
							'label' => 'Project id',
							'type' => 'text',
							'value' => isset($this->request->query['project_id']) ? $this->request->query['project_id']: null
						)); ?>
					</div>
					<div class="filter">
						<?php echo $this->Form->input('user_id', array(
							'label' => 'User id',
							'type' => 'text',
							'value' => isset($this->request->query['user_id']) ? $this->request->query['user_id']: null
						)); ?>
					</div>
					<div class="filter date-group">
						<?php echo $this->Form->input('date_from', array(
							'label' => 'Date from', 
							'class' => 'datepicker',
							'data-date-autoclose' => true,
							'value' => isset($this->request->query['date_from']) ? $this->request->query['date_from']: null
						)); ?> 
						<?php echo $this->Form->input('date_to', array(
							'label' => 'Date to', 
							'class' => 'datepicker',
							'data-date-autoclose' => true,
							'value' => isset($this->request->query['date_to']) ? $this->request->query['date_to']: null
						)); ?>
					</div>
				</div>
			</div>
			<div class="form-actions">
				<?php echo $this->Form->submit('Export to csv', array('class' => 'btn btn-primary')); ?>
			</div>
		<?php echo $this->Form->end(null); ?>
	</div>
</div>