<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<div class="row">
	<div class="col-md-12">		
		<ol class="breadcrumb">
			<li><a href="/customer-management">Customer Management</a></li>
			<li><a href="/customer-management/customer/<?php echo $client_code; ?>">Customer Overview</a></li>
			<li><a href="/customer-management/endpoint/<?php echo $client_code; ?>">Endpoint Integration</a></li>
			<li><a href="/customer-management/endpoint/carbon-black/<?php echo $client_code; ?>">Carbon Black Endpoint API</a></li>
			<li class="active">Modify</li>
		</ol>	
	</div>
</div>
<div class="row">
	<div class="col-md-8">		
		<div class="panel panel-light">
			<div class="panel-heading">
				<div class="clearfix">
					<div class="pull-left">
						<h3>Carbon Black API Integration</h3>
						<h4>Modify</h4>
					</div>
					<div class="pull-right">
						<a href="/customer-management/endpoint/carbon-black/<?php echo $client_code; ?>" type="button" class="btn btn-default btn-sm">Cancel &amp; Return</a>
					</div>
				</div>
			</div>			
			<?php echo form_open($this->uri->uri_string(), array('autocomplete' => 'off', 'aria-autocomplete' => 'off')); ?>
				<div class="panel-body">
					<div class="row">
						<div class="col-md-12">							
							<div class="form-group<?php echo form_error('hostname') ? ' has-error':''; ?>">
								<label class="control-label" for="hostname">Hostname</label>
								<div class="input-group">
									<div class="input-group-addon">https://</div>
									<input type="text" class="form-control" id="hostname" name="hostname" placeholder="defense.conferdeploy.net" value="<?php echo set_value('hostname', $carbon_black_info['hostname']); ?>">
								</div>
							</div>
							<div class="form-group<?php echo form_error('org_key') ? ' has-error':''; ?>">
								<label class="control-label" for="org_key">Organization Key <span class="help-block inline small">( The org_key is located under <strong>Settings > General</strong>, and if CBC manages your identities, under <strong>Settings > API Access</strong>. )</span></label>
								<input type="text" class="form-control" id="org_key" name="org_key" placeholder="Enter Organization Key" value="<?php echo set_value('org_key', $carbon_black_info['org_key']); ?>">
							</div>
							<div class="form-group<?php echo form_error('api_secret_key') ? ' has-error':''; ?>">
								<label class="control-label" for="api_secret_key">API Secret Key</label>
								<input type="text" class="form-control" id="api_secret_key" name="api_secret_key" placeholder="Enter API Secret Key" value="<?php echo set_value('api_secret_key', $carbon_black_info['api_secret_key']); ?>">
							</div>
							<div class="form-group<?php echo form_error('api_id') ? ' has-error':''; ?>">
								<label class="control-label" for="api_id">API ID</label>
								<input type="text" class="form-control" id="api_id" name="api_id" placeholder="Enter API ID" value="<?php echo set_value('api_id', $carbon_black_info['api_id']); ?>">
							</div>
						</div>
					</div>
					<div class="row">
						<div class="col-md-12 text-right">
							<button type="submit" class="btn btn-success" data-loading-text="Updating...">Update</button>
						</div>						
					</div>
	  			</div>	  			
			<?php echo form_close(); ?>			
		</div>		
	</div>
</div>