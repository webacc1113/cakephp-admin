<?php $user_levels = unserialize(USER_LEVELS); ?>
<?php if (isset($user) && $user): ?>
	<?php
		$rows = array();
		if (!empty($user['User']['fullname']) || !empty($user['User']['last_touched'])) {
			$rows[0] = array(
				$user['User']['fullname'],
				!empty($user['User']['last_touched'])  ? $this->Time->format($user['User']['last_touched'], Utils::dateFormatToStrftime('F jS, Y h:i:A'), false, $timezone) : null
			);
		}
		if (!is_null($user['QueryProfile']['country']) || !is_null($user['QueryProfile']['state'])) {
			$location = array();
			if (!is_null($user['QueryProfile']['state'])) {
				$location[] = $user['QueryProfile']['state'];
			}
			if (!is_null($user['QueryProfile']['country'])) {
				$location[] = $user['QueryProfile']['country'];
			}
			$rows[1] = array(
				implode(', ', $location)		
			);
			 if ($user_analysis) {
				$rows[1][] = $user_analysis['UserAnalysis']['score'];
			}
		}
		$user_level = MintVineUser::user_level($user['User']['last_touched']);
		$rows[2] = array(
			$user_level ? $user_levels[$user_level] : '',
			empty($user['User']['poll_streak']) ? '0 day poll streak': $this->App->number($user['User']['poll_streak']).' day poll streak'
		);
		$rows[3] = array(
			$this->App->number($user['User']['balance'])
		);
		if (isset($active_payment_method['PaymentMethod'])) {
			$payment_method = $active_payment_method['PaymentMethod']['payment_method']; 
			if ($active_payment_method && $active_payment_method['PaymentMethod']['payment_method'] == 'paypal') {
				$payment_method .= ': '.$active_payment_method['PaymentMethod']['value'];
			}
			$rows[3][] = $payment_method;
		}
		$rows[4] = array(
			$this->Html->link('History', 'https://cp.mintvine.com/panelist_histories/user?user_id='.$user['User']['id'], array('target' => '_blank')),
			$this->Html->link('Transactions', 'https://cp.mintvine.com/transactions?user=%23'.$user['User']['id'], array('target' => '_blank'))
		);
		$rows[5] = array(
			$this->Html->link('Pending Transactions', 'https://cp.mintvine.com/transactions?type=&paid=0&user=%23'.$user['User']['id'], array('target' => '_blank')),
			$this->Html->link('Login', 'https://cp.mintvine.com/users/login_as_user/'.$user['User']['id'], array('target' => '_blank'))
		);
		foreach ($rows as $key => $row) {
			$write = array();
			foreach ($row as $k => $v) {
				if (empty($v)) {
					unset($row[$k]);
				}
			}
			$rows[$key] = implode(' | ', $row); 
		}
	?>
	<p><?php echo implode('<br/>', $rows); ?></p>
	<hr />
	<?php if (!empty($user['User']['deleted_on'])): ?>
	<p style="color:#f12f2f;">
		Account Deleted: <?php echo $this->Time->format($user['User']['deleted_on'], Utils::dateFormatToStrftime('F jS, Y'), false, $timezone); ?>
	</p>
	<hr />
	<?php endif; ?>
	<?php if ($user['User']['hellbanned']): ?>
	<p>
		HB: <?php echo $this->Time->format($user['User']['hellbanned_on'], Utils::dateFormatToStrftime('F jS, Y'), false, $timezone); ?>
		<?php if (!empty($user['User']['hellban_reason'])): ?>
			<br/><?php echo $user['User']['hellban_reason']; ?>
		<?php endif; ?>
	</p>
	<hr />
	<?php endif; ?>
	<p>Survey History<br/>
		<?php if ($survey_user_visits): ?>
			<?php foreach ($survey_user_visits as $survey_user_visit) : ?>
				<?php 
					echo $this->Html->link(
						'#'.$survey_user_visit['SurveyUserVisit']['survey_id'], 
						'https://cp.mintvine.com/surveys/dashboard/'.$survey_user_visit['SurveyUserVisit']['survey_id'],
						array('target' => '_blank')
					); 
				?> | <?php echo $survey_user_visit['SurveyUserVisit']['created']; ?> | <?php
					$survey_statuses = unserialize(SURVEY_STATUSES); 
					echo strtoupper($survey_statuses[$survey_user_visit['SurveyUserVisit']['status']]); 
				?><br/>
			<?php endforeach; ?>
		<?php endif; ?>
	</p>
	<hr />
	<p><?php echo $this->Time->format($user['User']['created'], Utils::dateFormatToStrftime('F jS, Y'), false, $timezone); ?></p>
<?php else: ?>
	<p>No user found <?php echo (isset($this->request->query['guid'])) ? 'with ID '. $this->request->query['guid'] : ''; ?> 
		<?php if (isset($this->request->query['email'])): ?>
			or <?php echo $this->request->query['email']; ?>
		<?php endif; ?></p>
<?php endif; ?>