<div class="row-fluid padded">
	<div class="span8">
		<?php echo $this->Form->create(null, array('type' => 'file', 'class' => 'filter')); ?>
			<div class="box">
				<div class="box-header">
					<span class="title">Projects Performance by Day</span>
				</div>
				<div class="box-content">
					<div class="padded separate-sections">
						<div>
							<?php
							echo $this->Form->input('group', array(
								'type' => 'select',
								'label' => 'Group',
								'options' => array('all' => 'All') + $groups,
								'empty' => 'Select a group',
								'required' => true,
								'value' => isset($this->request->data['Report']['group_key']) ? $this->request->data['Report']['group_key'] : null));
							?>
						</div>
						<div>
							<?php echo $this->Form->input('filter_ids', array(
								'type' => 'textarea',
								'label' => 'Filter by Project ID'
							)); ?>
						</div>
						<div class="row-fluid">
							<div class="filter date-group">
								<label>Report Date:</label>
								<?php
								echo $this->Form->input('date', array(
									'label' => false,
									'required' => true,
									'class' => 'datepicker',
									'type' => 'text',
									'data-date-autoclose' => true,
									'placeholder' => 'Date',
									'value' => isset($this->request->data['Report']['date']) ? $this->request->data['Report']['date'] : date('m/d/Y')
								));
								?> 
							</div>
						</div>
					</div>
					<div class="form-actions">
						<?php echo $this->Form->submit('Download Report', array('class' => 'btn btn-primary')); ?>
					</div>
				</div>
			</div>
		<?php echo $this->Form->end(); ?>
	</div>
	<div class="span4">
		<div class="box">
			<div class="box-header">
				<span class="title">Description</span>
			</div>
			<div class="box-content">
				<div class="padded">
					<p>This report will take all the projects within a group on a given date, and given you the performance of each of those projects just on that date.</p>
				</div>
			</div>
		</div>
	</div>
</div>
