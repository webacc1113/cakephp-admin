<div class="container">
	<?php echo $this->Form->create('Invoice', array(
		'inputDefaults' => array(
			'div' => 'form-group',
			'wrapInput' => false,
			'class' => 'form-control'
		))); 
	?>
	<?php echo $this->Form->input('project_id', array('type' => 'hidden', 'value' => $survey['Project']['id'] )); ?>
	<div class="box invoice">
		<div class="box-header">
			<span class="title">Generate Invoice</span>
		</div>
		<div class="box-content">
			<div class="padded">
				<div class="row-fluid">
					<div class="span3"><?php
						echo $this->Form->input('name', array(
							'label' => 'To:',
							'value' => (isset($this->data['Invoice']['name'])) ? $this->data['Invoice']['name'] : $survey['Client']['billing_name'],
						));
					?></div>
					<div class="span3">
						<?php echo $this->Form->input('email', array(
							'value' => (isset($this->data['Invoice']['email'])) ? $this->data['Invoice']['email'] : !empty($survey['Client']['Contact']['email']) ? $survey['Client']['Contact']['email'] : !empty($survey['Client']['billing_email']) ? $survey['Client']['billing_email'] : '',
							));	?>
					</div>
					<div class="span3"><?php
						echo $this->Form->input('project_reference', array(
							'label' => 'Project Reference',
							'value' => (isset($this->data['Invoice']['project_reference'])) ? $this->data['Invoice']['project_reference'] : $survey['Project']['id'],
						));
						?></div>
					<div class="span3">
						<?php
						echo $this->Form->input('number', array(
							'label' => 'Invoice Number',
							'value' => (isset($this->data['Invoice']['number'])) ? $this->data['Invoice']['number'] : $survey['Project']['id'],
						));
						?>
					</div>
				</div>
				<div class="row-fluid">
					<div class="span3"><?php
						echo $this->Form->input('address_line1', array(
							'value' => $survey['Client']['address_line1']
						));
						?>
						<?php echo $this->Form->input('address_line2', array(
							'value' => $survey['Client']['address_line2']
						)); ?></div>
					<div class="span3"><?php
						echo $this->Form->input('geo_country_id', array(
							'label' => 'Country',
							'empty' => 'Select:',
							'options' => $geo_countries,
							'escape' => false,
							'selected' => $survey['Client']['geo_country_id']
						));
						?>
						<?php
						echo $this->Form->input('geo_state_id', array(
							'label' => 'State',
							'options' => $geo_states,
							'escape' => false,
							'selected' => $survey['Client']['geo_state_id']
						));
						?>
						</div>
					<div class="span3">
						<?php
						echo $this->Form->input('city', array(
							'label' => 'City',
							'default' => $survey['Client']['city']
						));
						?>
						<?php
						echo $this->Form->input('postal_code', array(
							'default' => $survey['Client']['postal_code']
						));
						?>
					</div>
				</div>
				<div class="row-fluid slide-down">
					<div class="span3">
						<?php
						echo $this->Form->input('cc', array(
							'label' => 'CC',
							'type' => "textarea",
							'rows' => 3,
							'after' => '<small>Separate multiple emails with comma(,)</small>',
						));
						?>
					</div>
					<div class="span3 date">
						<?php
						echo $this->Form->input('date', array(
							'label' => 'Date',
							'type' => 'date',
							'selected' => isset($this->data['Invoice']['date']) ? $this->data['Invoice']['date'] : Utils::change_tz_from_utc(date(DB_DATETIME), DB_DATETIME),
						));
						?>
					</div>
					<div class="span3">
						<?php echo $this->Form->input('client_project_reference', array(
							'label' => 'Client Project Reference',
							'value' => (isset($this->data['Invoice']['client_project_reference'])) ? $this->data['Invoice']['client_project_reference'] : $survey['Project']['prj_name'],
						)); ?>
						<?php
						echo $this->Form->input('terms', array(
							'label' => 'Terms',
							'after' =>  '<span> Days</span>',
							'value' => (isset($this->data['Invoice']['terms'])) ? $this->data['Invoice']['terms'] : $survey['Client']['net'],
						));
						?>
					</div>
				</div>
				<div class="row-fluid">
					<div class="span3">
						<?php
						$currencies = unserialize(CURRENCY);
						echo $this->Form->input('currency', array(
							'options' => $currencies
						));
						?>
					</div>
				</div>
				
				<div class="row-fluid">
					<div class="span12">
						<p>&nbsp;</p>
						<p>To remove a row, set Qty to 0</p>
						<table class="table invoice">
							<tr>
								<th class="qty">Qty</th>
								<th class="description">Description</th>
								<th class="unit-price">Unit Price</th>
								<th class="line-total">Line Total</th>
							</tr>
							<?php if (isset($invoice_rows)): ?>
								<?php $i = 1; ?>
								<?php foreach ($invoice_rows as $client_rate => $qty): ?>
									<?php if ($i == 1): ?>
										<tr>
											<td class="qty"><?php
												echo $this->Form->input('InvoiceRow.quantity', array(
													'label' => false,
													'type' => 'text',
													'value' => $qty,
													'class' => 'quantity',
													'onchange' => 'return MintVineInvoice.ChangeInvoiceRow(this);'
												));
											?></td>
											<td class="description"><?php
												echo $this->Form->input('InvoiceRow.description', array(
													'label' => false,
													'type' => 'text',
													'value' => 'Completed Surveys',
												));
											?></td>
											<td class="unit-price"><?php
												echo $this->Form->input('InvoiceRow.unit_price', array(
													'label' => false,
													'before' => '$',
													'class' => 'dollar unit_price',
													'type' => 'text',
													'value' => number_format($client_rate, 2),
													'onchange' => 'return MintVineInvoice.ChangeInvoiceRow(this);'
												));
											?></td>
											<td class="line-total"><?php
												echo $this->Form->input('InvoiceRow.line_total', array(
													'label' => false,
													'before' => '$',
													'class' => 'dollar line_total',
													'value' => number_format($client_rate * $qty, 2),
													'data-value' => $client_rate * $qty,
												));
											?></td>
										</tr>
										<?php else: ?>
											<tr>
												<td class="qty"><?php
													echo $this->Form->input('new.quantity.', array(
														'label' => false,
														'type' => 'text',
														'class' => 'quantity',
														'value' => $qty,
														'required' => false,
														'onchange' => 'return MintVineInvoice.ChangeInvoiceRow(this);'
													));
													?></td>
												<td class="description"><?php
													echo $this->Form->input('new.description.', array(
														'label' => false,
														'type' => 'text',
														'value' => 'Completed Surveys',
														'required' => false,
													));
													?></td>
												<td class="unit-price"><?php
													echo $this->Form->input('new.unit_price.', array(
														'label' => false,
														'before' => '$',
														'class' => 'dollar unit_price',
														'type' => 'text',
														'value' => number_format($client_rate, 2),
														'required' => false,
														'onchange' => 'return MintVineInvoice.ChangeInvoiceRow(this);'
													));
													?></td>
												<td class="line-total"><?php
													echo $this->Form->input('new.line_total.', array(
														'label' => false,
														'before' => '$',
														'value' => number_format($client_rate * $qty, 2),
														'data-value' => $client_rate * $qty,
														'class' => 'dollar line_total',
														'required' => false
													));
													?></td>
											</tr>
										<?php endif; ?>
									<?php $i++; ?>
								<?php endforeach; ?>
							<?php else: ?>
								<tr>
									<td class="qty"><?php
										echo $this->Form->input('InvoiceRow.quantity', array(
											'label' => false,
											'type' => 'text',
											'value' => $count,
											'class' => 'quantity',
											'onchange' => 'return MintVineInvoice.ChangeInvoiceRow(this);'
										));
										?></td>
									<td class="description"><?php
										echo $this->Form->input('InvoiceRow.description', array(
											'label' => false,
											'type' => 'text',
											'value' => 'Completed Surveys',
										));
										?></td>
									<td class="unit-price"><?php
										echo $this->Form->input('InvoiceRow.unit_price', array(
											'label' => false,
											'before' => '$',
											'class' => 'dollar unit_price',
											'type' => 'text',
											'value' => number_format($survey['Project']['client_rate'], 2),
											'onchange' => 'return MintVineInvoice.ChangeInvoiceRow(this);'
										));
										?></td>
									<td class="line-total"><?php
										echo $this->Form->input('InvoiceRow.line_total', array(
											'label' => false,
											'before' => '$',
											'class' => 'dollar line_total',
											'value' => number_format($total, 2),
											'data-value' => $total,
										));
										?>
									</td>
								</tr>
							<?php endif; ?>
							<tr class="new">
								<td class="qty"><?php
									echo $this->Form->input('new.quantity.', array(
										'label' => false,
										'type' => 'text',
										'class' => 'quantity',
										'required' => false,
										'onchange' => 'return MintVineInvoice.ChangeInvoiceRow(this);'
									));
								?></td>
								<td class="description"><?php
									echo $this->Form->input('new.description.', array(
										'label' => false,
										'type' => 'text',
										'required' => false,
										));
								?></td>
								<td class="unit-price"><?php
									echo $this->Form->input('new.unit_price.', array(
										'label' => false,
										'before' => '$',
										'class' => 'dollar unit_price',
										'type' => 'text',
										'required' => false,
										'onchange' => 'return MintVineInvoice.ChangeInvoiceRow(this);'
									));
								?></td>
								<td class="line-total"><?php
									echo $this->Form->input('new.line_total.', array(
										'label' => false,
										'before' => '$',
										'data-value' => '0',
										'class' => 'dollar line_total',
										'required' => false
									));
								?></td>
							</tr>
							<tr>
								<td colspan="2"><?php 
									echo $this->Html->link('Add new row', '#', array('onclick' => 'return MintVineInvoice.ShowInvoiceRow(this);')); 
								?></td>
								<td><strong>Total</strong> 
								<td><?php
									echo $this->Form->input('subtotal', array(
										'type' => 'text',
										'value' => number_format($total, 2),
										'before' => '$',
										'label' => false,
										'class' => 'dollar',
										'data-value' => $total
									));
								?></td>
							</tr>
						</table>
					</div>
				</div>
			</div>
			<div class="form-actions">
				<?php echo $this->Form->submit('Save Invoice', array('class' => 'btn btn-primary')); ?>
			</div>
		</div>
	</div>
	<?php echo $this->Form->end(null); ?>
</div>