<?php echo $this->Form->create(); ?>
<div class="box">
	<div class="box-header">
		<div class="pull-right" style="padding: 6px 6px 0 0;">
			<?php echo $this->Html->link('Select all hellbanned', 
				'#', 
				array(
					'onclick' => 'return check_all(this, true);', 
					'class' => 'btn btn-small btn-primary tt',
					'data-placement' => 'bottom',
					'data-toggle' => 'tooltip', 
					'title' => 'Maximum of 500 hellbanned users will be selected at once, you may need to iterate over the page a few times if more then 500 hellbanned users are found in the list.'
				)
			); ?>
		</div>
		<div class="pull-right" style="padding: 6px 6px 0 0;">
			<?php echo $this->Html->link('Select all unhellbanned', 
				'#', 
				array(
					'onclick' => 'return check_all(this, false);', 
					'class' => 'btn btn-small btn-primary tt',
					'data-placement' => 'bottom',
					'data-toggle' => 'tooltip', 
					'title' => 'Maximum of 500 unhellbanned users will be selected at once, you may need to iterate over the page a few times if more then 500 unhellbanned users are found in the list.'
				)
			); ?>
		</div>
		<span class="title">Referral Tree for <?php echo $this->App->username($user['User']); ?>
			<?php echo $this->Element('user_delete_flag', array('deleted' => $user['User']['deleted_on']));?></span>
	</div>
	<div class="box-content">
		<div class="padded">
			
			<?php if ($grandparent): ?>
			<ul>
				<li><?php echo $this->Element('referral_tree_user', array('user' => $grandparent)); ?>
			<?php endif; ?>
					<?php if (!empty($user['Referrer']['id'])): ?>
					<ul>
						<li><?php echo $this->Element('referral_tree_user', array('user' => $user['Referrer'])); ?>
					<?php endif; ?>
							<ul>
								<li><?php echo $this->Element('referral_tree_user', array('user' => $user)); ?>				
									<?php if ($referred_users) : ?>
										<ul>
										<?php foreach ($referred_users as $referred_user): ?>
											<li><?php echo $this->Element('referral_tree_user', array('user' => $referred_user)); ?></li>							
										<?php endforeach; ?>
										</ul>
									<?php endif; ?>	
								</li>
							</ul>
					<?php if (!empty($user['Referrer']['id'])): ?>
						</li>
					</ul>
					<?php endif; ?>				
			<?php if (!empty($grandparent)): ?>
				</li>
			</ul>
			<?php endif; ?>
		</div>
		<div class="form-actions">
			<p id="user-select-info"></p>
			<?php echo $this->Form->submit('Hellban', array('name' => 'User[btn_hellban]', 'class' => 'btn btn-primary')); ?>&nbsp;<?php echo $this->Form->submit('Remove Hellban', array('name' => 'User[btn_remove_hellban]', 'class' => 'btn btn-success')); ?>
		</div>
	</div>
</div>
<?php echo $this->Form->end(null); ?>
<?php echo $this->Element('modal_user_quickprofile'); ?>
<?php echo $this->Element('modal_user_score'); ?>
<script type="text/javascript">
	$(document).ready(function() {
		$('.tt').tooltip();
	});
	
	$('.user-referral-tree').on('change', function() {
		var total = $('input.user-referral-tree:checkbox:checked').length;
		$('#user-select-info').html(total + ' users have been selected.');
	});

	var check_all = function(node, hellbanned) {
		var i = 0;
		var limit = 500;
		
		if (hellbanned) {
			$(node).closest('form').find('.user-referral-tree.unhellbanned-user').prop('checked', false);
			$('.user-referral-tree.hellbanned-user').each(function() {
				if (i == limit) {
					$('#user-select-info').html(i + ' <b>hellbanned</b> users have been selected.');
					return false;
				}
				
				$(this).prop('checked', true);
				i++;
			});
			$('#user-select-info').html(i + ' <b>hellbanned</b> users have been selected.');
		}
		else {
			$(node).closest('form').find('.user-referral-tree.hellbanned-user').prop('checked', false);
			$('.user-referral-tree.unhellbanned-user').each(function() {
				if (i == limit) {
					$('#user-select-info').html(i + ' <b>unhellbanned</b> users have been selected.');
					return false;
				}
				
				$(this).prop('checked', true);
				i++;
			});
			$('#user-select-info').html(i + ' <b>unhellbanned</b> users have been selected.');
		}
		
		return false;
	}; 
</script>