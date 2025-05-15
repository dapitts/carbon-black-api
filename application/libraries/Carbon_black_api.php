<?php 
defined('BASEPATH') OR exit('No direct script access allowed');

class Carbon_black_api 
{
	private $ch;
	private $ci;
	private $client_redis_key;
	private $redis_host;
	private $redis_port;
	private $redis_timeout;  
	private $redis_password;
	private $max_rows;

	function __construct()
	{
		$this->ci =& get_instance();
		
		$this->client_redis_key = 'carbon_black_';
		$this->redis_host       = $this->ci->config->item('redis_host');
		$this->redis_port       = $this->ci->config->item('redis_port');
		$this->redis_password   = $this->ci->config->item('redis_password');
		$this->redis_timeout    = $this->ci->config->item('redis_timeout');
		$this->max_rows         = $this->ci->config->item('carbon_black_max_rows') ?? 2000;  // Max: 10k
	}

	public function redis_info($client, $field = NULL, $action = 'GET', $data = NULL)
	{
		$client_info    = client_redis_info($client);
		$client_key     = $this->client_redis_key.$client;

		$redis = new Redis();
		$redis->connect($client_info['redis_host'], $client_info['redis_port'], $this->redis_timeout);
		$redis->auth($client_info['redis_password']);
		
		if ($action === 'SET')
		{
			$check = $redis->hMSet($client_key, $data);
		}
		else
		{
			if (is_null($field))
			{
				$check = $redis->hGetAll($client_key);
			}
			else
			{
				$check = $redis->hGet($client_key, $field);
			}
		}     
			
		$redis->close();
		
		if (empty($check))
		{
			$check = NULL;
		}
		
		return $check;		
	}

	public function create_client_redis_key($client, $data = NULL)
	{
		$client_info    = client_redis_info($client);
		$client_key     = $this->client_redis_key.$client;
		
		$redis = new Redis();
		$redis->connect($client_info['redis_host'], $client_info['redis_port'], $this->redis_timeout);
		$redis->auth($client_info['redis_password']);

		$check = $redis->hMSet($client_key, [
			'hostname'          => $data['hostname'],
			'org_key'           => $data['org_key'],
			'api_secret_key'    => $data['api_secret_key'],
			'api_id'            => $data['api_id'],
			'endpointer'        => 'id',
			'tested'            => '0',
			'request_sent'      => '0',
			'terms_agreed'      => '0'
		]);
						
		$redis->close();
		
		return $check;		
	}

	public function quarantine_machine($client, $machine_id, $data = NULL)
	{
		$device_id  = base64_decode_url($machine_id);
		$response   = $this->quarantine($client, $device_id, TRUE);

		if ($response['success'])
		{
			$response2 = $this->specific_device_info($client, $device_id);

			if ($response2['success'])
			{
				return array(
					'success'   => TRUE,
					'response'  => array(
						'status'    => $response2['response']['quarantined'] ? 'Succeeded' : 'Pending',
						'id'        => $response2['response']['id']
					)
				);
			}
			else
			{
				$msg = $response2['response']['message'] ?? $response2['response']['error'] ?? $response2['response']['error_code'] ?? 'N/A';

				return array(
					'success'   => FALSE,
					'response'  => $response2['response'],
					'message'   => $msg
				);
			}
		}
		else
		{
			$msg = $response['response']['message'] ?? $response['response']['error'] ?? $response['response']['error_code'] ?? 'N/A';

			return array(
				'success'   => FALSE,
				'response'  => $response['response'],
				'message'   => $msg
			);
		}
	}

	public function machine_status($client, $vars, $data = NULL)
	{
		$device_id  = base64_decode_url($vars['machine_id']);
		$response   = $this->specific_device_info($client, $device_id);
		
		if ($response['success'])
		{
			return array(
				'success'   => TRUE,
				'response'  => array(
					'status'    => $response['response']['quarantined'] ? 'Succeeded' : 'Pending',
					'id'        => $response['response']['id']
				)
			);
		}
		else
		{
			return array(
				'success'   => FALSE,
				'response'  => $response['response']
			);
		}
	}

	public function pull_machine_id($event_json)
	{
		$event_obj = json_decode($event_json);

		if (isset($event_obj->payload))
		{
			$payload_obj = json_decode(base64_decode($event_obj->payload));

			if (json_last_error_msg() == 'No error')
			{
				if (isset($payload_obj->deviceInfo->deviceId))
				{
					return base64_encode_url($payload_obj->deviceInfo->deviceId);
				}
			}
		}

		return NULL;
	}

	public function get_endpoint_list_search($client, $search_params)
	{
		$response = $this->search_devices($client, $search_params);

		if ($response['success'])
		{
			$devices        = [];
			$results        = [];
			$private_ip     = TRUE;
			$device_count   = count($response['response']['results']); 
			$num_found      = $response['response']['num_found'];

			if (in_array($search_params['type'], array('computer_name', 'ip_address', 'mac_address')))
			{
				if ($search_params['type'] === 'ip_address')
				{
					$private_ip = $this->ci->utility->is_private_ip($search_params['term']);
				}

				foreach ($response['response']['results'] as $device)
				{
					if (($search_params['type'] === 'computer_name' && stripos($device['name'], $search_params['term']) !== FALSE) ||
						($search_params['type'] === 'ip_address' && $search_params['term'] === ($private_ip ? $device['last_internal_ip_address'] : $device['last_external_ip_address'])) ||
						($search_params['type'] === 'mac_address' && $search_params['term'] === $device['mac_address']))
					{
						$devices[] = array(
							'client_code'   => $search_params['client'],
							'id'            => base64_encode_url($device['id']),
							'name'          => $device['name'],
							'last_ip'       => $device['last_internal_ip_address'],
							'platform'      => ucfirst(strtolower($device['os'])),
							'mac_address'   => $device['mac_address'],
							'provider'      => 'carbon_black'
						);

						$results[] = $device;
					}
				}

				$device_count = count($devices);

				unset($response['response']['results']);
				$response['response']['results']    = $results;
				$response['response']['num_found']  = $device_count;

				$return_array = array(
					'success'       => TRUE,
					'response'      => $response['response'],
					'machine_count' => $device_count,
					'machine_data'  => $devices,
					'machine_total' => $device_count
				);
			}
			else
			{
				foreach ($response['response']['results'] as $device)
				{
					$devices[] = array(
						'client_code'   => $search_params['client'],
						'id'            => base64_encode_url($device['id']),
						'name'          => $device['name'],
						'last_ip'       => $device['last_internal_ip_address'],
						'platform'      => ucfirst(strtolower($device['os'])),
						'mac_address'   => $device['mac_address'],
						'provider'      => 'carbon_black'
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
		}
		else
		{
			$return_array = array(
				'success'   => FALSE,
				'response'  => $response['response']
			);
		}

		return $return_array;
	}

	public function get_endpoint_information($client, $client_code, $machine_id)
	{
		$device_id  = base64_decode_url($machine_id);
		$response   = $this->specific_device_info($client, $device_id);

		if ($response['success'])
		{
			$machine = $response['response'];

			$device = array(
				'name'          => $machine['name'],
				'last_ip'       => $machine['last_internal_ip_address'],
				'platform'      => ucfirst(strtolower($machine['os'])),
				'id'            => $machine['id'],
				'mac'           => $machine['mac_address'],
				'last_user'     => !empty($machine['login_user_name']) ? $machine['login_user_name'] : false,
				'connected'     => !$machine['quarantined'],
				'url_id'        => base64_encode_url($machine['id']),
				'provider'      => 'carbon-black',
				'client_code'   => $client_code
			);

			$return_array = array(
				'success'   => TRUE,
				'json'      => $machine,
				'details'   => $device
			);
		}
		else
		{
			$return_array = array(
				'success'   => FALSE,
				'response'  => $response['response']
			);
		}

		return $return_array;
	}

	public function search_devices($client, $search_params = NULL, $rows = 20, $start = 0)
	{
		$cb_info    = $this->redis_info($client);
		$url        = 'https://'.$cb_info['hostname'].'/appservices/v6/orgs/'.$cb_info['org_key'].'/devices/_search';

		$header_fields = array(
			'Content-Type: application/json',
			'Accept: application/json',
			'X-Auth-Token: '.$cb_info['api_secret_key'].'/'.$cb_info['api_id']
		);

		$criteria = new stdClass();

		if (!is_null($search_params))
		{
			switch ($search_params['type'])
			{
				case 'device_id':
					$criteria->id       = [intval($search_params['term'])];
					break;
				case 'os':
					$criteria->os       = [strtoupper($search_params['term'])];
					$rows               = $this->max_rows;
					break;
				case 'status':
					$criteria->status   = [strtoupper($search_params['term'])];
					$rows               = $this->max_rows;
					break;
				case 'computer_name':
				case 'ip_address':
				case 'mac_address':
				case 'list':
				default:
					$criteria->status   = ['ALL'];
					$rows               = $this->max_rows;
			}
		}
		else
		{
			$criteria->status   = ['ALL'];
			$rows               = $this->max_rows;
		}

		$post_fields = new stdClass();
		$post_fields->criteria  = $criteria;
		$post_fields->rows      = $rows;   // Max: 10k
		$post_fields->start     = $start;  // Rows + Start should not exceed 10k

		$response = $this->call_api('POST', $url, $header_fields, json_encode($post_fields));

		if ($response['result'] !== FALSE)
		{
			if ($response['http_code'] === 200)
			{
				return array(
					'success'   => TRUE,
					'response'  => $response['result']
				);
			}
			else
			{
				return array(
					'success'   => FALSE,
					'response'  => $response['result']
				);
			}
		}
		else
		{
			return array(
				'success'   => FALSE,
				'response'  => array(
					'status'    => 'cURL returned false',
					'message'   => 'errno = '.$response['errno'].', error = '.$response['error']
				)
			);
		}
	}

	public function specific_device_info($client, $device_id)
	{
		$cb_info    = $this->redis_info($client);
		$url        = 'https://'.$cb_info['hostname'].'/appservices/v6/orgs/'.$cb_info['org_key'].'/devices/'.$device_id;

		$header_fields = array(
			'Accept: application/json',
			'X-Auth-Token: '.$cb_info['api_secret_key'].'/'.$cb_info['api_id']
		);

		$response = $this->call_api('GET', $url, $header_fields);

		if ($response['result'] !== FALSE)
		{
			if ($response['http_code'] === 200)
			{
				return array(
					'success'   => TRUE,
					'response'  => $response['result']
				);
			}
			else
			{
				return array(
					'success'   => FALSE,
					'response'  => $response['result']
				);
			}
		}
		else
		{
			return array(
				'success'   => FALSE,
				'response'  => array(
					'status'    => 'cURL returned false',
					'message'   => 'errno = '.$response['errno'].', error = '.$response['error']
				)
			);
		}
	}

	public function quarantine($client, $device_id, $enable = FALSE)
	{
		$cb_info    = $this->redis_info($client);
		$url        = 'https://'.$cb_info['hostname'].'/appservices/v6/orgs/'.$cb_info['org_key'].'/device_actions';

		$header_fields = array(
			'Content-Type: application/json',
			'Accept: application/json',
			'X-Auth-Token: '.$cb_info['api_secret_key'].'/'.$cb_info['api_id']
		);

		$options = new stdClass();
		$options->toggle = $enable ? 'ON' : 'OFF';

		$post_fields = new stdClass();
		$post_fields->action_type   = 'QUARANTINE';
		$post_fields->device_id[]   = $device_id;
		$post_fields->options       = $options;

		$response = $this->call_api('POST', $url, $header_fields, json_encode($post_fields));

		if ($response['result'] !== FALSE)
		{
			if ($response['http_code'] === 204)
			{
				return array(
					'success'   => TRUE,
					'response'  => $response['result']
				);
			}
			else
			{
				return array(
					'success'   => FALSE,
					'response'  => $response['result']
				);
			}
		}
		else
		{
			return array(
				'success'   => FALSE,
				'response'  => array(
					'status'    => 'cURL returned false',
					'message'   => 'errno = '.$response['errno'].', error = '.$response['error']
				)
			);
		}
	}

	private function call_api($method, $url, $header_fields, $post_fields = NULL)
	{
		$this->ch = curl_init();

		switch ($method)
		{
			case 'POST':
				curl_setopt($this->ch, CURLOPT_POST, true);

				if (isset($post_fields))
				{
					curl_setopt($this->ch, CURLOPT_POSTFIELDS, $post_fields);
				}

				break;
			case 'PUT':
				curl_setopt($this->ch, CURLOPT_CUSTOMREQUEST, 'PUT');

				if (isset($post_fields))
				{
					curl_setopt($this->ch, CURLOPT_POSTFIELDS, $post_fields);
				}

				break;
			case 'DELETE':
				curl_setopt($this->ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
				break;
		}

		if (is_array($header_fields))
		{
			curl_setopt($this->ch, CURLOPT_HTTPHEADER, $header_fields);
		}

		curl_setopt($this->ch, CURLOPT_URL, $url);
		curl_setopt($this->ch, CURLOPT_RETURNTRANSFER, true);
		//curl_setopt($this->ch, CURLOPT_SSL_VERIFYHOST, false);
		//curl_setopt($this->ch, CURLOPT_SSL_VERIFYPEER, false);

		curl_setopt($this->ch, CURLOPT_CONNECTTIMEOUT, 5);
		//curl_setopt($this->ch, CURLOPT_TIMEOUT, 10);

		if (($response['result'] = curl_exec($this->ch)) !== FALSE)
		{
			if (curl_getinfo($this->ch, CURLINFO_SIZE_DOWNLOAD_T))
			{
				$response['result'] = json_decode($response['result'], TRUE);
			}

			$response['http_code'] = curl_getinfo($this->ch, CURLINFO_HTTP_CODE);
		}
		else
		{
			$response['errno'] 	= curl_errno($this->ch);
			$response['error'] 	= curl_error($this->ch);
		}

		curl_close($this->ch);

		return $response;
	}

	public function change_api_activation_status($client, $requested, $status)
	{
		$set_activation = $status ? 1 : 0;
		$set_provider   = $status ? 'carbon_black' : '';
		$check          = FALSE;
		
		#set soc redis keys
		$redis = new Redis();
		$redis->connect($this->redis_host, $this->redis_port, $this->redis_timeout);
		$redis->auth($this->redis_password);

		$check = $redis->hMSet($client.'_information', [
			'endpoint_provider' => $set_provider,
			'endpoint_enabled'  => $set_activation
		]);
			
		$redis->close();

		# set client redis keys
		if ($check)
		{
			$status_data = array(
				'request_sent'  => $set_activation,
				'request_user'  => $requested,
				'terms_agreed'  => $set_activation
			);

			$config_data = array(
				'endpoint_provider' => $set_provider,
				'endpoint_enabled'  => $set_activation
			);
			
			if ($this->redis_info($client, NULL, 'SET', $status_data))
			{
				$this->reset_providers($client, 'carbon_black');

				if ($this->client_config($client, NULL, 'SET', $config_data))
				{
					return TRUE;
				}
			}
		}

		return FALSE;
	}

	private function reset_providers($client, $active_provider)
	{
		$client_info    = client_redis_info($client);
		$providers      = endpoint_providers($active_provider);

		$redis = new Redis();
		$redis->connect($client_info['redis_host'], $client_info['redis_port'], $this->redis_timeout);
		$redis->auth($client_info['redis_password']);
			
		$reset_data = array(
			'request_sent'  => '0',
			'request_user'  => '0',
			'terms_agreed'  => '0'
		);
		
		foreach ($providers as $provider)
		{
			$provider_key = $provider.'_'.$client;

			if ($redis->exists($provider_key))
			{
				$redis->hMSet($provider_key, $reset_data);
			}
		}
				
		$redis->close();
	}
	
	public function client_config($client, $field = NULL, $action = 'GET', $data = NULL)
	{
		$client_info    = client_redis_info($client);
		$client_key     = $client.'_configurations';

		$redis = new Redis();
		$redis->connect($client_info['redis_host'], $client_info['redis_port'], $this->redis_timeout);
		$redis->auth($client_info['redis_password']);

		if ($action === 'SET')
		{
			$check = $redis->hMSet($client_key, $data);
		}
		else
		{
			if (is_null($field))
			{
				$check = $redis->hGetAll($client_key);
			}
			else
			{
				$check = $redis->hGet($client_key, $field);
			}
		}   
			
		$redis->close();
		
		if (empty($check))
		{
			$check = NULL;
		}
		
		return $check;		
	}
}