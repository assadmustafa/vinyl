<?php
class ControllerCommonDashboard extends Controller {
	public function index() {
      if(version_compare(VERSION, '3.0', '<')) {
            $this->load->model('extension/extension');

            $extensions = $this->model_extension_extension->getInstalled('payment');
        } else {
            $this->load->model('setting/extension');

            $extensions = $this->model_setting_extension->getInstalled('payment');

        }

        $data['success'] = '';
        foreach ($extensions as $key => $value) {
            if ($value == 'mollie_ideal') {
                require_once(DIR_SYSTEM . "library/mollie/helper.php");
                require_once(DIR_SYSTEM . "/library/mollie/mollieHttpClient.php");
                $client = new Mollie\mollieHttpClient();
                $info = $client->get("https://api.github.com/repos/mollie/OpenCart/releases/latest");
                $mollieHelper = new MollieHelper($this->registry);
                if (isset($info["tag_name"]) && ($info["tag_name"] != $mollieHelper::PLUGIN_VERSION) && version_compare($mollieHelper::PLUGIN_VERSION, $info["tag_name"], "<") && (!isset($_COOKIE["hide_mollie_update_message_version"]) || ($_COOKIE["hide_mollie_update_message_version"] != $info["tag_name"]))) {
                    $this->load->language('payment/mollie_ideal');

                    if(version_compare(VERSION, '3.0', '<')) {
                        $token = 'token=' . $this->session->data['token'];
                    } else {
                        $token = 'user_token=' . $this->session->data['user_token'];
                    }

                    if (version_compare(phpversion(), $mollieHelper::MIN_PHP_VERSION, "<")) {
                        $data['success'] = sprintf($this->language->get('text_update_message'), $info["tag_name"], $this->url->link("payment/mollie_ideal", $token), $info["tag_name"]);
                    } else {
                        $data['success'] = sprintf($this->language->get('text_update_message'), $info["tag_name"], $this->url->link("payment/mollie_ideal/update", $token), $info["tag_name"]);
                    }
                }
                break;
            }
        }

		$this->load->language('common/dashboard');

		$this->document->setTitle($this->language->get('heading_title'));

		$data['user_token'] = $this->session->data['user_token'];

		$data['breadcrumbs'] = array();

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
		);

		// Check install directory exists
		if (is_dir(DIR_CATALOG . '../install')) {
			$data['error_install'] = $this->language->get('error_install');
		} else {
			$data['error_install'] = '';
		}

		// Dashboard Extensions
		$dashboards = array();

		$this->load->model('setting/extension');

		// Get a list of installed modules
		$extensions = $this->model_setting_extension->getInstalled('dashboard');

		// Add all the modules which have multiple settings for each module
		foreach ($extensions as $code) {
			if ($this->config->get('dashboard_' . $code . '_status') && $this->user->hasPermission('access', 'extension/dashboard/' . $code)) {
				$output = $this->load->controller('extension/dashboard/' . $code . '/dashboard');

				if ($output) {
					$dashboards[] = array(
						'code'       => $code,
						'width'      => $this->config->get('dashboard_' . $code . '_width'),
						'sort_order' => $this->config->get('dashboard_' . $code . '_sort_order'),
						'output'     => $output
					);
				}
			}
		}

		$sort_order = array();

		foreach ($dashboards as $key => $value) {
			$sort_order[$key] = $value['sort_order'];
		}

		array_multisort($sort_order, SORT_ASC, $dashboards);

		// Split the array so the columns width is not more than 12 on each row.
		$width = 0;
		$column = array();
		$data['rows'] = array();

		foreach ($dashboards as $dashboard) {
			$column[] = $dashboard;

			$width = ($width + $dashboard['width']);

			if ($width >= 12) {
				$data['rows'][] = $column;

				$width = 0;
				$column = array();
			}
		}

		if (!empty($column)) {
    			$data['rows'][] = $column;
		}

		if (DIR_STORAGE == DIR_SYSTEM . 'storage/') {
			$data['security'] = $this->load->controller('common/security');
		} else {
			$data['security'] = '';
		}

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		// Run currency update
		if ($this->config->get('config_currency_auto')) {
			$this->load->model('localisation/currency');

			$this->model_localisation_currency->refresh();
		}

		$this->response->setOutput($this->load->view('common/dashboard', $data));
	}
}
