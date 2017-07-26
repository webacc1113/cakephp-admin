<h4>SurveyQualifications/BySurveyNumberForOfferwall</h4>
<?php if (!empty($offerwall_qualifications)): ?>
	<dl>
	<?php foreach($offerwall_qualifications as $qualification): ?>
		<dt><?php echo $qualification['FedQuestion']['question'];?></dt>
		<?php if (!empty($qualification['FedAnswer'])): ?>
			<dd>
			<?php foreach($qualification['FedAnswer'] as $answer): ?>
				<?php echo $answer['answer']; ?><br />
			<?php endforeach; ?>
			</dd>
		<?php endif; ?>
	<?php endforeach; ?>
	</dl>
<?php endif; ?>

<h4>SurveyQuotas/BySurveyNumber</h4>
<?php if (!empty($quotas)): ?>
	<?php foreach ($quotas as $key => $quota): ?>
		<?php if (!empty($quota)): ?>
			<h5>Qualification for Quota id: <?php echo $key; ?></h5>
			<dl>
				<?php foreach ($quota as $qualification): ?>
					<dt><?php echo $qualification['FedQuestion']['question']; ?></dt>
					<?php if (!empty($qualification['FedAnswer'])): ?>
						<dd>
							<?php foreach ($qualification['FedAnswer'] as $answer): ?>
								<?php echo $answer['answer']; ?><br />
							<?php endforeach; ?>
						</dd>
					<?php endif; ?>
				<?php endforeach; ?>
			</dl>
		<?php endif; ?>
	<?php endforeach; ?>
<?php endif; ?>
