<div class="container">
	<?php
	echo $this->Form->create('Invoice', array(
		'inputDefaults' => array(
			'div' => 'form-group',
			'wrapInput' => false,
			'class' => 'form-control'
	)));
	?>
	<div class="box invoice">
		<div class="box-header">
			<span class="title">Edit Invoice</span>
		</div>
		<div class="box-content">
			<div class="padded">
				<div class="row-fluid">
					<div class="span3"><?php
						echo $this->Form->input('name', array(
							'label' => 'To:',
						));
						?></div>
					<div class="span3">
						<?php echo $this->Form->input('email'); ?>
					</div>
					
					<div class="span3"><?php
						echo $this->Form->input('project_reference', array(
							'label' => 'Project Reference',
						));
						?></div>
					<div class="span3">
						<?php
						echo $this->Form->input('number', array(
							'label' => 'Invoice Number',
						));
						?>
					</div>
				</div>
				<div class="row-fluid">
					<div class="span3"><?php
						echo $this->Form->input('address_line1', array(
							'value' => !empty($invoice['Invoice']['address_line1']) ? $invoice['Invoice']['address_line1'] : $invoice['Project']['Client']['address_line1']
						));
						?>
						<?php echo $this->Form->input('address_line2', array(
							'value' => !empty($invoice['Invoice']['address_line2']) ? $invoice['Invoice']['address_line2'] : $invoice['Project']['Client']['address_line2']
						)); ?></div>
					<div class="span3"><?php
						echo $this->Form->input('geo_country_id', array(
							'label' => 'Country',
							'options' => $geo_countries,
							'escape' => false,
							'selected' => !empty($invoice['Invoice']['geo_country_id']) ? $invoice['Invoice']['geo_country_id'] : $invoice['Project']['Client']['geo_country_id']
						));
						?>
						<?php
						echo $this->Form->input('geo_state_id', array(
							'label' => 'State',
							'options' => $geo_states,
							'escape' => false,
							'selected' => !empty($invoice['Invoice']['geo_state_id']) ? $invoice['Invoice']['geo_state_id'] : $invoice['Project']['Client']['geo_state_id']
						));
						?>
						</div>
					<div class="span3">
						<?php
						echo $this->Form->input('city', array(
							'label' => 'City',
							'value' => !empty($invoice['Invoice']['city']) ? $invoice['Invoice']['city'] : $invoice['Project']['Client']['city']
						));
						?>
						<?php
						echo $this->Form->input('postal_code', array(
							'value' => !empty($invoice['Invoice']['postal_code']) ? $invoice['Invoice']['postal_code'] : $invoice['Project']['Client']['postal_code']
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
							'after' => '<small>Separate multiple emails with comma(,)</small>'
						));
						?>
					</div>
					<div class="span3 date">
						<?php
						echo $this->Form->input('date', array(
							'label' => 'Date',
						));
						?>
					</div> 
					<div class="span3"><?php
						echo $this->Form->input('client_project_reference', array(
							'label' => 'Client Project Reference',
						));
						?>
						<?php echo $this->Form->input('terms', array('after' =>  '<span> Days</span>')); ?>
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
							<?php if (isset($invoice['InvoiceRow']) && !empty($invoice['InvoiceRow'])): ?>
								<?php foreach ($invoice['InvoiceRow'] as $invoice_row): ?>
									<tr>
										<td class="qty">
											<?php
											echo $this->Form->input('quantity.' . $invoice_row['id'], array(
												'label' => false,
												'type' => 'text',
												'class' => 'quantity',
												'value' => $invoice_row['quantity'],
												'onchange' => 'return MintVineInvoice.ChangeInvoiceRow(this);'
											));
											?>
										</td>
										<td class="description">
											<?php
											echo $this->Form->input('description.' . $invoice_row['id'], array(
												'label' => false,
												'type' => 'text',
												'value' => $invoice_row['description'],
											));
											?>
										</td>
										<td class="unit-price">
											<?php
											echo $this->Form->input('unit_price.' . $invoice_row['id'], array(
												'label' => false,
												'before' => $currencies[$this->data['Invoice']['currency']],
												'class' => 'dollar unit_price',
												'type' => 'text',
												'value' => $invoice_row['unit_price'],
												'onchange' => 'return MintVineInvoice.ChangeInvoiceRow(this);'
											));
											?>
										</td>
										<td class="line-total">
											<?php
											echo $this->Form->input('line_total.' . $invoice_row['id'], array(
												'label' => false,
												'before' => $currencies[$this->data['Invoice']['currency']],
												'class' => 'dollar line_total',
												'value' => $invoice_row['quantity'] * $invoice_row['unit_price'],
												'data-value' => $invoice_row['quantity'] * $invoice_row['unit_price'],
											));
											?>
										</td>
									</tr>
								<?php endforeach; ?>
							<?php endif; ?>
							<tr class="new">
								<td class="qty">
									<?php
									echo $this->Form->input('new.quantity.', array(
										'label' => false,
										'type' => 'text',
										'class' => 'quantity',
										'required' => false,
										'onchange' => 'return MintVineInvoice.ChangeInvoiceRow(this);'
									));
									?>
								</td>
								<td class="description">
									<?php
									echo $this->Form->input('new.description.', array(
										'label' => false,
										'type' => 'text',
										'required' => false,
									));
									?>
								</td>
								<td class="unit-price">
									<?php
									echo $this->Form->input('new.unit_price.', array(
										'label' => false,
										'before' => $currencies[$this->data['Invoice']['currency']],
										'class' => 'dollar unit_price',
										'type' => 'text',
										'required' => false,
										'onchange' => 'return MintVineInvoice.ChangeInvoiceRow(this);'
									));
									?>
								</td>
								<td class="line-total">
									<?php
									echo $this->Form->input('new.line_total.', array(
										'label' => false,
										'before' => $currencies[$this->data['Invoice']['currency']],
										'data-value' => '0',
										'class' => 'dollar line_total',
										'required' => false
									));
									?>
								</td>
							</tr>
							<tr>
								<td colspan="2"><?php echo $this->Html->link('Add new row', '#', array('onclick' => 'return MintVineInvoice.ShowInvoiceRow(this);')); ?></td>
								<td><strong>Total</strong> 
								<td><?php
									echo $this->Form->input('subtotal', array(
										'type' => 'text',
										'before' => $currencies[$this->data['Invoice']['currency']],
										'label' => false,
										'class' => 'dollar',
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