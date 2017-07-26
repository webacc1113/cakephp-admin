<h3>Users</h3>

<style type="text/css">
	img {
		max-width: 16px;
	}
	td.gender {
		width: 34px;
		text-align: right;
	}
</style>

<script type="text/javascript">
	$(document).ready(function() {
		$('.tt').tooltip();
	});
</script>

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
		<?php echo $this->Form->create('User', array('type' => 'get', 'class' => 'filter', 'url' => array('action' => 'index', 'page' => null))); ?>
			<div class="padded separate-sections">					
				<div class="row-fluid">
					<div class="filter">
						<?php echo $this->Form->input('active', array(
							'class' => 'uniform',
							'type' => 'select', 
							'label' => '&nbsp;',
							'value' => isset($this->data['active']) ? $this->data['active']: null,
							'options' => array(
								'0' => 'All users',
								'1' => 'Verified users', 
								'2' => 'Unverified users',
								'3' => 'Hellbanned users',
								'4' => 'Deleted users',
								'5' => 'Unsubscribed users',
								'6' => 'Unverified w/ EXT registration'
							)
						)); ?>
					</div>
					<div class="filter">
					<?php echo $this->Form->input('keyword', array(
						'placeholder' => 'Search keyword',
						'value' => isset($this->data['keyword']) ? $this->data['keyword']: null
					)); ?>
					</div>
				
					<div class="filter">
					<?php echo $this->Form->input('source', array(
						'class' => 'uniform',
						'type' => 'select', 
						'empty' => 'Select:',
						'label' => 'Traffic Source',
						'value' => isset($this->data['source']) ? $this->data['source']: null,
						'onchange' => 'return MintVine.ShowPublishers(this)'
					)); ?>
					</div>
					
					<div class="filter">
					<?php echo $this->Form->input('country', array(
						'class' => 'uniform',
						'type' => 'select', 
						'empty' => 'Select:',
						'label' => 'Country',
						'value' => isset($this->data['country']) ? $this->data['country']: null,
						'options' => $countries
					)); ?>
					</div>
					<div class="filter">
					<?php echo $this->Form->input('pubid', array(
						'label' => 'Publisher ID',
						'type' => 'select', 
						'empty' => 'Select:',
						'options' => (isset($publishers)) ? $publishers : array(),
						'value' => isset($this->data['pubid']) ? $this->data['pubid']: null,
						'style' => 'width: auto;'
					)); ?>
					</div>
				
					<div class="filter date-group">
						<label>Registered between:</label>
						<?php echo $this->Form->input('created_from', array(
							'label' => false, 
							'class' => 'datepicker',
							'data-date-autoclose' => true,
							'placeholder' => 'Start date',
							'value' => isset($this->data['created_from']) ? $this->data['created_from']: null
						)); ?> 
						<?php echo $this->Form->input('created_to', array(
							'label' => false, 
							'class' => 'datepicker',
							'data-date-autoclose' => true,
							'placeholder' => 'End date',
							'value' => isset($this->data['created_to']) ? $this->data['created_to']: null
						)); ?>
					</div>
					<div class="filter">
						<?php echo $this->Form->input('user_level', array(
							'class' => 'uniform',
							'type' => 'select',
							'empty' => 'Select:',
							'value' => isset($this->data['user_level']) ? $this->data['user_level'] : null,
							'options' => unserialize(USER_LEVELS)
						));	?>
					</div>
					<div class="clearfix"></div>
					<div class="filter date-group">
						<label>Verified between:</label>
						<?php echo $this->Form->input('verified_from', array(
							'label' => false, 
							'class' => 'datepicker',
							'data-date-autoclose' => true,
							'placeholder' => 'Start date',
							'value' => isset($this->data['verified_from']) ? $this->data['verified_from']: null
						)); ?> 
						<?php echo $this->Form->input('verified_to', array(
							'label' => false, 
							'class' => 'datepicker',
							'data-date-autoclose' => true,
							'placeholder' => 'End date',
							'value' => isset($this->data['verified_to']) ? $this->data['verified_to']: null
						)); ?>
					</div>
				</div>
			</div>
			<div class="form-actions">
				<?php echo $this->Form->submit('Search', array('class' => 'btn btn-primary')); ?>
			</div>
		<?php echo $this->Form->end(null); ?>
	</div>
</div>

<?php if (isset($users) && !empty($users)): ?>
	<a style="float:right;" title="Export Users" href="javascript:void();" onclick="$('#exportUserModal').modal('show');">
			<i style="font-size:25px;" class="icon-download-alt"></i>
	</a>
	<p class="count">Showing <?php 
		echo number_format($this->Paginator->counter(array('format' => '{:current}')));
	?> of <?php
		echo number_format($this->Paginator->counter(array('format' => '{:count}')));
	?> matches</p>

	<div class="box">	
		<table cellpadding="0" cellspacing="0" class="table table-normal">
			<thead>
				<tr>
					<td class="gender"></td>
					<td><?php echo $this->Paginator->sort('name'); ?></td>
					<td>Level</td>
					<td>Age</td>
					<td><?php echo $this->Paginator->sort('country_id'); ?></td>
					<td><?php echo $this->Paginator->sort('state_id'); ?></td>
					<?php if (!isset($showing_hellbanned) || !$showing_hellbanned): ?>
					<td><?php echo $this->Paginator->sort('created'); ?></td>
					<?php endif; ?>
					<td><?php echo $this->Paginator->sort('verified'); ?></td>
					<?php if (isset($showing_hellbanned) && $showing_hellbanned): ?>
					<td><?php echo $this->Paginator->sort('hellbanned_on', 'Hellbanned'); ?></td>
					<td><?php echo $this->Paginator->sort('hellban_score', 'Score'); ?></td>
					<?php endif; ?>
					<td><?php echo $this->Paginator->sort('last_touched'); ?></td>
					<?php if (!isset($showing_hellbanned) || !$showing_hellbanned): ?>
					<td><?php echo $this->Paginator->sort('active'); ?></td>
					<?php endif; ?>
					<td><?php echo $this->Paginator->sort('origin'); ?></td>
					<td><?php echo $this->Paginator->sort('balance'); ?></td>
					<?php if (!isset($showing_hellbanned) || !$showing_hellbanned): ?>
					<td><?php echo $this->Paginator->sort('pending'); ?></td>
					<?php endif; ?>
					<td><?php echo $this->Paginator->sort('total', 'Lifetime*', array(
						'class' => 'tt',
						'data-placement' => 'top',
						'data-toggle' => 'tooltip', 
						'title' => 'Calculated once per 24 hours'
					)); ?></td>					
				</tr>
			</thead>
			<tbody>
				<?php foreach ($users as $user): ?>
					<?php echo $this->Element('user_row', array(
						'user' => $user, 
						'showing_hellbanned' => $showing_hellbanned
					)); ?>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>

	<?php if ($users) : ?>
		<?php foreach ($users as $user): ?>
			<?php echo $this->Element('modal_user_hellban', array('user' => $user['User'])); ?>
			<?php echo $this->Element('modal_user_remove_hellban', array('user' => $user['User'])); ?>
		<?php endforeach; ?>
	<?php endif; ?>

	<?php echo $this->Element('pagination'); ?>
	<?php echo $this->Element('modal_user_score'); ?>
	<?php echo $this->Element('modal_user_quickprofile'); ?>
	<?php echo $this->Element('modal_user_referrer'); ?>
	<?php echo $this->Element('modal_export_users'); ?>
<?php endif; ?>
