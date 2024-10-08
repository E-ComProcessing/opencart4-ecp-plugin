<?php
/*
 * Copyright (C) 2018 E-Comprocessing Ltd.
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * @author	  E-Comprocessing
 * @copyright   2018 E-Comprocessing Ltd.
 * @license	 http://opensource.org/licenses/gpl-2.0.php GNU General Public License, version 2 (GPL-2.0)
 */

namespace Opencart\Catalog\Model\Extension\Ecomprocessing\Payment\Ecomprocessing;

if (!class_exists('Genesis\Genesis', false)) {
	require DIR_STORAGE . 'vendor/genesisgateway/genesis_php/vendor/autoload.php';
}

use DateTime;
use Genesis\Api\Constants\Endpoints;
use Genesis\Api\Constants\Environments;
use Genesis\Api\Request\Wpf\Create;
use Genesis\Config;
use Genesis\Exceptions\InvalidArgument;
use Genesis\Genesis;
use Genesis\Api\Constants\Transaction\Parameters\Mobile\ApplePay\PaymentTypes as ApplePayPaymentTypes;
use Genesis\Api\Constants\Transaction\Parameters\Mobile\GooglePay\PaymentTypes as GooglePayPaymentTypes;
use Genesis\Api\Constants\Transaction\Parameters\Wallets\PayPal\PaymentTypes as PayPalPaymentTypes;
use Genesis\Api\Constants\Transaction\States;
use Genesis\Api\Constants\Transaction\Types;
use Opencart\Extension\Ecomprocessing\System\DbHelper;
use Opencart\Extension\Ecomprocessing\System\EcomprocessingHelper;
use Opencart\System\Engine\Model;

/**
 * Base Abstract Model for Method Models
 *
 * Class BaseModel
 *
 * @package Opencart\Catalog\Model\Extension\Ecomprocessing\Payment\Ecomprocessing
 *
 * @SuppressWarnings(PHPMD.ExcessiveClassLength)
 * @SuppressWarnings(PHPMD.ExcessiveClassComplexity)
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
abstract class BaseModel extends Model
{
	const GOOGLE_PAY_TRANSACTION_PREFIX     = Types::GOOGLE_PAY . '_';
	const GOOGLE_PAY_PAYMENT_TYPE_AUTHORIZE = GooglePayPaymentTypes::AUTHORIZE;
	const GOOGLE_PAY_PAYMENT_TYPE_SALE      = GooglePayPaymentTypes::SALE;

	const PAYPAL_TRANSACTION_PREFIX         = Types::PAY_PAL . '_';
	const PAYPAL_PAYMENT_TYPE_AUTHORIZE     = PayPalPaymentTypes::AUTHORIZE;
	const PAYPAL_PAYMENT_TYPE_SALE          = PayPalPaymentTypes::SALE;
	const PAYPAL_PAYMENT_TYPE_EXPRESS       = PayPalPaymentTypes::EXPRESS;

	const APPLE_PAY_TRANSACTION_PREFIX      = Types::APPLE_PAY . '_';
	const APPLE_PAY_PAYMENT_TYPE_AUTHORIZE  = ApplePayPaymentTypes::AUTHORIZE;
	const APPLE_PAY_PAYMENT_TYPE_SALE       = ApplePayPaymentTypes::SALE;

	/**
	 * Max. number of records of the cron log
	 */
	const MAX_CRON_LOG_SIZE = 50;

	/**
	 * OpenCart Order Status constants
	 * Reference: database table oc_order_status
	 */
	const OC_ORD_STATUS_PENDING           =  1;
	const OC_ORD_STATUS_PROCESSING        =  2;
	const OC_ORD_STATUS_SHIPPED           =  3;
	const OC_ORD_STATUS_COMPLETE          =  5;
	const OC_ORD_STATUS_CANCELED          =  7;
	const OC_ORD_STATUS_DENIED            =  8;
	const OC_ORD_STATUS_CANCELED_REVERSAL =  9;
	const OC_ORD_STATUS_FAILED            = 10;
	const OC_ORD_STATUS_REFUNDED          = 11;
	const OC_ORD_STATUS_REVERSED          = 12;
	const OC_ORD_STATUS_CHARGEBACK        = 13;
	const OC_ORD_STATUS_EXPIRED           = 14;
	const OC_ORD_STATUS_PROCESSED         = 15;
	const OC_ORD_STATUS_VOIDED            = 16;

	/**
	 * OpenCart Recurring Transactions Type constants
	 * Reference: ModelSaleRecurring::getRecurringTransactions class method
	 */
	const OC_REC_TXN_DATE_ADDED          = 0;
	const OC_REC_TXN_PAYMENT             = 1;
	const OC_REC_TXN_OUTSTANDING_PAYMENT = 2;
	const OC_REC_TXN_SKIPPED             = 3;
	const OC_REC_TXN_FAILED              = 4;
	const OC_REC_TXN_CANCELLED           = 5;
	const OC_REC_TXN_SUSPENDED           = 6;
	const OC_REC_TXN_SUSPENDED_FAILED    = 7;
	const OC_REC_TXN_OUTSTANDING_FAILED  = 8;
	const OC_REC_TXN_EXPIRED             = 9;

	/**
	 * OpenCart Order Recurring Status constants
	 * Reference: text_status_1 .. text_status_6 language definitions
	 */
	const OC_ORD_REC_STATUS_ACTIVE    = 1;
	const OC_ORD_REC_STATUS_INACTIVE  = 2;
	const OC_ORD_REC_STATUS_CANCELLED = 3;
	const OC_ORD_REC_STATUS_SUSPENDED = 4;
	const OC_ORD_REC_STATUS_EXPIRED   = 5;
	const OC_ORD_REC_STATUS_PENDING   = 6;

	/**
	 * OpenCart TaxCalss Constants
	 */
	const OC_TAX_CLASS_PHYSICAL_PRODUCT = 9;
	const OC_TAX_CLASS_VIRTUAL_PRODUCT  = 10;

	const THREEDS_V2_JAVA_ENABLED                 = 'java_enabled';
	const THREEDS_V2_COLOR_DEPTH                  = 'color_depth';
	const THREEDS_V2_BROWSER_LANGUAGE             = 'browser_language';
	const THREEDS_V2_SCREEN_HEIGHT                = 'screen_height';
	const THREEDS_V2_SCREEN_WIDTH                 = 'screen_width';
	const THREEDS_V2_USER_AGENT                   = 'user_agent';
	const THREEDS_V2_BROWSER_TIMEZONE_ZONE_OFFSET = 'browser_timezone_zone_offset';

	/**
	 * Module Name
	 *
	 * @var string
	 */
	protected $module_name = null;

	/**
	 * Cron log entry id
	 *
	 * @var int
	 */
	protected $log_entry_id = null;

	/**
	 * Cron log start_timestamp
	 *
	 * @var string
	 */
	protected $log_start_timestamp = null;

	/**
	 * Determines whether the module supports recurring payments
	 *
	 * @return bool
	 */
	public function recurringPayments(): bool {
		return ($this->config->get($this->module_name . '_supports_recurring') == '1')
			&& (!empty($this->config->get($this->module_name . '_recurring_transaction_type')));
	}

	/**
	 * Determines whether the shopping cart has a recurring item
	 * along with other recurring or non-recurring items which
	 * (currently) is not allowed
	 *
	 * @return bool
	 */
	public function isCartContentMixed(): bool {
		return $this->cart->hasSubscription() && (count($this->cart->getProducts()) > 1);
	}

	/**
	 * Determines whether the order is recurring
	 *
	 * @return bool
	 */
	public function isRecurringOrder(): bool {
		return $this->recurringPayments() && !empty($this->cart->getRecurringProducts());
	}

	/**
	 * Get current Currency Code
	 * @return string
	 */
	public function getCurrencyCode(): string {
		return $this->session->data['currency'];
	}

	/**
	 * Adds recurring order
	 * @param array $recurring_products
	 * @param string $payment_reference
	 *
	 * @return void
	 */
	public function addOrderRecurring($recurring_products, $payment_reference): void {
		$this->load->model('checkout/recurring');
		$this->load->language('extension/ecomprocessing/payment/' . $this->module_name);

		foreach ($recurring_products as $item) {
			//trial information
			if ($item['recurring']['trial'] == 1) {
				$trial_amt = $this->currency->format(
						$this->tax->calculate(
							$item['recurring']['trial_price'],
							$item['tax_class_id'],
							$this->config->get('config_tax')
						),
						$this->getCurrencyCode(),
						false,
						false
					)
					* $item['quantity'] . ' ' . $this->getCurrencyCode();

				if ($item['recurring']['trial_duration'] == '1') {
					$trial_text = sprintf(
						$this->language->get('text_trial_single_payment'),
						$trial_amt,
						$item['recurring']['trial_cycle'],
						$item['recurring']['trial_frequency']
					);
				} else {
					$trial_text = sprintf(
						$this->language->get('text_trial_multiple_payment'),
						$trial_amt,
						$item['recurring']['trial_cycle'],
						$item['recurring']['trial_frequency'],
						$item['recurring']['trial_duration']
					);
				}
			} else {
				$trial_text = '';
			}

			$recurring_amt = $this->currency->format(
					$this->tax->calculate(
						$item['recurring']['price'],
						$item['tax_class_id'],
						$this->config->get('config_tax')
					),
					$this->getCurrencyCode(),
					false,
					false
				)
				* $item['quantity'] . ' ' . $this->getCurrencyCode();
			$recurring_description = $trial_text . sprintf(
					$this->language->get('text_recurring'),
					$recurring_amt,
					$item['recurring']['cycle'],
					$item['recurring']['frequency']
				);

			if ($item['recurring']['duration'] > 0) {
				$recurring_description .= sprintf(
					$this->language->get('text_length'),
					$item['recurring']['duration']
				);
			}

			//create new recurring and set to pending status as no payment has been made yet.
			$order_recurring_id = $this->model_checkout_recurring->addRecurring(
				$this->session->data['order_id'],
				$recurring_description,
				$item + $item['recurring']
			);
			$this->model_checkout_recurring->editReference(
				$order_recurring_id,
				$payment_reference
			);
		}
	}

	/**
	 * Updates order recurring status and reference (if set)
	 *
	 * @param array $data
	 * @param string $payment_reference
	 *
	 * @return bool
	 */
	public function updateOrderRecurring($data, $payment_reference = null): bool {
		switch ($data['status']) {
			case States::APPROVED:
				$recurring_status = self::OC_ORD_REC_STATUS_ACTIVE;
				break;
			case States::DECLINED:
			case States::ERROR:
				$recurring_status = self::OC_ORD_REC_STATUS_CANCELLED;
				break;
			case States::NEW_STATUS:
			case States::PENDING_ASYNC:
			case States::IN_PROGRESS:
				$recurring_status = self::OC_ORD_REC_STATUS_PENDING;
				break;
			default:
				$recurring_status = self::OC_ORD_REC_STATUS_PENDING;
		}

		$order_recurring_id = $this->getOrderRecurringId($data['order_id']);

		$set_reference = is_null($payment_reference) ? '' : " ,reference = '" . $payment_reference . "'";

		$this->db->query("UPDATE " . DB_PREFIX . "subscription SET status = '" . (int)$recurring_status . "'" . $set_reference . " WHERE subscription_id = '" . (int)$order_recurring_id . "'");

		return ($this->db->countAffected() > 0);
	}

	/**
	 * Cancels order recurring
	 *
	 * @param array $data
	 *
	 * @return bool
	 */
	public function cancelOrderRecurring($data): bool {
		$recurring_status = 3; //Cancelled

		$order_recurring_id = $this->getOrderRecurringId($data['order_id']);

		$this->db->query("UPDATE " . DB_PREFIX . "subscription SET status = '" . (int)$recurring_status . "'" . " WHERE subscription_id = '" . (int)$order_recurring_id . "'");

		return ($this->db->countAffected() > 0);
	}

	/**
	 * Gets order recurring id by order id
	 *
	 * @param string $order_id
	 *
	 * @return string
	 */
	public function getOrderRecurringId($order_id): string {
		$query = $this->db->query("SELECT subscription_id FROM `" . DB_PREFIX . "subscription` WHERE order_id = '" . (int)$order_id . "'");

		return $query->row['order_recurring_id'];
	}

	/**
	 * Populates (adds/updates) recurring transaction
	 *
	 * @param array $data
	 *
	 * @return bool
	 */
	public function populateRecurringTransaction($data): bool {
		$ord_rec_transaction_id = null;

		switch ($data['status']) {
			case States::APPROVED:
				$oc_txn_type = self::OC_REC_TXN_PAYMENT;
				break;
			case States::DECLINED:
			case States::ERROR:
				$oc_txn_type = self::OC_REC_TXN_FAILED;
				break;
			case States::NEW_STATUS:
			case States::PENDING_ASYNC:
			case States::IN_PROGRESS:
				$oc_txn_type = self::OC_REC_TXN_DATE_ADDED;
				break;
			default:
				$oc_txn_type = self::OC_REC_TXN_DATE_ADDED;
				break;
		}

		// Check if transaction exists
		$query = $this->db->query("SELECT `subscription_transaction` FROM `" . DB_PREFIX . "subscription_transaction` WHERE `reference` = '" . $data['unique_id'] . "' ");

		if ($query->rows) {
			$ord_rec_transaction_id = $query->row['subscription_transaction'];
			$result = $this->updateRecurringTransaction($data, $oc_txn_type);
		} else {
			$result = $this->addRecurringTransaction($data, $oc_txn_type);
			if ($result) {
				$ord_rec_transaction_id = $this->db->getLastId();
			}
		}

		$this->cronAddTransaction($ord_rec_transaction_id, $data['order_id']);

		return $result;
	}

	/**
	 * Adds recurring transaction to the DB table order_recurring_transaction
	 *
	 * @param array $data
	 * @param int $oc_txn_type
	 *
	 * @return bool
	 */
	public function addRecurringTransaction($data, $oc_txn_type): bool {
		$result = false;

		if (!array_key_exists('order_recurring_id', $data)) {
			$data['order_recurring_id'] = $this->getOrderRecurringId($data['order_id']);
		}
		if (!empty($data['order_recurring_id'])) {
			$result = $this->db->query("INSERT INTO `" . DB_PREFIX . "subscription_transaction` SET `subscription_transaction_id` = '" . (int)$data['order_recurring_id'] . "', `reference` = '" . $data['unique_id'] . "', `type` = '" . $oc_txn_type . "', `amount` = '" . $data['amount'] . "', `date_added` = NOW()");
		}

		return $result;
	}

	/**
	 * Updates the type of a recurring transaction from DB table order_recurring_transaction
	 *
	 * @param array $data
	 * @param int $oc_txn_type
	 *
	 * @return bool
	 */
	public function updateRecurringTransaction($data, $oc_txn_type): bool {
		$result = $this->db->query("UPDATE `" . DB_PREFIX . "subscription_transaction` SET `type` = '" . $oc_txn_type . "' WHERE `reference` = '" . $data['unique_id'] . "'");

		return $result;
	}

	/**
	 * Process recurring orders
	 *
	 * Processes all recurring orders meeting the following four conditions:
	 * 1. Recurring is active (order_recurring.status = 1)
	 * 2. The order status (order.order_status_id) is not in the "non-billable" list
	 * 3. The customer is active (customer.status = 1) and approved (customer.approved = 1)
	 * 4. There is no payment (order_recurring_transactions.type = 1) made today for this recurring order
	 *
	 * @return void
	 */
	public function processRecurringOrders(): void {
		if ($this->isCronCallAllowed()) {
			$this->cronLogStartTime();
			$this->cronLogDeleteOldRecords();

			$query = $this->db->query(
				'SELECT *, `ord`.`date_added`, `rec`.`status` as recurring_status '
				. 'FROM `' . DB_PREFIX . 'subscription` as rec '
				. 'JOIN `' . DB_PREFIX . 'subscription_transaction` as tr ON (`rec`.`subscription_transaction_id` = `tr`.`subscription_transaction_id` AND `rec`.`reference` = `tr`.`reference`) '
				. 'JOIN `' . DB_PREFIX . 'order` as ord ON (`rec`.`order_id` = `ord`.`order_id`) '
				. 'JOIN `' . DB_PREFIX . 'customer` as cst ON (`ord`.`customer_id` = `cst`.`customer_id`) '
				. 'WHERE '
				. '`rec`.`status` = 1 '
				. 'AND `ord`.`order_status_id` NOT IN (' . $this->getNonBillableOrderStatuses() . ') '
				. 'AND `cst`.`status` = 1 AND  `cst`.`approved` = 1 '
				. 'AND ((SELECT count(*) FROM `' . DB_PREFIX . 'subscription_transaction` as tr WHERE `type` = 1 AND DATE(`date_added`) = CURDATE()) = 0)'
			);

			if ($query->num_rows) {
				foreach ($query->rows as $data) {
					if ($this->isCronTimeLimitReached()) {
						break;
					}
					$this->processRecurringOrder($data);
				}
			}
			$this->cronLogRunTime();
		}
	}

	/**
	 * Creates Genesis request based on transaction type
	 *
	 * @param const $transaction_type
	 *
	 * @return Genesis
	 *
	 * @throws \Genesis\Exceptions\InvalidMethod
	 * @throws \Genesis\Exceptions\InvalidArgument
	 * @throws \Genesis\Exceptions\InvalidMethod
	 */
	public function createGenesisRequest($transaction_type): Genesis {
		return new Genesis(
			Types::getFinancialRequestClassForTrxType($transaction_type)
		);
	}

	/**
	 * Is transaction INIT_RECURRING_SALE or INIT_RECURRING_SALE_3D?
	 *
	 * @param const $transaction_type
	 *
	 * @return bool
	 */
	public function isInitialRecurringTransaction($transaction_type): bool {
		return in_array($transaction_type, array(
			Types::INIT_RECURRING_SALE,
			Types::INIT_RECURRING_SALE_3D
		));
	}

	/**
	 * Writes to the cron log the cron start time
	 *
	 * @return bool
	 */
	public function cronLogStartTime(): bool {
		$this->log_start_timestamp = microtime(true);
		$result = $this->db->query("INSERT `" . DB_PREFIX . $this->module_name . "_cronlog` SET `pid` = " . getmypid() . ", `start_time` = NOW()");
		if ($result) {
			$this->log_entry_id = $this->db->getLastId();
		}

		return $result;
	}

	/**
	 * Writes to the cron log the cron run time
	 *
	 * @return bool
	 */
	public function cronLogRunTime(): bool {
		$run_time = sprintf('%0.3f', microtime(true) - $this->log_start_timestamp);

		return $this->db->query("UPDATE `" . DB_PREFIX . $this->module_name . "_cronlog` SET `run_time` = '"  . $run_time . "' WHERE `log_entry_id`=" . $this->log_entry_id);
	}

	/**
	 * Determines whether the allowed time to run the cron is reached
	 *
	 * @return bool
	 */
	public function isCronTimeLimitReached(): bool {
		return (microtime(true) - $this->log_start_timestamp) > $this->config->get($this->module_name . '_cron_time_limit');
	}

	/**
	 * Adds transaction to the cron log
	 *
	 * @param string $ord_rec_transaction_id
	 * @param string $order_id
	 *
	 * @return bool
	 */
	public function cronAddTransaction($ord_rec_transaction_id, $order_id): bool {
		$result = null;

		if ($this->log_entry_id !== null) {
			return $this->db->query("INSERT `" . DB_PREFIX . $this->module_name . "_cronlog_transactions` SET `subscription_transaction_id` = " . (int)$ord_rec_transaction_id . ", `order_id` = " . (int)$order_id . ", `log_entry_id` = " . (int)$this->log_entry_id);
		}

		return $result;
	}

	/**
	 * Deletes the old records from the cron log
	 *
	 * @return bool
	 */
	public function cronLogDeleteOldRecords(): bool {
		$last_cron_log_id = (string)$this->getLastCronLogId();

		return $this->db->query(
			"DELETE FROM `" . DB_PREFIX . $this->module_name . "_cronlog`
			  WHERE 
				(`" . DB_PREFIX . $this->module_name . "_cronlog`.`log_entry_id` != {$last_cron_log_id}) AND 
				NOT (
					EXISTS(SELECT 
							`subscription_transaction_id` 
						FROM `" . DB_PREFIX . $this->module_name . "_cronlog_transactions` 
						WHERE 
							`" . DB_PREFIX . $this->module_name . "_cronlog_transactions`.`log_entry_id`
							 = `" . DB_PREFIX . $this->module_name . "_cronlog`.`log_entry_id`
					   )
					)
			"
		);
	}

	/**
	 * @param $data
	 *
	 * @return void
	 *
	 * @throws \Exception
	 */
	public function populateTransaction($data): void {
		$db_helper = new DbHelper($this->module_name, $this);
		$db_helper->populateTransaction($data);
	}

	/**
	 * Proxy method for logEx
	 *
	 * @param \Exception $exception
	 *
	 * @return void
	 */
	public function logEx(\Exception $exception): void {
		$db_helper = new DbHelper($this->module_name, $this);
		$db_helper->logEx($exception);
	}

	/**
	 * Get a description-formatted list of products
	 * inside an order
	 *
	 * @param $order_id
	 *
	 * @return string
	 */
	public function getOrderProducts($order_id): string {
		$order_product_query = $this->db->query("SELECT * FROM " . DB_PREFIX . "order_product WHERE order_id = '" . abs((int)$order_id) . "'");

		$description = '';

		foreach ($order_product_query->rows as $order_product) {
			$description .= sprintf("%s (%s) x %d\r\n", $order_product['name'], $order_product['model'], $order_product['quantity']);
		}

		return $description;
	}

	/**
	 * Get the Order Products stored in the Database
	 *
	 * @param $order_id
	 *
	 * @return mixed
	 */
	public function getDbOrderProducts($order_id): mixed {
		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "order_product	WHERE order_id = '" . (int)$order_id . "'");

		return $query->rows;
	}

	/**
	 * Get Products Information
	 *
	 * @param array $products
	 *
	 * @return mixed
	 */
	public function getProductsInfo($products = array()): mixed {
		$ids = array();
		foreach ($products as $product) {
			array_push($ids, abs((int)$product));
		}

		$products_resource = $this->db->query("SELECT *	FROM " . DB_PREFIX . "product WHERE product_id IN (" . implode(', ', $ids) . ")");

		return $products_resource->rows;
	}

	/**
	 * Determines whether the call to the cron is allowed based on IP address from which the cron is called
	 *
	 * @return bool
	 */
	protected function isCronCallAllowed(): bool {
		$remote_address = EcomprocessingHelper::getFirstRemoteAddress($this->request->server['REMOTE_ADDR']);

		return $remote_address == trim($this->config->get("{$this->module_name}_cron_allowed_ip"));
	}

	/**
	 * Process recurring order and performs RecurringSale if needed
	 *
	 * @param array $data
	 *
	 * @return void
	 *
	 * @throws \Exception
	 */
	protected function processRecurringOrder($data): void {
		$recurring = array(
			'trial'           => $data['trial'],
			'trial_cycle'     => $data['trial_cycle'],
			'trial_frequency' => $data['trial_frequency'],
			'trial_duration'  => $data['trial_duration'],
			'cycle'           => $data['recurring_cycle'],
			'frequency'       => $data['recurring_frequency'],
			'duration'        => $data['recurring_duration']
		);

		$order_recurring_start = new DateTime(substr($data['date_added'], 0, 10)); //oc_order_recurring.date_added
		$trial_end = clone $order_recurring_start;

		$now = new DateTime(date('Y-m-d'));

		$is_pay_day = false;

		if (($recurring['trial'] == 1) && ($recurring['trial_duration'] > 0)) {
			$trial_end = $this->getPaymentDueDate(
				clone $order_recurring_start,
				$recurring['trial_frequency'],
				$recurring['trial_cycle'] * $recurring['trial_duration']
			);
			if ($now < $trial_end) {
				$is_pay_day = $this->isPayDay(
					$now,
					$order_recurring_start,
					$recurring['trial_frequency'],
					$recurring['trial_cycle'],
					$recurring['trial_duration']
				);
				$data['amount'] = $this->calculateRebillingAmount($data['order_id'], $data['trial_price']);
			}
		}

		if (!$is_pay_day) {
			if ($recurring['duration'] > 0) {
				$recurring_end = $this->getPaymentDueDate(
					clone $trial_end,
					$recurring['frequency'],
					$recurring['cycle'] * $recurring['duration']
				);
			} else {
				$recurring_end = new DateTime('2999-01-01');
			}
			if ($now < $recurring_end) {
				$is_pay_day = $this->isPayDay(
					$now,
					$trial_end,
					$recurring['frequency'],
					$recurring['cycle'],
					$recurring['duration']
				);
				$data['amount'] = $this->calculateRebillingAmount($data['order_id'], $data['recurring_price']);
			}
		}

		if ($is_pay_day) {
			$this->recurringSale($data);
		}
	}

	/**
	 * Calculates the re-billing amount
	 *
	 * @param string $order_id
	 * @param string $amount The amount of the recurring price or trial price before taxes
	 *
	 * @return float
	 */
	protected function calculateRebillingAmount($order_id, $amount): float {
		$result = null;

		$query = $this->db->query(
			'SELECT `ord`.`quantity`, `prd`.`tax_class_id` '
			. 'FROM `' . DB_PREFIX . 'order_product` as ord '
			. 'JOIN `' . DB_PREFIX . 'product` as prd using (`product_id`) '
			. 'WHERE `ord`.`order_id` = ' . (int)$order_id
		);

		if ($query->num_rows == 1) {
			$order_data = array_pop($query->rows);
			$result = $this->tax->calculate(
					$amount,
					$order_data['tax_class_id'],
					$this->config->get('config_tax')
				) * $order_data['quantity'];
		}

		return $result;
	}

	/**
	 * Performs RecurringSale by sending RecurringSale transaction
	 * to the Gateway and populates the transaction
	 *
	 * @param array $rec_data
	 *
	 * @return void
	*/
	protected function recurringSale($rec_data): void {
		$this->load->model('checkout/order');

		$this->load->language('extension/ecomprocessing/payment/' . $this->module_name);

		try {
			$this->bootstrap();

			$transaction = $this->getTransactionById($rec_data['reference']);

			if (isset($transaction['order_id']) && abs((int)$transaction['order_id']) > 0) {

				$data = array(
					'terminal_token'   => $this->getTerminalToken($transaction),
					'transaction type' => Types::RECURRING_SALE,
					'transaction_id'   => $this->genTransactionId(),
					'usage'            => $this->getUsage(),
					'remote_address'   => EcomprocessingHelper::getFirstRemoteAddress($this->request->server['REMOTE_ADDR']),
					'reference_id'     => $rec_data['reference'],
					'amount'           => $rec_data['amount'],
					'currency'         => $rec_data['currency_code']
				);

				$response = $this->sendRecurringSale($data);

				if ($response != false) {
					$timestamp = ($response->timestamp instanceof DateTime) ? $response->timestamp->format('c') : $response->timestamp;

					$data = array(
						'order_id'          => $transaction['order_id'],
						'reference_id'      => $rec_data['reference'],
						'status'            => $response->status,
						'unique_id'         => $response->unique_id,
						'type'              => $response->transaction_type,
						'mode'              => $response->mode,
						'currency'          => $response->currency,
						'amount'            => $response->amount,
						'timestamp'         => $timestamp,  //tz?
						'message'           => isset($response->message) ? $response->message : '',
						'technical_message' => isset($response->technical_message) ? $response->technical_message : '',
					);

					$this->populateTransaction($data);

					$data['order_recurring_id'] = $rec_data['order_recurring_id'];

					$this->populateRecurringTransaction($data);
					$this->updateOrderRecurring($data);
				}
			}
		} catch (\Exception $exception) {
			$this->logEx($exception);
		}
	}

	/**
	 * Sends RecurringSale transaction to Genesis
	 *
	 * @param $data array Transaction Data
	 *
	 * @return mixed
	 *
	 * @throws /Exception
	 */
	protected function sendRecurringSale($data): mixed {
		try {
			$this->bootstrap();

			Config::setToken((string)$data['terminal_token']);

			$genesis = $this->createGenesisRequest($data['transaction type']);

			$genesis
				->request()
				->setReferenceId($data['reference_id'])
				->setTransactionId($data['transaction_id'])
				->setRemoteIp($data['remote_address'])
				// Financial
				->setCurrency($data['currency'])
				->setAmount($data['amount'])
				->setUsage($data['usage']);

			$genesis->execute();

			return $genesis->response()->getResponseObject();
		} catch (\Exception $exception) {
			$this->logEx($exception);

			return false;
		}
	}

	/**
	 *
	 * @return int
	 */
	protected function getLastCronLogId(): int {
		$query = $this->db->query('SELECT `log_entry_id` FROM `' . DB_PREFIX . $this->module_name . '_cronlog` ORDER BY `log_entry_id` DESC LIMIT 1');

		if ($query->num_rows == 1) {
			$data = array_pop($query->rows);

			return $data['log_entry_id'];
		}

		return 0;
	}

	/**
	 * Execute a query to find if the payment method is available in the user's geo zone
	 *
	 * @param array $address Array with Address data from checkout
	 *
	 * @return bool
	 */
	protected function checkGeoZoneAvailability($address) {
		$status = false;
		$query  = $this->db->query("
				SELECT * 
				FROM `" . DB_PREFIX . "zone_to_geo_zone` 
				WHERE 
				`geo_zone_id` = '" . (int)$this->config->get("{$this->module_name}_geo_zone_id") . "' 
				AND 
				`country_id` = '" . (int)($address['country_id'] ?? 0) . "' 
				AND 
				(`zone_id` = '" . (int)($address['zone_id'] ?? 0) . "' OR `zone_id` = '0')
			");

		if ($query->num_rows) {
			$status = true;
		}

		return $status;
	}

	/**
	 * Append 3DSv2 parameters to the Genesis Request
	 *
	 * @param $genesis
	 * @param $data
	 *
	 * @throws InvalidArgument
	 *
	 * @return void
	 */
	protected function addThreedsParamsToRequest($genesis, $data): void {
		/** @var Create $request */
		$request = $genesis->request();
		$request->setThreedsV2ControlChallengeIndicator($data['threeds_challenge_indicator'])
			->setThreedsV2PurchaseCategory($data['threeds_purchase_category'])
			->setThreedsV2MerchantRiskDeliveryTimeframe($data['threeds_delivery_timeframe'])
			->setThreedsV2MerchantRiskShippingIndicator($data['threeds_shipping_indicator'])
			->setThreedsV2MerchantRiskReorderItemsIndicator($data['threeds_reorder_items_indicator'])
			->setThreedsV2CardHolderAccountRegistrationDate($data['threeds_registration_date'])
			->setThreedsV2CardHolderAccountRegistrationIndicator($data['threeds_registration_indicator'])
		;
		if (!$data['is_guest']) {
			$request->setThreedsV2CardHolderAccountCreationDate($data['threeds_creation_date'])
				->setThreedsV2CardHolderAccountShippingAddressDateFirstUsed($data['threads_shipping_address_date_first_used'])
				->setThreedsV2CardHolderAccountShippingAddressUsageIndicator($data['threeds_shipping_address_usage_indicator'])
				->setThreedsV2CardHolderAccountTransactionsActivityLast24Hours($data['transactions_activity_last_24_hours'])
				->setThreedsV2CardHolderAccountTransactionsActivityPreviousYear($data['transactions_activity_previous_year'])
				->setThreedsV2CardHolderAccountPurchasesCountLast6Months($data['purchases_count_last_6_months'])
			;
		}

		$amount = (float)$genesis->request()->getAmount();
		if ($amount <= $data['sca_exemption_amount']) {
			$genesis->request()->setScaExemption($data['sca_exemption_value']);
		}
	}

	/**
	 * Check settings if 3DSv2 is enabled
	 *
	 * @return bool
	 */
	protected function isThreedsAllowed(): bool {
		return (bool)$this->config->get($this->module_name . '_threeds_allowed');
	}

	/**
	 * Check whether the selected transaction type is a 3d transaction
	 *
	 * @return bool
	 */
	protected function is3dTransaction(): bool {
		$types = array(
			Types::AUTHORIZE_3D,
			Types::SALE_3D,
			Types::INIT_RECURRING_SALE_3D,
		);

		$transaction_type = $this->config->get(
			$this->isRecurringOrder() ? "{$this->module_name}_recurring_transaction_type" : "{$this->module_name}_transaction_type"
		);

		return in_array($transaction_type, $types);
	}

	/**
	 * Bootstrap Genesis Library
	 *
	 * @return void
	 *
	 * @throws InvalidArgument
	 */
	public function bootstrap(): void {
		// Look for, but DO NOT try to load via Auto-loader magic methods
		if (!class_exists('\Genesis\Genesis', false)) {
			include DIR_STORAGE . 'vendor/genesisgateway/genesis_php/vendor/autoload.php';
		}

		Config::setEndpoint(
			Endpoints::ECOMPROCESSING
		);

		Config::setUsername(
			$this->config->get("{$this->module_name}_username")
		);

		Config::setPassword(
			$this->config->get("{$this->module_name}_password")
		);

		Config::setEnvironment(
			$this->config->get("{$this->module_name}_sandbox") ? Environments::STAGING : Environments::PRODUCTION
		);
	}

	/**
	 * Generates a comma-separated list of the order statuses that should not be re-billed
	 *
	 * @return string
	 */
	private function getNonBillableOrderStatuses(): string {
		return self::OC_ORD_STATUS_CANCELED
			. ',' . self::OC_ORD_STATUS_DENIED
			. ',' . self::OC_ORD_STATUS_CANCELED_REVERSAL
			. ',' . self::OC_ORD_STATUS_REFUNDED
			. ',' . self::OC_ORD_STATUS_CHARGEBACK
			. ',' . self::OC_ORD_STATUS_EXPIRED;
	}

	/**
	 * Gets the terminal token
	 *
	 * @param array $transaction
	 *
	 * @return string|null
	 */
	private function getTerminalToken($transaction): string|null {
		if (!empty($this->config->get($this->module_name . '_recurring_token'))) {
			$result = $this->config->get($this->module_name . '_recurring_token');
		} elseif ($this->module_name == 'ecomprocessing_direct') {
			$result = $this->config->get('ecomprocessing_direct_token');
		} else {
			$result = array_key_exists('terminal_token', $transaction) ? $transaction['terminal_token'] : null;
		}

		return $result;
	}

	/**
	 * Calculates whether now is a "pay day" (the due-date of the recurring order)
	 *
	 * @param DateTime $now
	 * @param DateTime $start_payment
	 * @param string $frequency
	 * @param string $cycle
	 * @param string $duration
	 *
	 * @return bool
	 */
	private function isPayDay($now, $start_payment, $frequency, $cycle, $duration): bool {
		$result = false;

		$payment_no = 0;

		$due_day = new DateTime('0000-00-00');

		if ($duration == 0) {
			$duration = 1e6;
		}

		while (($payment_no <= $duration-1) && ($due_day < $now)) {
			$due_day = $this->getPaymentDueDate(clone $start_payment, $frequency, $cycle * $payment_no);
			if ($due_day == $now) {
				$result = true;
			}
			$payment_no++;
		}

		return $result;
	}

	/**
	 * Calculates the payment due date
	 *
	 * @param DateTime $payment start day of the recurring
	 * @param string $frequency recurring frequency
	 * @param string $cycle recurring cycle
	 *
	 * @return DateTime
	 */
	private function getPaymentDueDate($payment, $frequency, $cycle): DateTime {
		if ($frequency == 'semi_month') {
			$payment2 = clone $payment;
			$payment2->modify('+ 1 month');
			$interval = $payment->diff($payment2);
			$days_to_month = $interval->format('%a');
			$days_to_semi_month = (int)$days_to_month / 2;
			$payment->modify('+ ' . $cycle * $days_to_semi_month . ' day');
		} else {
			$payment->modify('+' . $cycle . ' ' . $frequency);
		}

		return $payment;
	}
}
