<?php
/**
 * Copyright (c) 2012-2017, Mollie B.V.
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *
 * - Redistributions of source code must retain the above copyright notice,
 *    this list of conditions and the following disclaimer.
 * - Redistributions in binary form must reproduce the above copyright
 *    notice, this list of conditions and the following disclaimer in the
 *    documentation and/or other materials provided with the distribution.
 *
 * THIS SOFTWARE IS PROVIDED BY THE AUTHOR AND CONTRIBUTORS ``AS IS'' AND ANY
 * EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
 * WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
 * DISCLAIMED. IN NO EVENT SHALL THE AUTHOR OR CONTRIBUTORS BE LIABLE FOR ANY
 * DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
 * (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR
 * SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER
 * CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT
 * LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY
 * OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH
 * DAMAGE.
 *
 * @package     Mollie
 * @license     Berkeley Software Distribution License (BSD-License 2) http://www.opensource.org/licenses/bsd-license.php
 * @author      Mollie B.V. <info@mollie.com>
 * @copyright   Mollie B.V.
 * @link        https://www.mollie.com
 *
 * @property Config                       $config
 * @property DB                           $db
 * @property Language                     $language
 * @property Loader                       $load
 * @property ModelSettingSetting          $model_setting_setting
 * @property ModelSettingStore            $model_setting_store
 * @property ModelLocalisationOrderStatus $model_localisation_order_status
 * @property Request                      $request
 * @property Response                     $response
 * @property URL                          $url
 * @property User                         $user
 */
 
//Check if VQMod is installed
$vqversion = '';
if(version_compare(VERSION, '2.0', '<')) {
	if (!class_exists('VQMod')) {
	     die('<div class="alert alert-warning"><i class="fa fa-exclamation-circle"></i> This extension requires VQMod. Please download and install it on your shop. You can find the latest release <a href="https://github.com/vqmod/vqmod/releases" target="_blank">here</a>!    <button type="button" class="close" data-dismiss="alert">×</button></div>');
	} else {
		if (is_file(DIR_SYSTEM.'../vqmod/xml/mollie.xml_')) {
			rename(DIR_SYSTEM.'../vqmod/xml/mollie.xml_', DIR_SYSTEM.'../vqmod/xml/mollie.xml');
		}
	}
}

if (class_exists('VQMod')) {     
	$vqversion = VQMod::$_vqversion;
}
define("VQ_VERSION", $vqversion);

use Mollie\Api\Exceptions\ApiException;
use Mollie\Api\Exceptions\IncompatiblePlatform;
use Mollie\Api\MollieApiClient;
use Mollie\mollieHttpClient;

require_once(DIR_SYSTEM . "library/mollie/helper.php");
require_once(DIR_SYSTEM . "/library/mollie/mollieHttpClient.php");

define("MOLLIE_VERSION", MollieHelper::PLUGIN_VERSION);
define("MOLLIE_RELEASE", "v" . MOLLIE_VERSION);
define("MOLLIE_VERSION_URL", "https://api.github.com/repos/mollie/OpenCart/releases/latest");
// Defining arrays in a constant cannot be done with "define" until PHP 7, so using this syntax for backwards compatibility.
const DEPRECATED_METHODS = array('mistercash', 'bitcoin', 'directdebit', 'inghomepay');

if (!defined("MOLLIE_TMP")) {
    define("MOLLIE_TMP", sys_get_temp_dir());
}

class ControllerPaymentMollieBase extends Controller {
	const OUTH_URL = 'https://api.mollie.com/oauth2';

	// Initialize var(s)
	protected $error = array();

	// Holds multistore configs
	protected $data = array();
	private $token;
	public $mollieHelper;

	public function __construct($registry) {
		parent::__construct($registry);
    
    	$this->token = isset($this->session->data['user_token']) ? 'user_token='.$this->session->data['user_token'] : 'token='.$this->session->data['token'];
    	$this->mollieHelper = new MollieHelper($registry);
	}

	/**
	 * @param int $store The Store ID
	 * @return MollieApiClient
	 */
	protected function getAPIClient ($store = 0) {
		$data = $this->data;
		$data[$this->mollieHelper->getModuleCode() . "_api_key"] = $this->mollieHelper->getApiKey($store);
		
		return $this->mollieHelper->getAPIClientAdmin($data);
	}

	/**
	 * This method is executed by OpenCart when the Payment module is installed from the admin. It will create the
	 * required tables.
	 *
	 * @return void
	 */
	public function install () {
		// Just install all modules while we're at it.
		$this->installAllModules();
		$this->cleanUp();

		//Add event to create shipment
		if (version_compare(VERSION, '2.2', '>=')) { // Events were added in OC2.2
			if ($this->mollieHelper->isOpenCart3x()) {
				$this->load->model('setting/event');
				$this->model_setting_event->deleteEventByCode('mollie_create_shipment');
				$this->model_setting_event->addEvent('mollie_create_shipment', 'catalog/model/checkout/order/addOrderHistory/after', 'payment/mollie/base/createShipment');
			} else {
				$this->load->model('extension/event');
				$this->model_extension_event->deleteEvent('mollie_create_shipment');
				$this->model_extension_event->addEvent('mollie_create_shipment', 'catalog/model/checkout/order/addOrderHistory/after', 'payment/mollie/base/createShipment');
			}
		}

		// Create mollie payments table
		$this->db->query(
			sprintf(
				"CREATE TABLE IF NOT EXISTS `%smollie_payments` (
					`order_id` INT(11) NOT NULL,
					`method` VARCHAR(32) NOT NULL,
					`mollie_order_id` VARCHAR(32) NOT NULL,
					`transaction_id` VARCHAR(32),
					`bank_account` VARCHAR(15),
					`bank_status` VARCHAR(20),
					`refund_id` VARCHAR(32),
					`subscription_id` VARCHAR(32),
					`order_recurring_id` INT(11),
					`next_payment` DATETIME,
					`subscription_end` DATETIME,
					`date_modified` DATETIME NOT NULL,
					`payment_attempt` INT(11) NOT NULL,
					PRIMARY KEY (`mollie_order_id`),
					UNIQUE KEY `mollie_order_id` (`mollie_order_id`)
				) DEFAULT CHARSET=utf8",
				DB_PREFIX
			)
		);

		// Create mollie customers table
		$this->db->query(
			sprintf(
				"CREATE TABLE IF NOT EXISTS `%smollie_customers` (
					`id` INT(11) NOT NULL AUTO_INCREMENT,
					`mollie_customer_id` VARCHAR(32) NOT NULL,
					`customer_id` INT(11) NOT NULL,
					`email` VARCHAR(96) NOT NULL,
					`date_created` DATETIME NOT NULL,
					PRIMARY KEY (`id`)
				) DEFAULT CHARSET=utf8",
				DB_PREFIX
			)
		);

		// Create mollie recurring payments table
		$this->db->query(
			sprintf(
				"CREATE TABLE IF NOT EXISTS `%smollie_recurring_payments` (
					`id` INT(11) NOT NULL AUTO_INCREMENT,
					`transaction_id` VARCHAR(32),
					`order_recurring_id` INT(11),
					`subscription_id` VARCHAR(32) NOT NULL,
					`mollie_customer_id` VARCHAR(32) NOT NULL,
					`method` VARCHAR(32) NOT NULL,
					`status` VARCHAR(32) NOT NULL,
					`date_created` DATETIME NOT NULL,
					PRIMARY KEY (`id`)
				) DEFAULT CHARSET=utf8",
				DB_PREFIX
			)
		);

		// Create mollie refund table
		$this->db->query(
			sprintf(
				"CREATE TABLE IF NOT EXISTS `%smollie_refund` (
					`id` INT(11) NOT NULL AUTO_INCREMENT,
					`refund_id` VARCHAR(32),
					`order_id` INT(11) NOT NULL,
					`transaction_id` VARCHAR(32),
					`amount` decimal(15,4),
					`currency_code` VARCHAR(32),
					`date_created` DATETIME NOT NULL,
					PRIMARY KEY (`id`)
				) DEFAULT CHARSET=utf8",
				DB_PREFIX
			)
		);

		$this->db->query("ALTER TABLE `" . DB_PREFIX . "order` MODIFY `payment_method` VARCHAR(255) NOT NULL;");

		//Check if subscription fields exist
		if(!$this->db->query("SHOW COLUMNS FROM `" . DB_PREFIX . "mollie_payments` LIKE 'subscription_id'")->row)
			$this->db->query("ALTER TABLE `" . DB_PREFIX . "mollie_payments` ADD `subscription_id` VARCHAR(32), ADD `order_recurring_id` INT(11), ADD `next_payment` DATETIME, ADD `subscription_end` DATETIME");

		//Check if mollie_order_id field exists
		if(!$this->db->query("SHOW COLUMNS FROM `" . DB_PREFIX . "mollie_payments` LIKE 'mollie_order_id'")->row)
			$this->db->query("ALTER TABLE `" . DB_PREFIX . "mollie_payments` ADD `mollie_order_id` VARCHAR(32) UNIQUE");

		//Check if refund_id field exists
		if(!$this->db->query("SHOW COLUMNS FROM `" . DB_PREFIX . "mollie_payments` LIKE 'refund_id'")->row)
			$this->db->query("ALTER TABLE `" . DB_PREFIX . "mollie_payments` ADD `refund_id` VARCHAR(32)");

		//Check if date_modified field exists
		if(!$this->db->query("SHOW COLUMNS FROM `" . DB_PREFIX . "mollie_payments` LIKE 'date_modified'")->row)
			$this->db->query("ALTER TABLE `" . DB_PREFIX . "mollie_payments` ADD `date_modified` DATETIME NOT NULL");

		//Check if payment_attempt field exists
		if(!$this->db->query("SHOW COLUMNS FROM `" . DB_PREFIX . "mollie_payments` LIKE 'payment_attempt'")->row)
			$this->db->query("ALTER TABLE `" . DB_PREFIX . "mollie_payments` ADD `payment_attempt` INT(11) NOT NULL");
		
		//Check if amount fields exist
		if(!$this->db->query("SHOW COLUMNS FROM `" . DB_PREFIX . "mollie_payments` LIKE 'amount'")->row)
			$this->db->query("ALTER TABLE `" . DB_PREFIX . "mollie_payments` ADD `amount` decimal(15,4)");

		//Check if status fields exist
		if(!$this->db->query("SHOW COLUMNS FROM `" . DB_PREFIX . "mollie_refund` LIKE 'status'")->row)
			$this->db->query("ALTER TABLE `" . DB_PREFIX . "mollie_refund` ADD `status` VARCHAR(20)");

		// Update primary key
		// $query = $this->db->query("SHOW INDEX FROM `" .DB_PREFIX. "mollie_payments` where Key_name = 'PRIMARY'");
		// if($query->num_rows > 0 && $query->row['Column_name'] != 'mollie_order_id') {
		// 	$this->db->query("DELETE FROM `" .DB_PREFIX. "mollie_payments` where mollie_order_id IS NULL OR mollie_order_id = ''");
		// 	$this->db->query("ALTER TABLE `" .DB_PREFIX. "mollie_payments` DROP PRIMARY KEY, ADD PRIMARY KEY (mollie_order_id)");
		// }

		// Update Primary Key
		$query = $this->db->query("SHOW INDEX FROM `" .DB_PREFIX. "mollie_payments` where Key_name = 'PRIMARY'");
		if($query->num_rows > 0) {
			$this->db->query("ALTER TABLE `" .DB_PREFIX. "mollie_payments` DROP PRIMARY KEY, ADD PRIMARY KEY (mollie_order_id, transaction_id)");
		} else {
			$this->db->query("ALTER TABLE `" .DB_PREFIX. "mollie_payments` ADD PRIMARY KEY (mollie_order_id, transaction_id)");
		}

		// Drop Unique Key
		$query = $this->db->query("SHOW INDEX FROM `" .DB_PREFIX. "mollie_payments`");
		if($query->num_rows > 0) {
			foreach ($query->rows as $row) {
				if ($row['Key_name'] != 'PRIMARY') {
					$this->db->query("ALTER TABLE `" .DB_PREFIX. "mollie_payments` DROP INDEX " . $row['Key_name'] . "");
				}
			}
		}

		// Add voucher category field
		if(!$this->db->query("SHOW COLUMNS FROM `" . DB_PREFIX . "product` LIKE 'voucher_category'")->row) {
			$this->db->query("ALTER TABLE `" . DB_PREFIX . "product` ADD `voucher_category` VARCHAR(20) NULL");
		}

		// Fix for empty transaction id in old versions
		$query = $this->db->query("SELECT * FROM `" .DB_PREFIX. "mollie_payments`");
		if ($query->num_rows) {
			foreach ($query->rows as $row) {
				if (!$row['transaction_id']) {
					$rand_string = substr(str_shuffle(str_repeat($x='0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ', ceil(10/strlen($x)) )), 1, 10);

					$this->db->query("UPDATE `" .DB_PREFIX. "mollie_payments` SET transaction_id = '" . $rand_string . "' WHERE order_id = '" . $row['order_id'] . "' AND mollie_order_id = '" . $row['mollie_order_id'] . "'");
				}
			}
		}
	}

	public function enableModFile($keep = '') {
		if ($keep != '') {
			if ($keep == 'vqmod') {
				if (is_file(DIR_SYSTEM.'../vqmod/xml/mollie.xml_')) {
					rename(DIR_SYSTEM.'../vqmod/xml/mollie.xml_', DIR_SYSTEM.'../vqmod/xml/mollie.xml');
				}
			} else {
				if (is_file(DIR_SYSTEM.'../system/mollie.ocmod.xml_')) {
					rename(DIR_SYSTEM.'../system/mollie.ocmod.xml_', DIR_SYSTEM.'../system/mollie.ocmod.xml');
				}
			}
		}

		$code = $this->mollieHelper->getModuleCode();
		
		$file = isset($this->request->get['file_name']) ? $this->request->get['file_name'] : '';
		if ($file != '') {
			if ($file == 'vqmod') {
				if (is_file(DIR_SYSTEM.'../vqmod/xml/mollie.xml_')) {
					rename(DIR_SYSTEM.'../vqmod/xml/mollie.xml_', DIR_SYSTEM.'../vqmod/xml/mollie.xml');
				}

				$this->db->query("INSERT INTO " . DB_PREFIX . "setting SET store_id = '0', `code` = '" . $this->db->escape($code) . "', `key` = '" . $this->db->escape($code . '_mod_file') . "', `value` = 'vqmod'");
			} else {
				if (is_file(DIR_SYSTEM.'../system/mollie.ocmod.xml_')) {
					rename(DIR_SYSTEM.'../system/mollie.ocmod.xml_', DIR_SYSTEM.'../system/mollie.ocmod.xml');
				}

				$this->db->query("INSERT INTO " . DB_PREFIX . "setting SET store_id = '0', `code` = '" . $this->db->escape($code) . "', `key` = '" . $this->db->escape($code . '_mod_file') . "', `value` = 'ocmod'");
			}
		}

		// Delete the modification files to avoid errors
		if (version_compare(VERSION, '2.0', '>=')) {
			if (is_dir(DIR_MODIFICATION . 'admin')) {
				$this->delTree(DIR_MODIFICATION . 'admin');
			}
		}

		// Delete mods.cache
		if (is_file(DIR_SYSTEM.'../vqmod/mods.cache')) {
			unlink(DIR_SYSTEM.'../vqmod/mods.cache');
		}

		if (version_compare(VERSION, '3', '>=')) {
			$this->load->controller('marketplace/modification/refresh');
		} elseif (version_compare(VERSION, '2', '>=')) {
			$this->load->controller('extension/modification/refresh');
		} else {
			$this->redirect($this->url->link('payment/mollie_' . static::MODULE_NAME, $this->token, 'SSL'));
		}
	}

	public function checkModFiles() {
		if (version_compare(VERSION, '2.0', '<')) {
			// For OC versions 1.5.x, no ocmod is needed.
			if (is_file(DIR_SYSTEM.'../vqmod/xml/mollie.xml_')) {
				rename(DIR_SYSTEM.'../vqmod/xml/mollie.xml_', DIR_SYSTEM.'../vqmod/xml/mollie.xml');
			}
			// Delete mods.cache
			if (is_file(DIR_SYSTEM.'../vqmod/mods.cache')) {
				unlink(DIR_SYSTEM.'../vqmod/mods.cache');
			}

			return 'true';
		}

		if (is_file(DIR_SYSTEM.'../vqmod/xml/mollie.xml_') && is_file(DIR_SYSTEM.'../system/mollie.ocmod.xml_')) {
			$code = $this->mollieHelper->getModuleCode();
			if ($this->config->get($code . '_mod_file')) {
				$this->enableModFile($this->config->get($code . '_mod_file'));
			} else {
				return '<div class="alert alert-warning"><div style="text-align: center;padding: 20px;background-color: red;color: #fff;font-size: 28px;"><span><i class="fa fa-exclamation-circle"></i> Please choose VQMOD or OCMOD to finalise installation</span></div><div style="display: flex;margin-top: 20px;margin-bottom: 40px;"><div style="width: 50%;text-align: right;margin-right: 10px;"><a href="' . $this->url->link('payment/mollie_' . static::MODULE_NAME . '/enableModFile', 'file_name=ocmod&' . $this->token, 'SSL') . '" class="btn btn-primary" style="padding: 20px;font-size: 18px;">Click Here To Use OCMOD Version</a></div><div style="width: 50%;text-align: left;margin-left: 10px;"><a href="' . $this->url->link('payment/mollie_' . static::MODULE_NAME . '/enableModFile', 'file_name=vqmod&' . $this->token, 'SSL') . '" class="btn btn-primary"  style="padding: 20px;font-size: 18px;">Click Here To Use VQMOD Version</a></div></div>- OCMOD is recommended for opencart versions 2.x and later<br/>- You will be redirected to modification page to refresh modifications<br/>- In case of error please delete the files/folders inside "storage/modification" folder. And refresh again.</div>';
			}
		} elseif (is_file(DIR_SYSTEM.'../vqmod/xml/mollie.xml') && is_file(DIR_SYSTEM.'../system/mollie.ocmod.xml')) {
			return '<div class="alert alert-warning"><div style="text-align: center;padding: 20px;background-color: red;color: #fff;font-size: 28px;margin-bottom:20px;"><span><i class="fa fa-exclamation-circle"></i> Please choose VQMOD or OCMOD to finalise installation</span></div>- Delete vqmod file if you want to use OCMOD version<br/>- Or delete ocmod file if you want to use VQMOD version<br/>- OCMOD is recommended for opencart versions 2.x and later<br/>- In case of error please delete the files/folders inside "storage/modification" folder. And refresh again.</div>';
		}

		return 'true';
	}
	
	/**
	 * Clean up files that are not needed for the running version of OC.
	 */
	public function cleanUp() {
		$adminThemeDir = DIR_APPLICATION . 'view/template/';
		$catalogThemeDir = DIR_CATALOG . 'view/theme/default/template/';

		// Remove old template from previous version.
		if (file_exists($adminThemeDir . 'extension/payment/mollie_2.tpl')) {
			unlink($adminThemeDir . 'extension/payment/mollie_2.tpl');
			unlink($adminThemeDir . 'payment/mollie_2.tpl');
		}

		//Remove deprecated method files from old version
		$adminControllerDir   = DIR_APPLICATION . 'controller/';
		$adminLanguageDir     = DIR_APPLICATION . 'language/';
		$catalogControllerDir = DIR_CATALOG . 'controller/';
		$catalogModelDir      = DIR_CATALOG . 'model/';

		$files = array();

		foreach (DEPRECATED_METHODS as $method) {
			$files = array(
				$adminControllerDir . 'extension/payment/mollie_' . $method . '.php',
				$catalogControllerDir . 'extension/payment/mollie_' . $method . '.php',
				$catalogModelDir . 'extension/payment/mollie_' . $method . '.php',
				$adminControllerDir . 'payment/mollie_' . $method . '.php',
				$catalogControllerDir . 'payment/mollie_' . $method . '.php',
				$catalogModelDir . 'payment/mollie_' . $method . '.php'
			);

			foreach ($files as $file) {
				if (file_exists($file)) {
					unlink($file);
				}
			}

			$languageFiles = glob($adminLanguageDir . '*/extension/payment/mollie_' . $method . '.php');
			foreach ($languageFiles as $file) {
				if (file_exists($file)) {
					unlink($file);
				}
			}

			$languageFiles = glob($adminLanguageDir . '*/payment/mollie_' . $method . '.php');
			foreach ($languageFiles as $file) {
				if (file_exists($file)) {
					unlink($file);
				}
			}
		}

		if ($this->mollieHelper->isOpenCart3x()) {
			$files = array(
				$adminThemeDir . 'extension/payment/mollie(max_1.5.6.4).tpl',
				$adminThemeDir . 'payment/mollie(max_1.5.6.4).tpl',
				$catalogThemeDir . 'extension/payment/mollie_return.tpl',
				$catalogThemeDir . 'payment/mollie_return.tpl',
				$catalogThemeDir . 'extension/payment/mollie_checkout_form.tpl',
				$catalogThemeDir . 'payment/mollie_checkout_form.tpl',
				$adminThemeDir . 'extension/payment/mollie.twig', //Remove twig file from old version
				$adminThemeDir . 'payment/mollie.twig' //Remove twig file from old version
			);
			
		} elseif ($this->mollieHelper->isOpenCart2x()) {
			$files = array(
				$adminThemeDir . 'extension/payment/mollie(max_1.5.6.4).tpl',
				$adminThemeDir . 'payment/mollie(max_1.5.6.4).tpl',
				$catalogThemeDir . 'extension/payment/mollie_return.twig',
				$catalogThemeDir . 'payment/mollie_return.twig',
				$catalogThemeDir . 'extension/payment/mollie_checkout_form.twig',
				$catalogThemeDir . 'payment/mollie_checkout_form.twig'
			);
			
		} else {
			$files = array(
				$adminThemeDir . 'extension/payment/mollie.tpl',
				$adminThemeDir . 'payment/mollie.tpl',
				$catalogThemeDir . 'extension/payment/mollie_return.twig',
				$catalogThemeDir . 'payment/mollie_return.twig',
				$catalogThemeDir . 'extension/payment/mollie_checkout_form.twig',
				$catalogThemeDir . 'payment/mollie_checkout_form.twig'
			);
			
		}

		foreach ($files as $file) {
			if (file_exists($file)) {
				unlink($file);
			}
		}

		// Remove base.php file from version 8.x
		if (file_exists($adminControllerDir . 'extension/payment/mollie')) {
			$this->delTree($adminControllerDir . 'extension/payment/mollie');
		}

		if (file_exists($catalogControllerDir . 'extension/payment/mollie')) {
			$this->delTree($catalogControllerDir . 'extension/payment/mollie');
		}

		if (file_exists($catalogControllerDir . 'extension/payment/mollie-api-client')) {
			$this->delTree($catalogControllerDir . 'extension/payment/mollie-api-client');
		}

		//API has been moved to library folder
		if (file_exists($catalogControllerDir . 'payment/mollie-api-client')) {
			$this->delTree($catalogControllerDir . 'payment/mollie-api-client');
		}

		if (file_exists(DIR_APPLICATION . '../vqmod/xml/mollie_onepage_no_givenname.xml')) {
			unlink(DIR_APPLICATION . '../vqmod/xml/mollie_onepage_no_givenname.xml');
		}

		// Remove patch
		if (is_dir(DIR_APPLICATION . 'patch')) {
			$this->delTree(DIR_APPLICATION . 'patch');
		}

		// Remove old helper file from version 9.4.0
		if (file_exists($catalogControllerDir . 'payment/mollie/helper.php')) {
			unlink($catalogControllerDir . 'payment/mollie/helper.php');
		}

		// Remove mollie.php file from version 10.0.0
		if (file_exists($catalogControllerDir . 'extension/payment/mollie.php')) {
			unlink($catalogControllerDir . 'extension/payment/mollie.php');
		}
		if (file_exists($catalogControllerDir . 'payment/mollie.php')) {
			unlink($catalogControllerDir . 'payment/mollie.php');
		}
		if (file_exists($catalogModelDir . 'extension/payment/mollie.php')) {
			unlink($catalogModelDir . 'extension/payment/mollie.php');
		}
		if (file_exists($catalogModelDir . 'payment/mollie.php')) {
			unlink($catalogModelDir . 'payment/mollie.php');
		}
		if (file_exists($adminControllerDir . 'extension/payment/mollie.php')) {
			unlink($adminControllerDir . 'extension/payment/mollie.php');
		}
		if (file_exists($adminControllerDir . 'payment/mollie.php')) {
			unlink($adminControllerDir . 'payment/mollie.php');
		}
		if (file_exists(DIR_SYSTEM . 'library/mollieHttpClient.php')) {
			unlink(DIR_SYSTEM . 'library/mollieHttpClient.php');
		}
		// Remove old xml files
		if (is_file(DIR_SYSTEM.'../vqmod/xml/mollie.xml_') && is_file(DIR_SYSTEM.'../system/mollie.ocmod.xml_')) {
			if (file_exists(DIR_SYSTEM.'../system/mollie.ocmod.xml')) {
				unlink(DIR_SYSTEM.'../system/mollie.ocmod.xml');
			}
			if (file_exists(DIR_SYSTEM.'../vqmod/xml/mollie.xml')) {
				unlink(DIR_SYSTEM.'../vqmod/xml/mollie.xml');
			}
			// Delete the modification files to avoid errors
			if (version_compare(VERSION, '2.0', '>=')) {
				if (is_dir(DIR_MODIFICATION . 'admin')) {
					$this->delTree(DIR_MODIFICATION . 'admin');
				}
			}		
			// Delete mods.cache
			if (is_file(DIR_SYSTEM.'../vqmod/mods.cache')) {
				unlink(DIR_SYSTEM.'../vqmod/mods.cache');
			}
		}

		// Remove installer files (if exist)
		$languageFiles = glob($adminLanguageDir .'*/extension/payment/_mollie.php');
        foreach($languageFiles as $file) {
            if(file_exists($file)) {
                unlink($file);
            }
        }

        $languageFiles = glob($adminLanguageDir .'*/payment/_mollie.php');
        foreach($languageFiles as $file) {
            if(file_exists($file)) {
                unlink($file);
            }
        }

        if(file_exists($adminControllerDir . 'extension/payment/_mollie.php')) {
            unlink($adminControllerDir . 'extension/payment/_mollie.php');
        }

		if(file_exists($adminControllerDir . 'payment/_mollie.php')) {
            unlink($adminControllerDir . 'payment/_mollie.php');
        }
	}

	public function delTree($dir) {
		$files = array_diff(scandir($dir), array('.','..'));
	    foreach ($files as $file) {
	      (is_dir("$dir/$file")) ? $this->delTree("$dir/$file") : unlink("$dir/$file");
	    }
	    return rmdir($dir);
	}

	private function getStores() {
		// multi-stores management
		$this->load->model('setting/store');
		$stores = array();
		$stores[0] = array(
			'store_id' => 0,
			'name'     => $this->config->get('config_name')
		);

		$_stores = $this->model_setting_store->getStores();

		foreach ($_stores as $store) {
			$stores[$store['store_id']] = array(
				'store_id' => $store['store_id'],
				'name'     => $store['name']
			);
		}

		return $stores;
	}

	/**
	 * Insert variables that are added in later versions.
	*/
	public function updateSettings() {
		$code = $this->mollieHelper->getModuleCode();
        $stores = $this->getStores();
        $vars = array(
        	'default_currency' => 'DEF' // variable => default value
        );
        foreach($stores as $store) {
        	$storeData = array();
        	$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "setting WHERE store_id = '".$store['store_id']."'");
			
			foreach ($query->rows as $setting) {
				if (!$setting['serialized']) {
					$storeData[$setting["key"]] = $setting['value'];
				} else if (version_compare(VERSION, '2.1', '>=')) {
					$storeData[$setting["key"]] = json_decode($setting['value'], true);
				} else {
					$storeData[$setting["key"]] = unserialize($setting['value']);
				}
			}
        	foreach($vars as $key=>$value) {
        		if (!isset($storeData[$code . '_' . $key])) {
					if (version_compare(VERSION, '2', '>=')) {
			            $_code = 'code';
			        } else {
			            $_code = 'group';
			        }

			        if (!is_array($value)) {
			            $this->db->query("INSERT INTO " . DB_PREFIX . "setting SET store_id = '" . (int)$store['store_id'] . "', `" . $_code . "` = '" . $this->db->escape($code) . "', `key` = '" . $this->db->escape($code . '_' . $key) . "', `value` = '" . $this->db->escape($value) . "'");
			        } else {
						if (version_compare(VERSION, '2', '>=')) {
							$value = json_encode($value, true);
						} else {
							$value = serialize($value);
						}
			            $this->db->query("INSERT INTO " . DB_PREFIX . "setting SET store_id = '" . (int)$store['store_id'] . "', `" . $_code . "` = '" . $this->db->escape($code) . "', `key` = '" . $this->db->escape($code . '_' . $key) . "', `value` = '" . $this->db->escape($value) . "', serialized = '1'");
			        }
				}
        	}
        }
	}

	/**
	 * Trigger installation of all Mollie modules.
	 */
	protected function installAllModules () {
		// Load models.
		if(version_compare(VERSION, '3.0', '>=') || version_compare(VERSION, '2.0', '<')) {
			$this->load->model('setting/extension');
			$model = 'model_setting_extension';
		} else {
			$this->load->model('extension/extension');
			$model = 'model_extension_extension';
		}
		
		$user_id = $this->getUserId();

		foreach ($this->mollieHelper->MODULE_NAMES as $module_name) {
			$extensions = $this->{$model}->getInstalled("payment");
			
			// Install extension.
			$this->{$model}->install("payment", "mollie_" . $module_name);

			// First remove permissions to avoid memory overflow
			if (version_compare(VERSION, '2.0.0.0', '<')) {
				$this->removePermission($user_id, "access", "payment/mollie_" . $module_name);
				$this->removePermission($user_id, "access", "extension/payment/mollie_" . $module_name);
				$this->removePermission($user_id, "modify", "payment/mollie_" . $module_name);
				$this->removePermission($user_id, "modify", "extension/payment/mollie_" . $module_name);
			} else {
				$this->model_user_user_group->removePermission($user_id, "access", "payment/mollie_" . $module_name);
				$this->model_user_user_group->removePermission($user_id, "access", "extension/payment/mollie_" . $module_name);
				$this->model_user_user_group->removePermission($user_id, "modify", "payment/mollie_" . $module_name);
				$this->model_user_user_group->removePermission($user_id, "modify", "extension/payment/mollie_" . $module_name);
			}			
			
			// Set permissions.
			$this->model_user_user_group->addPermission($user_id, "access", "payment/mollie_" . $module_name);
			$this->model_user_user_group->addPermission($user_id, "access", "extension/payment/mollie_" . $module_name);
			$this->model_user_user_group->addPermission($user_id, "modify", "payment/mollie_" . $module_name);
			$this->model_user_user_group->addPermission($user_id, "modify", "extension/payment/mollie_" . $module_name);
		}

		// Install Mollie Payment Fee Total
		$extensions = $this->{$model}->getInstalled("total");
		if (!in_array("mollie_payment_fee", $extensions)) {
			$this->{$model}->install("total", "mollie_payment_fee");

			// First remove permissions to avoid memory overflow
			if (version_compare(VERSION, '2.0.0.0', '<')) {
				$this->removePermission($user_id, "access", "total/mollie_payment_fee");
				$this->removePermission($user_id, "access", "extension/total/mollie_payment_fee");
				$this->removePermission($user_id, "modify", "total/mollie_payment_fee");
				$this->removePermission($user_id, "modify", "extension/total/mollie_payment_fee");
			} else {
				$this->model_user_user_group->removePermission($user_id, "access", "total/mollie_payment_fee");
				$this->model_user_user_group->removePermission($user_id, "access", "extension/total/mollie_payment_fee");
				$this->model_user_user_group->removePermission($user_id, "modify", "total/mollie_payment_fee");
				$this->model_user_user_group->removePermission($user_id, "modify", "extension/total/mollie_payment_fee");
			}			
			
			// Set permissions.
			$this->model_user_user_group->addPermission($user_id, "access", "total/mollie_payment_fee");
			$this->model_user_user_group->addPermission($user_id, "access", "extension/total/mollie_payment_fee");
			$this->model_user_user_group->addPermission($user_id, "modify", "total/mollie_payment_fee");
			$this->model_user_user_group->addPermission($user_id, "modify", "extension/total/mollie_payment_fee");
		}
	}

	public function removePermission($user_id, $type, $page) {
		$user_query = $this->db->query("SELECT DISTINCT user_group_id FROM " . DB_PREFIX . "user WHERE user_id = '" . (int)$user_id . "'");

		if ($user_query->num_rows) {
			$user_group_query = $this->db->query("SELECT DISTINCT * FROM " . DB_PREFIX . "user_group WHERE user_group_id = '" . (int)$user_query->row['user_group_id'] . "'");

			if ($user_group_query->num_rows) {
				$data = unserialize($user_group_query->row['permission']);

				$data[$type] = array_diff($data[$type], array($page));

				$this->db->query("UPDATE " . DB_PREFIX . "user_group SET permission = '" . serialize($data) . "' WHERE user_group_id = '" . (int)$user_query->row['user_group_id'] . "'");
			}
		}
	}

	/**
	 * The method is executed by OpenCart when the Payment module is uninstalled from the admin. It will not drop the Mollie
	 * table at this point - we want to allow the user to toggle payment modules without losing their settings.
	 *
	 * @return void
	 */
	public function uninstall () {
		$this->uninstallAllModules();
	}

	/**
	 * Trigger removal of all Mollie modules.
	 */
	protected function uninstallAllModules () {
		if(version_compare(VERSION, '3.0', '>=') || version_compare(VERSION, '2.0', '<')) {
			$this->load->model('setting/extension');
			$model = 'model_setting_extension';
		} else {
			$this->load->model('extension/extension');
			$model = 'model_extension_extension';
		}

		foreach ($this->mollieHelper->MODULE_NAMES as $module_name) {
			$this->{$model}->uninstall("payment", "mollie_" . $module_name);
		}
	}

	//Delete deprecated method data from setting
	public function clearData() {
		foreach (DEPRECATED_METHODS as $method) {
			$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "setting WHERE `key` LIKE '%$method%'");
			if ($query->num_rows > 0) {
				$this->db->query("DELETE FROM " . DB_PREFIX . "setting WHERE `key` LIKE '%$method%'");
			}
		}
	}

	private function removePrefix($input, $prefix) {
		$result = [];
        $prefixLen = strlen($prefix);
        foreach ($input as $key => $val) {
            if (substr($key, 0, $prefixLen) == $prefix) {
                $key = substr($key, $prefixLen);
                $result[$key] = $val;
            }
        }
        return $result;
	}

	public function addPrefix($prefix, $input) {
        $result = [];
        foreach ($input as $val) {
            $result[] = $prefix . $val;
        }
        return $result;
    }

	/**
	 * Render the payment method's settings page.
	 */
	public function index () {
		// Put old settings back (This may be removed in next version)
		$this->load->model('setting/setting');
		if (isset($this->session->data['mollie_settings'])) {
			foreach ($this->session->data['mollie_settings'] as $storeID => $value) {
				$this->model_setting_setting->editSetting($this->mollieHelper->getModuleCode(), $value, $storeID);
			}

			unset($this->session->data['mollie_settings']);
		}

		// Double check for database and permissions
		$this->install();
		// Load essential models
		$this->load->model("localisation/order_status");
		$this->load->model("localisation/geo_zone");
		$this->load->model("localisation/language");
		$this->load->model("localisation/currency");
		$this->load->model('setting/setting');
		$this->load->model('localisation/tax_class');
		// Double-check if clean-up has been done - For upgrades
		if (null === $this->config->get($this->mollieHelper->getModuleCode() . '_version')) {
			if(version_compare(VERSION, '1.5.6.4', '<=')) {
	            $code = 'group';
	        } else {
	            $code = 'code';
	        }
			$this->db->query("INSERT INTO " . DB_PREFIX . "setting SET store_id = '0', `" . $code . "` = '" . $this->db->escape($this->mollieHelper->getModuleCode()) . "', `key` = '" . $this->db->escape($this->mollieHelper->getModuleCode() . '_version') . "', `value` = '" . $this->db->escape(MOLLIE_VERSION) . "'");
		} elseif (version_compare($this->config->get($this->mollieHelper->getModuleCode() . '_version'), MOLLIE_VERSION, '<')) {
			$this->model_setting_setting->editSettingValue($this->mollieHelper->getModuleCode(), $this->mollieHelper->getModuleCode() . '_version', MOLLIE_VERSION);
		}

		//Also delete data related to deprecated modules from settings
		$this->clearData();

		// Preserve Payment Fee Setting
		$this->savePaymentFeeSettings();

		// Update settings with newly added variables
		$this->updateSettings();

		//Load language data
		$data = array("version" => MOLLIE_RELEASE);

		// Manage mod files
		$data['modFilesError'] = false;
		$modFiles = $this->checkModFiles();

		if ($modFiles != 'true') {
			$data['modFilesError'] = html_entity_decode($modFiles, ENT_QUOTES, 'UTF-8');
		}

		if (version_compare(VERSION, '2.3', '>=')) {
	      $this->load->language('extension/payment/mollie');
	    } else {
	      $this->load->language('payment/mollie');
	    }
		$this->data = $data;		
		$code = $this->mollieHelper->getModuleCode();

		if (($this->request->server['REQUEST_METHOD'] == 'POST')) {
			$redirect = true;
            $stores = $this->getStores();
            foreach ($stores as $store) {
            	// Set payment method title to default if not provided
            	foreach ($this->mollieHelper->MODULE_NAMES as $module_name) {
            		$desc = $this->request->post[$store["store_id"] . '_' . $code . '_' . $module_name . '_description'];
            		foreach ($this->model_localisation_language->getLanguages() as $language) {
            			if (empty($desc[$language['language_id']]['title'])) {
            				$this->request->post[$store["store_id"] . '_' . $code . '_' . $module_name . '_description'][$language['language_id']]['title'] = $this->request->post[$store["store_id"] . '_' . $code . '_' . $module_name . '_name'];
            			}
            		}
            	}

            	if(count($stores) > 1) {
	                $this->model_setting_setting->editSetting($code, $this->removePrefix($this->request->post, $store["store_id"] . "_"), $store["store_id"]);
            	} else {
            		if ($this->validate($store["store_id"])) {
		                $this->model_setting_setting->editSetting($code, $this->removePrefix($this->request->post, $store["store_id"] . "_"), $store["store_id"]);
	            	}
	            	else {
	            		$redirect = false;
	            	}
            	}
            }

            if ($redirect) {
				// Unset payment methods session in frontend
				unset($this->session->data['mollie_allowed_methods']);

            	$this->session->data['success'] = $this->language->get('text_success');
            	if (version_compare(VERSION, '3', '>=')) {
					$this->response->redirect($this->url->link('marketplace/extension', 'type=payment&' . $this->token, 'SSL'));
				} elseif (version_compare(VERSION, '2.3', '>=')) {
					$this->response->redirect($this->url->link('extension/extension', 'type=payment&' . $this->token, 'SSL'));
				} elseif (version_compare(VERSION, '2.0', '>=')) {
					$this->response->redirect($this->url->link('extension/payment', $this->token, 'SSL'));
				} else {
					$this->redirect($this->url->link('extension/payment', $this->token, 'SSL'));
				}
            }
        }

        $this->document->setTitle(strip_tags($this->language->get('heading_title')));
        //Set form variables
        $paymentDesc = array();
        $paymentImage = array();
        $paymentStatus = array();
        $paymentSortOrder = array();
        $paymentGeoZone = array();
        $paymentTotalMin = array();
        $paymentTotalMax = array();
        $paymentAPIToUse = array();

        foreach ($this->mollieHelper->MODULE_NAMES as $module_name) {
        	$paymentDesc[]  	= $code . '_' . $module_name . '_description';
        	$paymentImage[] 	= $code . '_' . $module_name . '_image';
        	$paymentStatus[] 	= $code . '_' . $module_name . '_status';
        	$paymentSortOrder[] = $code . '_' . $module_name . '_sort_order';
        	$paymentGeoZone[] 	= $code . '_' . $module_name . '_geo_zone';
        	$paymentTotalMin[]  = $code . '_' . $module_name . '_total_minimum';
        	$paymentTotalMax[]  = $code . '_' . $module_name . '_total_maximum';
        	$paymentAPIToUse[]  = $code . '_' . $module_name . '_api_to_use';
		}

        $fields = array("show_icons", "show_order_canceled_page", "description", "api_key", "ideal_processing_status_id", "ideal_expired_status_id", "ideal_canceled_status_id", "ideal_failed_status_id", "ideal_pending_status_id", "ideal_shipping_status_id", "create_shipment_status_id", "ideal_refund_status_id", "create_shipment", "payment_screen_language", "debug_mode", "mollie_component", "mollie_component_css_base", "mollie_component_css_valid", "mollie_component_css_invalid", "default_currency", "recurring_email", "align_icons", "single_click_payment", "order_expiry_days", "ideal_partial_refund_status_id");

        $settingFields = $this->addPrefix($code . '_', $fields);

        $storeFormFields = array_merge($settingFields, $paymentDesc, $paymentImage, $paymentStatus, $paymentSortOrder, $paymentGeoZone, $paymentTotalMin, $paymentTotalMax, $paymentAPIToUse);

        $data['stores'] = $this->getStores();

        //API key not required for multistores
        $data['api_required'] = true;
        
        if(count($data['stores']) > 1) {
        	$data['api_required'] = false;
        }

        $data['breadcrumbs'] = array();

   		$data['breadcrumbs'][] = array(
	        'text'      => $this->language->get('text_home'),
	        'href'      => $this->url->link('common/home', $this->token, 'SSL'),
	      	'separator' => false
   		);

		if (version_compare(VERSION, '3', '>=')) {
			$extension_link = $this->url->link('marketplace/extension', 'type=payment&' . $this->token, 'SSL');
		} elseif (version_compare(VERSION, '2.3', '>=')) {
			$extension_link = $this->url->link('extension/extension', 'type=payment&' . $this->token, 'SSL');
		} else {
			$extension_link = $this->url->link('extension/payment', $this->token, 'SSL');
		}

		$data['heading_title'] = $this->language->get('heading_title');
		$data['text_yes'] = $this->language->get('text_yes');
		$data['text_no'] = $this->language->get('text_no');
		$data['text_enabled'] = $this->language->get('text_enabled');
		$data['text_disabled'] = $this->language->get('text_disabled');
		$data['text_edit'] = $this->language->get('text_edit');
		$data['text_payment'] = $this->language->get('text_payment');
		$data['text_activate_payment_method'] = $this->language->get('text_activate_payment_method');
		$data['text_no_status_id'] = $this->language->get('text_no_status_id');
		$data['text_creditcard_required'] = $this->language->get('text_creditcard_required');
		$data['text_mollie_api'] = $this->language->get('text_mollie_api');
		$data['text_mollie_app'] = $this->language->get('text_mollie_app');
		$data['text_general'] = $this->language->get('text_general');
		$data['text_enquiry'] = $this->language->get('text_enquiry');
		$data['text_update_message'] = $this->language->get('text_update_message');
		$data['text_default_currency'] = $this->language->get('text_default_currency');
		$data['text_custom_css'] = $this->language->get('text_custom_css');
		$data['text_contact_us'] = $this->language->get('text_contact_us');
		$data['text_bg_color'] = $this->language->get('text_bg_color');
		$data['text_color'] = $this->language->get('text_color');
		$data['text_font_size'] = $this->language->get('text_font_size');
		$data['text_other_css'] = $this->language->get('text_other_css');
		$data['text_module_by'] = $this->language->get('text_module_by');
		$data['text_mollie_support'] = $this->language->get('text_mollie_support');
		$data['text_contact'] = $this->language->get('text_contact');
		$data['text_allowed_variables'] = $this->language->get('text_allowed_variables');
		$data['text_browse'] = $this->language->get('text_browse');
		$data['text_clear'] = $this->language->get('text_clear');
		$data['text_image_manager'] = $this->language->get('text_image_manager');
		$data['text_create_shipment_automatically'] = $this->language->get('text_create_shipment_automatically');
		$data['text_create_shipment_on_status'] = $this->language->get('text_create_shipment_on_status');
		$data['text_create_shipment_on_order_complete'] = $this->language->get('text_create_shipment_on_order_complete');
		$data['text_log_success'] = $this->language->get('text_log_success');
		$data['text_log_list'] = $this->language->get('text_log_list');
		$data['text_all_zones'] = $this->language->get('text_all_zones');
		$data['text_missing_api_key'] = $this->language->get('text_missing_api_key');
		$data['text_left'] = $this->language->get('text_left');
		$data['text_right'] = $this->language->get('text_right');
		$data['text_more'] = $this->language->get('text_more');
		$data['text_none'] = $this->language->get('text_none');
		$data['text_select'] = $this->language->get('text_select');
		$data['text_no_maximum_limit'] = $this->language->get('text_no_maximum_limit');
		$data['text_standard_total'] = $this->language->get('text_standard_total');
		$data['text_advance_option'] = $this->language->get('text_advance_option');
		$data['text_payment_api'] = $this->language->get('text_payment_api');
		$data['text_order_api'] = $this->language->get('text_order_api');
		$data['text_info_orders_api'] = $this->language->get('text_info_orders_api');

		$data['title_global_options'] = $this->language->get('title_global_options');
		$data['title_payment_status'] = $this->language->get('title_payment_status');
		$data['title_mod_about'] = $this->language->get('title_mod_about');
		$data['footer_text'] = $this->language->get('footer_text');
		$data['title_mail'] = $this->language->get('title_mail');

		$data['name_mollie_banktransfer'] = $this->language->get('name_mollie_banktransfer');
		$data['name_mollie_belfius'] = $this->language->get('name_mollie_belfius');
		$data['name_mollie_creditcard'] = $this->language->get('name_mollie_creditcard');
		$data['name_mollie_ideal'] = $this->language->get('name_mollie_ideal');
		$data['name_mollie_kbc'] = $this->language->get('name_mollie_kbc');
		$data['name_mollie_bancontact'] = $this->language->get('name_mollie_bancontact');
		$data['name_mollie_paypal'] = $this->language->get('name_mollie_paypal');
		$data['name_mollie_paysafecard'] = $this->language->get('name_mollie_paysafecard');
		$data['name_mollie_sofort'] = $this->language->get('name_mollie_sofort');
		$data['name_mollie_giftcard'] = $this->language->get('name_mollie_giftcard');
		$data['name_mollie_eps'] = $this->language->get('name_mollie_eps');
		$data['name_mollie_giropay'] = $this->language->get('name_mollie_giropay');
		$data['name_mollie_klarnapaylater'] = $this->language->get('name_mollie_klarnapaylater');
		$data['name_mollie_klarnapaynow'] = $this->language->get('name_mollie_klarnapaynow');
		$data['name_mollie_klarnasliceit'] = $this->language->get('name_mollie_klarnasliceit');
		$data['name_mollie_przelewy24'] = $this->language->get('name_mollie_przelewy24');
		$data['name_mollie_applepay'] = $this->language->get('name_mollie_applepay');
		$data['name_mollie_in3'] = $this->language->get('name_mollie_in3');
		// Deprecated names
		$data['name_mollie_bitcoin'] = $this->language->get('name_mollie_bitcoin');
		$data['name_mollie_mistercash'] = $this->language->get('name_mollie_mistercash');

		$data['entry_payment_method'] = $this->language->get('entry_payment_method');
		$data['entry_activate'] = $this->language->get('entry_activate');
		$data['entry_sort_order'] = $this->language->get('entry_sort_order');
		$data['entry_api_key'] = $this->language->get('entry_api_key');
		$data['entry_description'] = $this->language->get('entry_description');
		$data['entry_show_icons'] = $this->language->get('entry_show_icons');
		$data['entry_align_icons'] = $this->language->get('entry_align_icons');
		$data['entry_show_order_canceled_page'] = $this->language->get('entry_show_order_canceled_page');
		$data['entry_geo_zone'] = $this->language->get('entry_geo_zone');
		$data['entry_payment_screen_language'] = $this->language->get('entry_payment_screen_language');
		$data['entry_name'] = $this->language->get('entry_name');
		$data['entry_email'] = $this->language->get('entry_email');
		$data['entry_subject'] = $this->language->get('entry_subject');
		$data['entry_enquiry'] = $this->language->get('entry_enquiry');
		$data['entry_debug_mode'] = $this->language->get('entry_debug_mode');
		$data['entry_mollie_component'] = $this->language->get('entry_mollie_component');
		$data['entry_test_mode'] = $this->language->get('entry_test_mode');
		$data['entry_mollie_component_base'] = $this->language->get('entry_mollie_component_base');
		$data['entry_mollie_component_valid'] = $this->language->get('entry_mollie_component_valid');
		$data['entry_mollie_component_invalid'] = $this->language->get('entry_mollie_component_invalid');
		$data['entry_default_currency'] = $this->language->get('entry_default_currency');
		$data['entry_email_subject'] = $this->language->get('entry_email_subject');
		$data['entry_email_body'] = $this->language->get('entry_email_body');
		$data['entry_title'] = $this->language->get('entry_title');
		$data['entry_image'] = $this->language->get('entry_image');
		$data['entry_module'] = $this->language->get('entry_module');
		$data['entry_mod_status'] = $this->language->get('entry_mod_status');
		$data['entry_comm_status'] = $this->language->get('entry_comm_status');
		$data['entry_support'] = $this->language->get('entry_support');
		$data['entry_pending_status'] = $this->language->get('entry_pending_status');
		$data['entry_failed_status'] = $this->language->get('entry_failed_status');
		$data['entry_canceled_status'] = $this->language->get('entry_canceled_status');
		$data['entry_expired_status'] = $this->language->get('entry_expired_status');
		$data['entry_processing_status'] = $this->language->get('entry_processing_status');
		$data['entry_refund_status'] = $this->language->get('entry_refund_status');
		$data['entry_shipping_status'] = $this->language->get('entry_shipping_status');
		$data['entry_shipment'] = $this->language->get('entry_shipment');
		$data['entry_create_shipment_status'] = $this->language->get('entry_create_shipment_status');
		$data['entry_create_shipment_on_order_complete'] = $this->language->get('entry_create_shipment_on_order_complete');
		$data['entry_single_click_payment'] = $this->language->get('entry_single_click_payment');
		$data['entry_order_expiry_days'] = $this->language->get('entry_order_expiry_days');
		$data['entry_partial_refund_status'] = $this->language->get('entry_partial_refund_status');
		$data['entry_amount'] = $this->language->get('entry_amount');
		$data['entry_payment_fee'] = $this->language->get('entry_payment_fee');
		$data['entry_payment_fee_tax_class'] = $this->language->get('entry_payment_fee_tax_class');
		$data['entry_total'] = $this->language->get('entry_total');
		$data['entry_minimum'] = $this->language->get('entry_minimum');
		$data['entry_maximum'] = $this->language->get('entry_maximum');
		$data['entry_api_to_use'] = $this->language->get('entry_api_to_use');
		$data['entry_status'] = $this->language->get('entry_status');

		$data['error_order_expiry_days'] = $this->language->get('error_order_expiry_days');
		
		$data['help_view_profile'] = $this->language->get('help_view_profile');
		$data['help_status'] = $this->language->get('help_status');
		$data['help_api_key'] = $this->language->get('help_api_key');
		$data['help_description'] = $this->language->get('help_description');
		$data['help_show_icons'] = $this->language->get('help_show_icons');
		$data['help_show_order_canceled_page'] = $this->language->get('help_show_order_canceled_page');
		$data['help_apple_pay'] = $this->language->get('help_apple_pay');
		$data['help_mollie_component'] = $this->language->get('help_mollie_component');
		$data['help_shipment'] = $this->language->get('help_shipment');
		$data['help_single_click_payment'] = $this->language->get('help_single_click_payment');
		$data['help_total'] = $this->language->get('help_total');
		
		$data['button_save'] = $this->language->get('button_save');
		$data['button_cancel'] = $this->language->get('button_cancel');
		$data['button_update'] = $this->language->get('button_update');
		$data['button_download'] = $this->language->get('button_download');
		$data['button_clear'] = $this->language->get('button_clear');
		$data['button_submit'] = $this->language->get('button_submit');
		$data['button_advance_option'] = $this->language->get('button_advance_option');
		$data['button_save_close'] = $this->language->get('button_save_close');
      
   		$data['breadcrumbs'][] = array(
	       	'text'      => $this->language->get('text_payment'),
	        'href'      => $extension_link,
	      	'separator' => ' :: '
   		);
		
   		$data['breadcrumbs'][] = array(
	       	'text'      => strip_tags($this->language->get('heading_title')),
	        'href'      => (version_compare(VERSION, '2.3', '>=')) ? $this->url->link('extension/payment/mollie_' . static::MODULE_NAME, $this->token, true) : $this->url->link('payment/mollie_' . static::MODULE_NAME, $this->token, 'SSL'),
	        'separator' => ' :: '
   		);
		
		$data['action'] = (version_compare(VERSION, '2.3', '>=')) ? $this->url->link('extension/payment/mollie_' . static::MODULE_NAME, $this->token, true) : $this->url->link('payment/mollie_' . static::MODULE_NAME, $this->token, 'SSL');
		
		$data['cancel'] = $extension_link;

		// Set data for template
        $data['module_name']        = static::MODULE_NAME;
        $data['api_check_url']      = $this->url->link("payment/mollie_" . static::MODULE_NAME . "/validate_api_key", $this->token, 'SSL');
        $data['entry_version']      = $this->language->get("entry_version") . " " . MOLLIE_VERSION;
        $data['code']               = $code;
		$data['token']          	= $this->token;

		$data['update_url']         = ($this->getUpdateUrl()) ? $this->getUpdateUrl()['updateUrl'] : '';
		if (version_compare(phpversion(), MollieHelper::MIN_PHP_VERSION, "<")) {
        	$data['error_min_php_version'] = sprintf($this->language->get('error_min_php_version'), MollieHelper::MIN_PHP_VERSION);
		} else {
        	$data['error_min_php_version'] = '';
		}

		if ($this->getUpdateUrl()) {
			if (version_compare(phpversion(), MollieHelper::NEXT_PHP_VERSION, "<")) {
				$data['text_update'] = sprintf($this->language->get('text_update_message_warning'), $this->getUpdateUrl()['updateVersion'], MollieHelper::NEXT_PHP_VERSION, $this->getUpdateUrl()['updateVersion']);
				$data['module_update'] = false;
			} else {
				$data['text_update'] = sprintf($this->language->get('text_update_message'), $this->getUpdateUrl()['updateVersion'], $data['update_url'], $this->getUpdateUrl()['updateVersion']);
				$data['module_update'] = true;
			}
		}

		if (isset($_COOKIE["hide_mollie_update_message_version"]) && ($_COOKIE["hide_mollie_update_message_version"] == $this->getUpdateUrl()['updateVersion'])) {
			$data['text_update'] = '';
		}
		
		$data['geo_zones']			= $this->model_localisation_geo_zone->getGeoZones();
		$data['order_statuses']		= $this->model_localisation_order_status->getOrderStatuses();
		$data['languages']			= $this->model_localisation_language->getLanguages();
		foreach ($data['languages'] as &$language) {
	      if (version_compare(VERSION, '2.2', '>=')) {
	        $language['image'] = 'language/'.$language['code'].'/'.$language['code'].'.png';
	      } else {
	        $language['image'] = 'view/image/flags/'. $language['image'];
	      }
	    }

		$data['currencies']			= $this->model_localisation_currency->getCurrencies();
		$data['tax_classes']        = $this->model_localisation_tax_class->getTaxClasses();

		$this->load->model('tool/image');

		if(version_compare(VERSION, '2.0.2.0', '>=')) {
			$no_image = 'no_image.png';
		} else {
			$no_image = 'no_image.jpg';
		}

		$data['placeholder'] = $this->model_tool_image->resize($no_image, 100, 100);

		if(isset($this->session->data['success'])) {
			$data['success'] = $this->session->data['success'];
			$this->session->data['success'] = '';
		} else {
			$data['success'] = '';
		}

		if(isset($this->session->data['warning'])) {
			$data['warning'] = $this->session->data['warning'];
			$this->session->data['warning'] = '';
		} else {
			$data['warning'] = '';
		}

		if(isset($this->error['warning'])) {
			$data['error_warning'] = $this->error['warning'];
		} else {
			$data['error_warning'] = '';
		}

		$description = array();
		foreach ($data['languages'] as $_language) {
			$description[$_language['language_id']]['title'] = "Order %";
		}

		// Load global settings. Some are prefixed with mollie_ideal_ for legacy reasons.
		$settings = array(
			$code . "_api_key"                    				=> NULL,
			$code . "_description"          					=> $description,
			$code . "_show_icons"                 				=> FALSE,
			$code . "_align_icons"                 				=> 'left',
			$code . "_show_order_canceled_page"   				=> FALSE,
			$code . "_ideal_pending_status_id"    				=> 1,
			$code . "_ideal_processing_status_id" 				=> 2,
			$code . "_ideal_canceled_status_id"   				=> 7,
			$code . "_ideal_failed_status_id"     				=> 10,
			$code . "_ideal_expired_status_id"    				=> 14,
			$code . "_ideal_shipping_status_id"   				=> 3,
			$code . "_create_shipment_status_id"  				=> 3,
			$code . "_ideal_refund_status_id"  					=> 11,
			$code . "_ideal_partial_refund_status_id"  			=> 11,
			$code . "_create_shipment"  		  				=> 3,
			$code . "_payment_screen_language"  		  		=> 'en-gb',
			$code . "_default_currency"  		  				=> 'DEF',
			$code . "_debug_mode"  		  						=> FALSE,
			$code . "_recurring_email"  		  				=> array(),
			$code . "_mollie_component"  		  				=> FALSE,
			$code . "_single_click_payment"  		  			=> FALSE,
			$code . "_order_expiry_days"  		  			    => 25,
			$code . "_mollie_component_css_base"  		  		=> array(
																	"background_color" => "#fff",
																	"color"			   => "#555",
																	"font_size"		   => "12px",
																	"other_css"		   => "border-width: 1px;\nborder-style: solid;\nborder-color: #ccc;\nborder-radius: 4px;\npadding: 8px;"
																	),
			$code . "_mollie_component_css_valid"  		  		=> array(
																	"background_color" => "#fff",
																	"color"			   => "#090",
																	"font_size"		   => "12px",
																	"other_css"		   => "border-width: 1px;\nborder-style: solid;\nborder-color: #090;\nborder-radius: 4px;\npadding: 8px;"
																	),
			$code . "_mollie_component_css_invalid"  		  	=> array(
																	"background_color" => "#fff",
																	"color"			   => "#f00",
																	"font_size"		   => "12px",
																	"other_css"		   => "border-width: 1px;\nborder-style: solid;\nborder-color: #f00;\nborder-radius: 4px;\npadding: 8px;"
																	),
		);

		// Check if order complete status is defined in store setting
		$data['is_order_complete_status'] = true;
		$data['order_complete_statuses'] = array();

		if((null == $this->config->get('config_complete_status')) && ($this->config->get('config_complete_status_id')) == '') {
			$data['is_order_complete_status'] = false;
		}

		foreach($data['stores'] as &$store) {
			$this->data = $this->model_setting_setting->getSetting($code, $store['store_id']);
			foreach ($settings as $setting_name => $default_value) {
				// Attempt to read from post
				if (isset($this->request->post[$store['store_id'] . '_' . $setting_name])) {
					$data['stores'][$store['store_id']][$setting_name] = $this->request->post[$store['store_id'] . '_' . $setting_name];
				} else { // Otherwise, attempt to get the setting from the database
					// same as $this->config->get() 
					$stored_setting = null;
					if(isset($this->data[$setting_name])) {
						$stored_setting = $this->data[$setting_name];						
					}

					if($stored_setting === NULL && $default_value !== NULL) {
						$data['stores'][$store['store_id']][$setting_name] = $default_value;
					} else {
						$data['stores'][$store['store_id']][$setting_name] = $stored_setting;
					}
				}
			}

			// Check which payment methods we can use with the current API key.
			$allowed_methods = array();
			try {
				$api_methods = $this->getAPIClient($store['store_id'])->methods->allActive(array('resource' => 'orders', 'includeWallets' => 'applepay'));
				foreach ($api_methods as $api_method) {
					$allowed_methods[$api_method->id] = array(
						"method" => $api_method->id,
						"minimumAmount" => $api_method->minimumAmount,
						"maximumAmount" => $api_method->maximumAmount
					);
				}
			} catch (Mollie\Api\Exceptions\ApiException $e) {
				// If we have an unauthorized request, our API key is likely invalid.
				if ($store[$code . '_api_key'] !== NULL && strpos($e->getMessage(), "Unauthorized request") !== false)
				{
					$data['error_api_key'] = $this->language->get("error_api_key_invalid");
				}
			}

			$data['store_data'][$store['store_id'] . '_' . $code . '_payment_methods'] = array();
			$data['store_data']['creditCardEnabled'] = false;

			foreach ($this->mollieHelper->MODULE_NAMES as $module_name) {
				$payment_method = array();

				$payment_method['name']    = $this->language->get("name_mollie_" . $module_name);
				$payment_method['icon']    = "../image/mollie/" . $module_name . "2x.png";
				$payment_method['allowed'] = array_key_exists($module_name, $allowed_methods);

				if(($module_name == 'creditcard') && $payment_method['allowed']) {
					$data['store_data']['creditCardEnabled'] = true;
				}

				// Make inactive if not allowed
				if (!$payment_method['allowed']) {
					$this->model_setting_setting->editSettingValue($code, $code . '_' . $module_name . '_status', 0, $store['store_id']);
				}

				// Load module specific settings.
				if (isset($this->data[$store['store_id'] . '_' . $code . '_' . $module_name . '_status'])) {
					$payment_method['status'] = ($this->data[$store['store_id'] . '_' . $code . '_' . $module_name . '_status'] == "on");
				} else {
					$payment_method['status'] = (bool) isset($this->data[$code . "_" . $module_name . "_status"]) ? $this->data[$code . "_" . $module_name . "_status"] : null;
				}

				if (isset($this->data[$store['store_id'] . '_' . $code . '_' . $module_name . '_description'])) {
					$payment_method['description'] = $this->data[$store['store_id'] . '_' . $code . '_' . $module_name . '_description'];
				} else {
					$payment_method['description'] = isset($this->data[$code . "_" . $module_name . "_description"]) ? $this->data[$code . "_" . $module_name . "_description"] : null;
				}

				if (isset($this->data[$store['store_id'] . '_' . $code . '_' . $module_name . '_image'])) {
					$payment_method['image'] = $this->data[$store['store_id'] . '_' . $code . '_' . $module_name . '_image'];
					if(!empty($this->data[$store['store_id'] . '_' . $code . '_' . $module_name . '_image'])) {
						$payment_method['thumb'] = $this->model_tool_image->resize($this->data[$store['store_id'] . '_' . $code . '_' . $module_name . '_image'], 100, 100);
					} else {
						$payment_method['thumb'] = $this->model_tool_image->resize($no_image, 100, 100);
					}					
				} else {
					$payment_method['image'] = isset($this->data[$code . "_" . $module_name . "_image"]) ? $this->data[$code . "_" . $module_name . "_image"] : null;
					$payment_method['thumb'] = (isset($this->data[$code . "_" . $module_name . "_image"]) && !empty($this->data[$code . "_" . $module_name . "_image"])) ? $this->model_tool_image->resize($this->data[$code . "_" . $module_name . "_image"], 100, 100) : $this->model_tool_image->resize($no_image, 100, 100);
				}

				if (isset($this->data[$store['store_id'] . '_' . $code . '_' . $module_name . '_sort_order'])) {
					$payment_method['sort_order'] = $this->data[$store['store_id'] . '_' . $code . '_' . $module_name . '_sort_order'];
				} else {
					$payment_method['sort_order'] = isset($this->data[$code . "_" . $module_name . "_sort_order"]) ? $this->data[$code . "_" . $module_name . "_sort_order"] : null;
				}

				if (isset($this->data[$store['store_id'] . '_' . $code . '_' . $module_name . '_geo_zone'])) {
					$payment_method['geo_zone'] = $this->data[$store['store_id'] . '_' . $code . '_' . $module_name . '_geo_zone'];
				} else {
					$payment_method['geo_zone'] = isset($this->data[$code . "_" . $module_name . "_geo_zone"]) ? $this->data[$code . "_" . $module_name . "_geo_zone"] : null;
				}

				if ($payment_method['allowed']) {
					$minimumAmount = $allowed_methods[$module_name]['minimumAmount']->value;
					$currency      = $allowed_methods[$module_name]['minimumAmount']->currency;
					$payment_method['minimumAmount'] =  sprintf($this->language->get('text_standard_total'), $this->currency->format($this->currency->convert($minimumAmount, $currency, $this->config->get('config_currency')), $currency));

					if (isset($this->data[$store['store_id'] . '_' . $code . '_' . $module_name . '_total_minimum'])) {
						$payment_method['total_minimum'] = $this->data[$store['store_id'] . '_' . $code . '_' . $module_name . '_total_minimum'];
					} elseif (isset($this->data[$code . "_" . $module_name . "_total_minimum"])) {
						$payment_method['total_minimum'] =  $this->data[$code . "_" . $module_name . "_total_minimum"];
					} else {
						$payment_method['total_minimum'] =  $this->numberFormat($this->currency->convert($minimumAmount, $currency, $this->config->get('config_currency')), $this->config->get('config_currency'));
					}

					if ($allowed_methods[$module_name]['maximumAmount']) {
						$maximumAmount = $allowed_methods[$module_name]['maximumAmount']->value;
						$currency      = $allowed_methods[$module_name]['maximumAmount']->currency;
						$payment_method['maximumAmount'] =  sprintf($this->language->get('text_standard_total'), $this->currency->format($this->currency->convert($maximumAmount, $currency, $this->config->get('config_currency')), $currency));
					} else {
						$payment_method['maximumAmount'] =  $this->language->get('text_no_maximum_limit');
					}				

					if (isset($this->data[$store['store_id'] . '_' . $code . '_' . $module_name . '_total_maximum'])) {
						$payment_method['total_maximum'] = $this->data[$store['store_id'] . '_' . $code . '_' . $module_name . '_total_maximum'];
					} elseif (isset($this->data[$code . "_" . $module_name . "_total_maximum"])) {
						$payment_method['total_maximum'] =  $this->data[$code . "_" . $module_name . "_total_maximum"];
					} else {
						$payment_method['total_maximum'] =  ($allowed_methods[$module_name]['maximumAmount']) ? $this->numberFormat($this->currency->convert($maximumAmount, $currency, $this->config->get('config_currency')), $this->config->get('config_currency')) : '';
					}
				}	
				
				if (isset($this->data[$store['store_id'] . '_' . $code . '_' . $module_name . '_api_to_use'])) {
					$payment_method['api_to_use'] = $this->data[$store['store_id'] . '_' . $code . '_' . $module_name . '_api_to_use'];
				} else {
					$payment_method['api_to_use'] = isset($this->data[$code . "_" . $module_name . "_api_to_use"]) ? $this->data[$code . "_" . $module_name . "_api_to_use"] : null;
				}

				$data['store_data'][$store['store_id'] . '_' . $code . '_payment_methods'][$module_name] = $payment_method;
			}

			$data['stores'][$store['store_id']]['entry_cstatus'] = $this->checkCommunicationStatus(isset($this->data[$code . '_api_key']) ? $this->data[$code . '_api_key'] : null);

			if(isset($this->error[$store['store_id']]['api_key'])) {
				$data['stores'][$store['store_id']]['error_api_key'] = $this->error[$store['store_id']]['api_key'];
			} else {
				$data['stores'][$store['store_id']]['error_api_key'] = '';
			}
			
		}

		$data['mollie_version'] = $this->config->get($code . '_version');
		$data['mod_file'] = $this->config->get($code . '_mod_file');

		$data['download'] = $this->url->link("payment/mollie_" . static::MODULE_NAME . "/download", $this->token, 'SSL');
		$data['clear'] = $this->url->link("payment/mollie_" . static::MODULE_NAME . "/clear", $this->token, 'SSL');

		$data['log'] = '';

		$file = DIR_LOGS . 'Mollie.log';

		if (file_exists($file)) {
			$size = filesize($file);

			if ($size >= 5242880) {
				$suffix = array(
					'B',
					'KB',
					'MB',
					'GB',
					'TB',
					'PB',
					'EB',
					'ZB',
					'YB'
				);

				$i = 0;

				while (($size / 1024) > 1) {
					$size = $size / 1024;
					$i++;
				}

				$data['error_warning'] = sprintf($this->language->get('error_log_warning'), basename($file), round(substr($size, 0, strpos($size, '.') + 4), 2) . $suffix[$i]);
			} else {
				$data['log'] = file_get_contents($file, FILE_USE_INCLUDE_PATH, null);
			}
		}

		$data['store_email'] = $this->config->get('config_email');

		if (version_compare(VERSION, '2', '>=')) {
			$data['header'] = $this->load->controller('common/header');
			$data['column_left'] = $this->load->controller('common/column_left');
			$data['footer'] = $this->load->controller('common/footer');
			
			if (version_compare(VERSION, '3', '>=')) {
				$this->config->set('template_engine', 'template');
				$this->response->setOutput($this->load->view('payment/mollie', $data));
			} else {
				$this->response->setOutput($this->load->view('payment/mollie.tpl', $data));
			}
		} else {
			$data['column_left'] = '';
			$this->data = &$data;
			$this->template = 'payment/mollie(max_1.5.6.4).tpl';
			$this->children = array(
				'common/header',
				'common/footer'
			);
      
			$this->response->setOutput($this->render());
		}
	}

    /**
     *
     */
    public function validate_api_key() {
    	if (version_compare(VERSION, '2.3', '>=')) {
	      $this->load->language('extension/payment/mollie');
	    } else {
	      $this->load->language('payment/mollie');
	    }
		$json = array(
			'error' => false,
			'invalid' => false,
			'valid' => false,
			'message' => '',
		);

		if (empty($this->request->get['key'])) {
			$json['invalid'] = true;
			$json['message'] = $this->language->get('error_no_api_client');
		} else {
			try {
				$client = $this->mollieHelper->getAPIClientForKey($this->request->get['key']);

				if (!$client) {
					$json['invalid'] = true;
					$json['message'] = $this->language->get('error_no_api_client');
				} else {
					$client->methods->all();

					$json['valid'] = true;
					$json['message'] = 'Ok.';
				}
			} catch (IncompatiblePlatform $e) {
				$json['error'] = true;
				$json['message'] = $e->getMessage() . ' ' . $this->language->get('error_api_help');
			} catch (ApiException $e) {
				$json['error'] = true;
				$json['message'] = sprintf($this->language->get('error_comm_failed'), htmlspecialchars($e->getMessage()), (isset($client) ? htmlspecialchars($client->getApiEndpoint()) : 'Mollie'));
			}
		}


		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	/**
	 * Check the post and check if the user has permission to edit the module settings
	 * @param int $store The store id
	 * @return bool
	 */
	private function validate ($store = 0) {
		$route = (version_compare(VERSION, '2.3', '>=')) ? 'extension/payment/mollie_' . static::MODULE_NAME : 'payment/mollie_' . static::MODULE_NAME;
		if (!$this->user->hasPermission("modify", $route)) {
			$this->error['warning'] = $this->language->get("error_permission");
		}

		if (!$this->request->post[$store . '_' . $this->mollieHelper->getModuleCode() . '_api_key']) {
			$this->error[$store]['api_key'] = $this->language->get("error_api_key");
		}
		
		return (count($this->error) == 0);
	}

	/**
	 * @param string|null
	 * @return string
	 */
	protected function checkCommunicationStatus ($api_key = null) {
		if (version_compare(VERSION, '2.3', '>=')) {
	      $this->load->language('extension/payment/mollie');
	    } else {
	      $this->load->language('payment/mollie');
	    }
		if (empty($api_key)) {
			return '<span style="color:red">' .  $this->language->get('error_no_api_key') . '</span>';
		}

		try {
			$client = $this->mollieHelper->getAPIClientForKey($api_key);

			if (!$client) {
				return '<span style="color:red">' . $this->language->get('error_no_api_client') . '</span>';
			}

			$client->methods->all();

			return '<span style="color: green">OK</span>';
		} catch (Mollie\Api\Exceptions\ApiException_IncompatiblePlatform $e) {
			return '<span style="color:red">' . $e->getMessage() . ' ' . $this->language->get('error_api_help') . '</span>';
		} catch (Mollie\Api\Exceptions\ApiException $e) {
			return '<span style="color:red">' . sprintf($this->language->get('error_comm_failed'), htmlspecialchars($e->getMessage()), (isset($client) ? htmlspecialchars($client->getApiEndpoint()) : 'Mollie')) . '</span>';				
		}
	}

	/**
	 * @return string
	 */
	private function getTokenUriPart() {
		if (isset($this->session->data['user_token'])) {
			return 'user_token=' . $this->session->data['user_token'];
		}

		return 'token=' . $this->session->data['token'];
	}

	private function getUserId() {
		$this->load->model('user/user_group');

		if (method_exists($this->user, 'getGroupId')) {
			return $this->user->getGroupId();
		}

		return $this->user->getId();
	}

	public function saveAPIKey() {
		$this->load->model('setting/setting');
		$store_id = $_POST['store_id'];
		$code = $this->mollieHelper->getModuleCode();

		$data = $this->model_setting_setting->getSetting($code, $store_id);
		$data[$code.'_api_key'] = $_POST['api_key'];
		
		$this->model_setting_setting->editSetting($code, $data, $store_id);
		return true;
	}

	private function getUpdateUrl() {
        $client = new mollieHttpClient();
        $info = $client->get(MOLLIE_VERSION_URL);
        if (isset($info["tag_name"]) && ($info["tag_name"] != MOLLIE_VERSION) && version_compare(MOLLIE_VERSION, $info["tag_name"], "<")) {
            $updateUrl = array(
                "updateUrl" => $this->url->link("payment/mollie_" . static::MODULE_NAME . "/update", $this->token, 'SSL'),
                "updateVersion" => $info["tag_name"]
            );

            return $updateUrl;
        }
        return false;
    }

    function update() {

		// Check for PHP version
		if (version_compare(phpversion(), MollieHelper::NEXT_PHP_VERSION, "<")) {
			if (version_compare(VERSION, '2.3', '>=')) {
				$this->response->redirect($this->url->link('extension/payment/mollie_' . static::MODULE_NAME, $this->token, true));
			} elseif (version_compare(VERSION, '2', '>=')) {
				$this->response->redirect($this->url->link('payment/mollie_' . static::MODULE_NAME, $this->token, 'SSL'));
			} else {
				$this->redirect($this->url->link('payment/mollie_' . static::MODULE_NAME, $this->token, 'SSL'));
			}
		}

        //get info
        $client = new mollieHttpClient();
        $info = $client->get(MOLLIE_VERSION_URL);

        //save tmp file
        $temp_file = MOLLIE_TMP . "/mollieUpdate.zip";
        $handle = fopen($temp_file, "w+");
		$content = $client->get($info["assets"][0]["browser_download_url"], false, false);
        fwrite($handle, $content);
        fclose($handle);


        //extract to temp dir
        $temp_dir = MOLLIE_TMP . "/mollieUpdate";
        if (class_exists("ZipArchive")) {
            $zip = new ZipArchive;
            $zip->open($temp_file);
            $zip->extractTo($temp_dir);
            $zip->close();
        } else {
            shell_exec("unzip " . $temp_file . " -d " . $temp_dir);
        }

        //find upload path

        $handle = opendir($temp_dir);
        $upload_dir = $temp_dir . "/upload";
        while ($file = readdir($handle)) {
            if ($file != "." && $file != ".." && is_dir($temp_dir . "/" . $file . "/upload")) {
                $upload_dir = $temp_dir . "/" . $file . "/upload";
                break;
            }
        }

        //copy files
        $handle = opendir($upload_dir);
        while ($file = readdir($handle)) {
            if ($file != "." && $file != "..") {
                $from = $upload_dir . "/" . $file;
                if ($file == "admin") {
                    $to = DIR_APPLICATION;
                } elseif ($file == "system") {
                    $to = DIR_SYSTEM;
                } else {
                    $to = DIR_CATALOG . "../" . $file . "/";
                }
                $this->cpy($from, $to);
            }

        }

        //cleanup
        unlink($temp_file);
        $this->rmDirRecursive($temp_dir);

        if (!$this->getUpdateUrl()) {
            $data = array("version" => MOLLIE_RELEASE);
            if (version_compare(VERSION, '2.3', '>=')) {
		      $this->load->language('extension/payment/mollie');
		    } else {
		      $this->load->language('payment/mollie');
		    }
            $this->session->data['success'] = sprintf($this->language->get('text_update_success'), MOLLIE_RELEASE);
        }

        //go back
        if (version_compare(VERSION, '2.3', '>=')) {
			$this->response->redirect($this->url->link('extension/payment/mollie_' . static::MODULE_NAME, $this->token, true));
		} elseif (version_compare(VERSION, '2', '>=')) {
			$this->response->redirect($this->url->link('payment/mollie_' . static::MODULE_NAME, $this->token, 'SSL'));
		} else {
			$this->redirect($this->url->link('payment/mollie_' . static::MODULE_NAME, $this->token, 'SSL'));
		}
    }

    public function rmDirRecursive($dir) {
        $files = array_diff(scandir($dir), array('.', '..'));
        foreach ($files as $file) {
            (is_dir("$dir/$file")) ? $this->rmDirRecursive("$dir/$file") : unlink("$dir/$file");
        }
        return rmdir($dir);
    }

    function cpy($source, $dest) {
        if (is_dir($source)) {
            $dir_handle = opendir($source);
            while ($file = readdir($dir_handle)) {
                if ($file != "." && $file != "..") {
                    if (is_dir($source . "/" . $file)) {
                        if (!is_dir($dest . "/" . $file)) {
                            mkdir($dest . "/" . $file);
                        }
                        $this->cpy($source . "/" . $file, $dest . "/" . $file);
                    } else {
                        copy($source . "/" . $file, $dest . "/" . $file);
                    }
                }
            }
            closedir($dir_handle);
        } else {
            copy($source, $dest);
        }
    }

    public function download() {
		if (version_compare(VERSION, '2.3', '>=')) {
	      $this->load->language('extension/payment/mollie');
	    } else {
	      $this->load->language('payment/mollie');
	    }

		$file = DIR_LOGS . 'Mollie.log';

		if (file_exists($file) && filesize($file) > 0) {
			$this->response->addHeader('Pragma:public');
			$this->response->addHeader('Expires:0');
			$this->response->addHeader('Content-Description:File Transfer');
			$this->response->addHeader('Content-Type:application/octet-stream');
			$this->response->addHeader('Content-Disposition:attachment; filename="' . $this->config->get('config_name') . '_' . date('Y-m-d_H-i-s', time()) . '_mollie_error.log"');
			$this->response->addHeader('Content-Transfer-Encoding:binary');

			$this->response->setOutput(file_get_contents($file, FILE_USE_INCLUDE_PATH, null));
		} else {
			$this->session->data['warning'] = sprintf($this->language->get('error_log_warning'), basename($file), '0B');

			if (version_compare(VERSION, '2.3', '>=')) {
				$this->response->redirect($this->url->link('extension/payment/mollie_' . static::MODULE_NAME, $this->token, true));
			} elseif (version_compare(VERSION, '2', '>=')) {
				$this->response->redirect($this->url->link('payment/mollie_' . static::MODULE_NAME, $this->token, 'SSL'));
			} else {
				$this->redirect($this->url->link('payment/mollie_' . static::MODULE_NAME, $this->token, 'SSL'));
			}
		}
	}
	
	public function clear() {
		if (version_compare(VERSION, '2.3', '>=')) {
	      $this->load->language('extension/payment/mollie');
	    } else {
	      $this->load->language('payment/mollie');
	    }

		$file = DIR_LOGS . 'Mollie.log';

		$handle = fopen($file, 'w+');

		fclose($handle);

		$this->session->data['success'] = $this->language->get('text_log_success');

		if (version_compare(VERSION, '2.3', '>=')) {
			$this->response->redirect($this->url->link('extension/payment/mollie_' . static::MODULE_NAME, $this->token, true));
		} elseif (version_compare(VERSION, '2', '>=')) {
			$this->response->redirect($this->url->link('payment/mollie_' . static::MODULE_NAME, $this->token, 'SSL'));
		} else {
			$this->redirect($this->url->link('payment/mollie_' . static::MODULE_NAME, $this->token, 'SSL'));
		}
	}

	public function sendMessage() {
		if (version_compare(VERSION, '2.3', '>=')) {
	      $this->load->language('extension/payment/mollie');
	    } else {
	      $this->load->language('payment/mollie');
	    }

		$json = array();

		if ($this->request->server['REQUEST_METHOD'] == 'POST') {
			if ((utf8_strlen($this->request->post['name']) < 3) || (utf8_strlen($this->request->post['name']) > 25)) {
				$json['error'] = $this->language->get('error_name');
			}

			if ((utf8_strlen($this->request->post['email']) > 96) || !filter_var($this->request->post['email'], FILTER_VALIDATE_EMAIL)) {
				$json['error'] = $this->language->get('error_email');
			}

			if (utf8_strlen($this->request->post['subject']) < 3) {
				$json['error'] = $this->language->get('error_subject');
			}

			if (utf8_strlen($this->request->post['enquiry']) < 25) {
				$json['error'] = $this->language->get('error_enquiry');
			}

			if (!isset($json['error'])) {
				$name = $this->request->post['name'];
				$email = $this->request->post['email'];
				$subject = $this->request->post['subject'];
				$enquiry = $this->request->post['enquiry'];
				$enquiry .= "<br>Opencart version : " . VERSION;
				if(version_compare(VERSION, '2', '<')) {
					$enquiry .= "<br>VQMod version : " . VQ_VERSION;
				}				
				$enquiry .= "<br>Mollie version : " . MOLLIE_VERSION;

				$mail = new Mail();
				$mail->protocol = $this->config->get('config_mail_protocol');
				$mail->parameter = $this->config->get('config_mail_parameter');
				$mail->smtp_hostname = $this->config->get('config_mail_smtp_hostname');
				$mail->smtp_username = $this->config->get('config_mail_smtp_username');
				$mail->smtp_password = html_entity_decode($this->config->get('config_mail_smtp_password'), ENT_QUOTES, 'UTF-8');
				$mail->smtp_port = $this->config->get('config_mail_smtp_port');
				$mail->smtp_timeout = $this->config->get('config_mail_smtp_timeout');
	
				$mail->setTo('support.mollie@qualityworks.eu');
				$mail->setFrom($email);
				$mail->setSender(html_entity_decode($name, ENT_QUOTES, 'UTF-8'));
				$mail->setSubject(html_entity_decode($subject, ENT_QUOTES, 'UTF-8'));
				$mail->setHtml($enquiry);

				$file = DIR_LOGS . 'Mollie.log';
				if (file_exists($file) && filesize($file) < 2147483648) {
					$mail->addAttachment($file);
				}

				$file = DIR_LOGS . 'error.log';
				if (file_exists($file) && filesize($file) < 2147483648) {
					$mail->addAttachment($file);
				}

				$mail->send();

				$json['success'] = $this->language->get('text_enquiry_success');
			}
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	public function numberFormat($amount, $currency) {
        $intCurrencies = array("ISK", "JPY");
        if(!in_array($currency, $intCurrencies)) {
            $formattedAmount = number_format((float)$amount, 2, '.', '');
        } else {
            $formattedAmount = number_format($amount, 0);
        }   
        return $formattedAmount;    
    }

	public function save() {
		if (version_compare(VERSION, '2.3', '>=')) {
			$this->load->language('extension/payment/mollie');
		} else {
			$this->load->language('payment/mollie');
		}
		$this->load->model('setting/setting');
		$this->load->model('localisation/language');

		$json = array();

		$code = $this->mollieHelper->getModuleCode();

		$stores = $this->getStores();
		foreach ($stores as $store) {
			// Set payment method title to default if not provided
			foreach ($this->mollieHelper->MODULE_NAMES as $module_name) {
				$desc = $this->request->post[$store["store_id"] . '_' . $code . '_' . $module_name . '_description'];
				foreach ($this->model_localisation_language->getLanguages() as $language) {
					if (empty($desc[$language['language_id']]['title'])) {
						$this->request->post[$store["store_id"] . '_' . $code . '_' . $module_name . '_description'][$language['language_id']]['title'] = $this->request->post[$store["store_id"] . '_' . $code . '_' . $module_name . '_name'];
					}
				}
			}
			$this->model_setting_setting->editSetting($code, $this->removePrefix($this->request->post, $store["store_id"] . "_"), $store["store_id"]);
		}

		$json['success'] = $this->language->get('text_success');

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	protected function savePaymentFeeSettings() {
		$code = "mollie";
		$code2 = "mollie_payment_fee";
		if (version_compare(VERSION, '3.0', '>=')) {
			$code = "payment_mollie";
			$code2 = "total_mollie_payment_fee";
		}
		if (version_compare(VERSION, '2.0', '>=')) {
			$q = $this->db->query("SELECT * FROM " . DB_PREFIX . "setting WHERE code = '" . $code2 . "' AND `key` = '" . $code2 . "_charge'");
		} else {
			$q = $this->db->query("SELECT * FROM " . DB_PREFIX . "setting WHERE `group` = '" . $code2 . "'  AND `key` = '" . $code2 . "_charge'");
		}
		
		if (!$q->num_rows) {
			if (version_compare(VERSION, '2.0', '>=')) {
				$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "setting WHERE code = '" . $code . "'");
			} else {
				$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "setting WHERE `group` = '" . $code . "'");
			}
	
			$results = $query->rows;
	
			$paymentFee = array();
			foreach ($this->mollieHelper->MODULE_NAMES as $module_name) {
				$key = $code . '_' . $module_name . '_payment_fee';
				foreach ($results as $result) {
					if (($result['key'] == $key) && !empty($result['value'])) {
						if (version_compare(VERSION, '2.1', '>=')) {
							$fee_setting = json_decode($result['value'], true);
						} else {
							$fee_setting = unserialize($result['value']);
						}
	
						if (!empty($fee_setting['amount'])) {
							$paymentFee[] = array(
								"description" => $fee_setting['description'],
								"payment_method" => $module_name,
								"cost" => $fee_setting['amount'],
								"customer_group_id" => $this->config->get('config_customer_group_id'),
								"geo_zone_id" => "0",
								"priority" => '',
							);
						}
	
						
					}
				}
			}
			
			if (!empty($paymentFee)) {
				if (version_compare(VERSION, '2.0', '>=')) {
					$this->db->query("INSERT INTO " . DB_PREFIX . "setting SET store_id = '0', code = '" . $this->db->escape($code2) . "', `key` = '" . $this->db->escape($code2 . '_charge') . "', `value` = '" . $this->db->escape(json_encode($paymentFee, true)) . "', serialized = '1'");
				} else {
					$this->db->query("INSERT INTO " . DB_PREFIX . "setting SET store_id = '0', `group` = '" . $this->db->escape($code2) . "', `key` = '" . $this->db->escape($code2 . '_charge') . "', `value` = '" . $this->db->escape(serialize($paymentFee)) . "', serialized = '1'");
				}
			}
		}
	}
}
