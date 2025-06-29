<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Carbon_black extends CI_Controller
{
	public function __construct()
	{
		parent::__construct();
		
		if (!$this->tank_auth->is_logged_in()) 
		{	
			if ($this->input->is_ajax_request()) 
			{
				redirect('/auth/ajax_logged_out_response');
			} 
			else 
			{
				redirect('/auth/login');
			}
		}
		$this->utility->restricted_access();
		$this->load->model('account/account_model', 'account');
		$this->load->library('carbon_black_api');
	}

	function _remap($method, $args)
	{ 
		if (method_exists($this, $method))
		{
			$this->$method($args);
		}
		else
		{
			$this->index($method, $args);
		}
	}

	public function index($method, $args = array())
	{
		$asset = client_redis_info_by_code();

		# Page Data	
		$nav['client_name']         = $asset['client'];
		$nav['client_code']         = $asset['code'];
		
		$data['client_code']        = $asset['code'];
		$data['sub_navigation']     = $this->load->view('customer-management/navigation', $nav, TRUE);	
		$data['carbon_black_info']  = $this->carbon_black_api->redis_info($asset['seed_name']);
		$data['show_activation']    = FALSE;
		$data['api_tested']         = FALSE;
		$data['request_was_sent']   = FALSE;
		$data['api_enabled']        = FALSE;
		$data['action']             = 'create';

		if (!is_null($data['carbon_black_info']))
		{
			$data['action'] = 'modify';
			
			if (intval($data['carbon_black_info']['tested']))
			{
				$data['show_activation']    = TRUE;
				$data['api_tested']         = TRUE;
				
				if (intval($data['carbon_black_info']['request_sent']))
				{
					$data['request_was_sent'] = TRUE;
				}
				
				if (intval($asset['endpoint_enabled']))
				{
					if ($asset['endpoint_provider'] === 'carbon_black')
					{
						$data['api_enabled'] = TRUE;
					}
				}
			}
		}

		# Page Views
		$this->load->view('assets/header');	
		$this->load->view('customer-management/carbon_black/start', $data);	
		$this->load->view('assets/footer');
	}

	public function create()
	{
		$asset = client_redis_info_by_code();

		if ($this->input->method(TRUE) === 'POST')
		{
			$this->form_validation->set_rules('hostname', 'Hostname', 'trim|required|callback_host_ip_check');
			$this->form_validation->set_rules('org_key', 'Organization Key', 'trim|required');
			$this->form_validation->set_rules('api_secret_key', 'API Secret Key', 'trim|required');
			$this->form_validation->set_rules('api_id', 'API ID', 'trim|required');

			if ($this->form_validation->run()) 
			{
				$redis_data = array(
					'hostname'          => $this->input->post('hostname'),
					'org_key'           => $this->input->post('org_key'),
					'api_secret_key'    => $this->input->post('api_secret_key'),
					'api_id'            => $this->input->post('api_id')
				);

				if ($this->carbon_black_api->create_client_redis_key($asset['seed_name'], $redis_data))
				{
					# Write To Logs
					$log_message = '[Carbon Black Endpoint API Created] user: '.$this->session->userdata('username').' | for client: '.$asset['client'];
					$this->utility->write_log_entry('info', $log_message);
					
					# Success
					$this->session->set_userdata('my_flash_message_type', 'success');
					$this->session->set_userdata('my_flash_message', '<p>Carbon Black Endpoint API settings were successfully created.</p>');

					redirect('/customer-management/endpoint/carbon-black/'.$asset['code']);
				}
				else
				{
					# Something went wrong
					$this->session->set_userdata('my_flash_message_type', 'error');
					$this->session->set_userdata('my_flash_message', '<p>Something went wrong. Please try again.</p>');
				}
			}
			else
			{
				if (validation_errors()) 
				{
					$this->session->set_userdata('my_flash_message_type', 'error');
					$this->session->set_userdata('my_flash_message', validation_errors());
				}
			}
		}
		
		# Page Data
		$data['client_code'] = $asset['code'];
		
		# Page Views
		$this->load->view('assets/header');
		$this->load->view('customer-management/carbon_black/create', $data);
		$this->load->view('assets/footer');
	}

	public function modify()
	{
		$asset = client_redis_info_by_code();

		if ($this->input->method(TRUE) === 'POST')
		{
			$this->form_validation->set_rules('hostname', 'Hostname', 'trim|required|callback_host_ip_check');
			$this->form_validation->set_rules('org_key', 'Organization Key', 'trim|required');
			$this->form_validation->set_rules('api_secret_key', 'API Secret Key', 'trim|required');
			$this->form_validation->set_rules('api_id', 'API ID', 'trim|required');

			if ($this->form_validation->run())
			{
				$redis_data = array(
					'hostname'          => $this->input->post('hostname'),
					'org_key'           => $this->input->post('org_key'),
					'api_secret_key'    => $this->input->post('api_secret_key'),
					'api_id'            => $this->input->post('api_id')
				);

				if ($this->carbon_black_api->create_client_redis_key($asset['seed_name'], $redis_data))
				{
					# Write To Logs
					$log_message = '[Carbon Black Endpoint API Modified] user: '.$this->session->userdata('username').' | for client: '.$asset['client'];
					$this->utility->write_log_entry('info', $log_message);
					
					# Success
					$this->session->set_userdata('my_flash_message_type', 'success');
					$this->session->set_userdata('my_flash_message', '<p>Carbon Black Endpoint API settings were successfully updated.</p>');

					redirect('/customer-management/endpoint/carbon-black/'.$asset['code']);
				}
				else
				{
					# Something went wrong
					$this->session->set_userdata('my_flash_message_type', 'error');
					$this->session->set_userdata('my_flash_message', '<p>Something went wrong. Please try again.</p>');
				}
			}
			else
			{
				if (validation_errors()) 
				{
					$this->session->set_userdata('my_flash_message_type', 'error');
					$this->session->set_userdata('my_flash_message', validation_errors());
				}
			}
		}
		
		# Page Data
		$data['client_code']        = $asset['code'];
		$data['carbon_black_info']  = $this->carbon_black_api->redis_info($asset['seed_name']);

		# Page Views
		$this->load->view('assets/header');
		$this->load->view('customer-management/carbon_black/modify', $data);
		$this->load->view('assets/footer');
	}

	public function api_test()
	{
		$asset = client_redis_info_by_code();

		$search_params = array(
			'type'  => 'list',
			'term'  => NULL
		);

		$response = $this->carbon_black_api->search_devices($asset['seed_name'], $search_params);

		if ($response['success'])
		{
			$devices        = [];
			$device_count   = count($response['response']['results']); 
			$num_found      = $response['response']['num_found'];

			foreach ($response['response']['results'] as $device)
			{
				$devices[] = array(
					'client_code'   => $asset['code'],
					'id'            => base64_encode_url($device['id']),
					'name'          => $device['name'],
					'platform'      => ucfirst(strtolower($device['os'])),
					'last_ip'       => $device['last_internal_ip_address'],
					'mac_address'   => $device['mac_address'],
					'status'        => ucfirst(strtolower($device['status']))
				);
			}

			$return_array = array(
				'success'       => TRUE,
				'response'      => $response['response'],
				'machine_count' => $device_count,
				'machine_data'  => $devices,
				'machine_total' => $num_found
			);
		}
		else
		{
			$return_array = array(
				'success'   => FALSE,
				'response'  => $response['response']
			);
		}

		echo json_encode($return_array);
	}

	public function attempt_quarantine()
	{
		$asset      = client_redis_info_by_code();
		$device_id  = base64_decode_url($this->uri->segment(5));

		$response = $this->carbon_black_api->quarantine($asset['seed_name'], $device_id, TRUE);

		if ($response['success'])
		{
			$response2 = $this->carbon_black_api->specific_device_info($asset['seed_name'], $device_id);

			if ($response2['success'])
			{
				$return_array = array(
					'success'   => TRUE,
					'status'    => $response2['response']['quarantined'] ? 'Succeeded' : 'Pending',
					'action_id' => base64_encode_url($response2['response']['id'])
				);
			}
			else
			{
				$return_array = array(
					'success'   => FALSE,
					'response'  => $response2['response']
				);
			}
		}
		else
		{
			$return_array = array(
				'success'   => FALSE,
				'response'  => $response['response']
			);
		}

		echo json_encode($return_array);
	}

	public function attempt_release()
	{
		$asset      = client_redis_info_by_code();
		$device_id  = base64_decode_url($this->uri->segment(5));

		$response = $this->carbon_black_api->quarantine($asset['seed_name'], $device_id, FALSE);

		if ($response['success'])
		{
			$this->carbon_black_api->redis_info($asset['seed_name'], NULL, 'SET', array('tested' => '1'));

			$response2 = $this->carbon_black_api->specific_device_info($asset['seed_name'], $device_id);

			if ($response2['success'])
			{
				$return_array = array(
					'success'   => TRUE,
					'status'    => $response2['response']['quarantined'] ? 'Pending' : 'Succeeded',
					'action_id' => base64_encode_url($response2['response']['id'])
				);
			}
			else
			{
				$return_array = array(
					'success'   => FALSE,
					'response'  => $response2['response']
				);
			}
		}
		else
		{
			$return_array = array(
				'success'   => FALSE,
				'response'  => $response['response']
			);
		}

		echo json_encode($return_array);
	}

	public function check_machine_action()
	{
		$asset      = client_redis_info_by_code();
		$device_id  = base64_decode_url($this->uri->segment(5));
		$action     = $this->uri->segment(6);  // quarantine, release

		$response = $this->carbon_black_api->specific_device_info($asset['seed_name'], $device_id);

		if ($response['success'])
		{
			switch ($action)
			{
				case 'quarantine':
					$status = $response['response']['quarantined'] ? 'Succeeded' : 'Pending';
					break;
				case 'release':
					$status = $response['response']['quarantined'] ? 'Pending' : 'Succeeded';
					break;
			}

			$return_array = array(
				'success'   => TRUE,
				'status'    => $status,
				'action_id' => $response['response']['id']
			);
		}
		else
		{
			$return_array = array(
				'success'   => FALSE,
				'response'  => $response['response']
			);
		}

		echo json_encode($return_array);
	}

	public function activate()
	{
		$asset = client_redis_info_by_code();
		
		$carbon_black_info              = $this->carbon_black_api->redis_info($asset['seed_name']);
		$data['authorized_to_modify']   = $this->account->get_authorized_to_modify($asset['id']);
		$data['client_code']            = $asset['code'];
		$data['client_title']           = $asset['client'];
		$data['request_user']           = $carbon_black_info['request_user'] ?? NULL;
		$data['terms_agreed']           = intval($carbon_black_info['terms_agreed']);

		$this->load->view('customer-management/carbon_black/activate', $data);
	}

	public function do_activate()
	{
		$asset = client_redis_info_by_code();
		
		$this->form_validation->set_rules('requesting_user', 'Requesting Contact', 'trim|required');
		$this->form_validation->set_rules('api-terms-of-agreement', 'api-terms-of-agreement', 'trim|required');

		if ($this->form_validation->run()) 
		{
			$requested_by 	= $this->input->post('requesting_user');			
			$requested_user = $this->account->get_user_by_code($requested_by);
			$requested_name = $requested_user->first_name.' '.$requested_user->last_name;
			
			if ($this->carbon_black_api->change_api_activation_status($asset['seed_name'], $requested_by, TRUE))
			{
				$this->account->send_api_activation_notification($asset['id'], 'carbon_black', $requested_name);
				
				# Write To Logs
				$log_message = '[Carbon Black Endpoint API Enabled] user: '.$this->session->userdata('username').', has enabled api for customer: '.$asset['client'].', per the request of '.$requested_name;
				$this->utility->write_log_entry('info', $log_message);
				
				# Set Success Alert Response
				$this->session->set_userdata('my_flash_message_type', 'success');
				$this->session->set_userdata('my_flash_message', '<p>The Carbon Black Endpoint API for: <strong>'.$asset['client'].'</strong>, has been successfully enabled.</p>');

				$response = array(
					'success'   => true,
					'goto_url'  => '/customer-management/endpoint/carbon-black/'.$asset['code']
				);
				echo json_encode($response);
			}
			else
			{
				# Set Error
				$response = array(
					'success'   => false,
					'message'   => '<p>Oops, something went wrong.</p>'
				);
				echo json_encode($response);
			}
		}
		else
		{
			if (validation_errors()) 
			{
				# Set Error
				$response = array(
					'success'   => false,
					'message'   => validation_errors()
				);
				echo json_encode($response);
			}
		}
	}

	public function disable()
	{
		$asset = client_redis_info_by_code();

		$data['authorized_to_modify']   = $this->account->get_authorized_to_modify($asset['id']);
		$data['client_code']            = $asset['code'];
		$data['client_title']           = $asset['client'];
		
		$this->load->view('customer-management/carbon_black/disable', $data);
	}

	public function do_disable()
	{
		$asset = client_redis_info_by_code();
		
		$this->form_validation->set_rules('requesting_user', 'Requesting Contact', 'trim|required');
		$this->form_validation->set_rules('api-terms-of-agreement', 'api-terms-of-agreement', 'trim|required');

		if ($this->form_validation->run()) 
		{
			$requested_by 	= $this->input->post('requesting_user');			
			$requested_user = $this->account->get_user_by_code($requested_by);
			$requested_name = $requested_user->first_name.' '.$requested_user->last_name;
			
			if ($this->carbon_black_api->change_api_activation_status($asset['seed_name'], $requested_by, FALSE))
			{
				$this->account->send_api_disabled_notification($asset['id'], 'carbon_black', $requested_name);
				
				# Write To Logs
				$log_message = '[Carbon Black Endpoint API Disabled] user: '.$this->session->userdata('username').', has disabled api for customer: '.$asset['client'].', per the request of '.$requested_name;
				$this->utility->write_log_entry('info', $log_message);
				
				# Set Success Alert Response
				$this->session->set_userdata('my_flash_message_type', 'success');
				$this->session->set_userdata('my_flash_message', '<p>The Carbon Black Endpoint API for: <strong>'.$asset['client'].'</strong>, has been successfully disabled.</p>');

				$response = array(
					'success'   => true,
					'goto_url'  => '/customer-management/endpoint/carbon-black/'.$asset['code']
				);
				echo json_encode($response);
			}
			else
			{
				# Set Error
				$response = array(
					'success'   => false,
					'message'   => '<p>Oops, something went wrong.</p>'
				);
				echo json_encode($response);
			}
		}
		else
		{
			if (validation_errors()) 
			{
				# Set Error
				$response = array(
					'success'   => false,
					'message'   => validation_errors()
				);
				echo json_encode($response);
			}
		}
	}

	public function host_ip_check($value)
	{
		if (strlen($value) === 0)
		{
			$this->form_validation->set_message('host_ip_check', 'The {field} field is required.');
			return FALSE;
		}

		$dot_count      = substr_count($value, '.');
		$colon_count    = substr_count($value, ':');

		if ($dot_count === 0 && $colon_count === 0)
		{
			if (strcmp($value, 'localhost') !== 0)
			{
				$this->form_validation->set_message('host_ip_check', 'The {field} field must contain a valid host or IP address.');
				return FALSE;
			}
		}
		else if ($colon_count > 0)
		{
			$rv = preg_match('/^\[([^\]]+)\]$/', $value, $matches);

			if ($rv === 0 || $rv === FALSE)
			{
				$this->form_validation->set_message('host_ip_check', '{field} - IPv6 addresses must be written within [brackets].');
				return FALSE;
			}
			else
			{
				if (filter_var($matches[1], FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) === FALSE)
				{
					$this->form_validation->set_message('host_ip_check', '{field} - invalid IPv6 address format.');
					return FALSE;
				}
			}
		}
		else if ($dot_count > 0)
		{
			switch ($dot_count)
			{
				case 3:
					if (filter_var($value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== FALSE)
					{
						return TRUE;
					}
				default:
					$rv = preg_match('/^(?=.{1,255}$)(((?!-)[a-z0-9-]{1,63}(?<!-)\.){1,127}[a-z]{2,63})$/i', $value, $matches);

					if ($rv === 0 || $rv === FALSE)
					{
						$this->form_validation->set_message('host_ip_check', 'The {field} field must contain a valid host or IP address.');
						return FALSE;
					}
			}
		}

		return TRUE;
	}
}