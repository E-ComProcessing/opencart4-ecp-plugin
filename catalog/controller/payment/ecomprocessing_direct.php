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
 * @author      E-Comprocessing
 * @copyright   2018 E-Comprocessing Ltd.
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU General Public License, version 2 (GPL-2.0)
 */

namespace Opencart\Catalog\Controller\Extension\Ecomprocessing\Payment;

use Genesis\API\Notification;
use Opencart\Extension\Ecomprocessing\System\Catalog\BaseController;
use Genesis\API\Constants\Transaction\States;

/**
 * Front-end controller for the "ecomprocessing Direct" module
 *
 * @package EcomprocessingDirect
 */
class EcomprocessingDirect extends BaseController
{

	/**
	 * Module Name
	 *
	 * @var string
	 */
	protected $module_name = 'ecomprocessing_direct';

	/**
	 * Init
	 *
	 * @param $registry
	 */
	public function __construct($registry)
	{
		parent::__construct($registry);
	}

	/**
	 * Entry-point
	 *
	 * @return mixed
	 */
	public function index(): mixed
	{
		$this->load->language('extension/ecomprocessing/payment/ecomprocessing_direct');
		$this->load->model('extension/ecomprocessing/payment/ecomprocessing_direct');
		$this->document->addStyle(HTTP_SERVER . '/extension/ecomprocessing/catalog/view/stylesheet/ecomprocessing/ecomprocessing.css');

		if ($this->model_extension_ecomprocessing_payment_ecomprocessing_direct->isCartContentMixed()) {
			$template = 'ecomprocessing_disabled';
			$data = $this->prepareViewDataMixedCart();

		} else {
			$template = 'ecomprocessing_direct';
			$this->document->addScript(
				HTTP_SERVER . '/extension/ecomprocessing/catalog/view/javascript/ecomprocessing/card.min.js'
			);
			$data = $this->prepareViewData();
		}

		return $this->load->view('extension/ecomprocessing/payment/' . $template, $data);
	}

	/**
	 * Prepares data for the view
	 *
	 * @return array
	 */
	public function prepareViewData(): array
	{
		return array(
			'text_credit_card' => $this->language->get('text_credit_card'),
			'text_loading'     => $this->language->get('text_loading'),
			'text_card_legal'  => $this->getLegalText(),

			'entry_cc_number'  => $this->language->get('entry_cc_number'),
			'entry_cc_owner'   => $this->language->get('entry_cc_owner'),
			'entry_cc_expiry'  => $this->language->get('entry_cc_expiry'),
			'entry_cc_cvv'     => $this->language->get('entry_cc_cvv'),

			'button_confirm' => $this->language->get('button_confirm'),
			'button_target'  => $this->buildUrl(
				'extension/ecomprocessing/payment/ecomprocessing_direct',
				'send'
			),

			'scripts'          => $this->document->getScripts(),
			'styles'           => $this->document->getStyles(),
		);
	}

	/**
	 * Prepares data for the view when cart content is mixed
	 *
	 * @return array
	 */
	public function prepareViewDataMixedCart(): array
	{
		return array(
			'text_loading'                    => $this->language->get('text_loading'),
			'text_payment_mixed_cart_content' => $this->language->get('text_payment_mixed_cart_content'),
			'button_shopping_cart'            => $this->language->get('button_shopping_cart'),
			'button_target'                   => $this->buildUrl('checkout/cart')
		);
	}

	/**
	 * Process order confirmation
	 *
	 * @return void
	 */
	public function send(): void
	{
		$this->load->model('checkout/order');
		$this->load->model('extension/ecomprocessing/payment/ecomprocessing_direct');

		$this->load->language('extension/ecomprocessing/payment/ecomprocessing_direct');

		if (array_key_exists('order_id', $this->session->data)) {
			$order_info = $this->model_checkout_order->getOrder($this->session->data['order_id']);

			try {
				$data = array(
					'transaction_id'     => $this->model_extension_ecomprocessing_payment_ecomprocessing_direct->genTransactionId(self::PLATFORM_TRANSACTION_PREFIX),

					'remote_address'     => $this->request->server['REMOTE_ADDR'],

					'usage'              => $this->model_extension_ecomprocessing_payment_ecomprocessing_direct->getUsage(),
					'description'        => $this->model_extension_ecomprocessing_payment_ecomprocessing_direct->getOrderProducts(
						$this->session->data['order_id']
					),

					'currency'           => $this->model_extension_ecomprocessing_payment_ecomprocessing_direct->getCurrencyCode(),
					'amount'             => (float)$order_info['total'],

					'customer_email'     => $order_info['email'],
					'customer_phone'     => $order_info['telephone'],

					'card_holder'        => $this->inputFilter(
						$this->request->post['ecomprocessing_direct-cc-holder'],
						'name'
					),
					'card_number'        => $this->inputFilter(
						$this->request->post['ecomprocessing_direct-cc-number'],
						'number'
					),
					'cvv'                => $this->inputFilter(
						$this->request->post['ecomprocessing_direct-cc-cvv'],
						'cvv'
					),
					'expiration_month'   => $this->inputFilter(
						$this->request->post['ecomprocessing_direct-cc-expiration'],
						'month'
					),
					'expiration_year'    => $this->inputFilter(
						$this->request->post['ecomprocessing_direct-cc-expiration'],
						'year'
					),

					'notification_url'   => $this->buildUrl('extension/ecomprocessing/payment/ecomprocessing_direct/callback'),
					'return_success_url' => $this->buildUrl('extension/ecomprocessing/payment/ecomprocessing_direct/success'),
					'return_failure_url' => $this->buildUrl('extension/ecomprocessing/payment/ecomprocessing_direct/failure')
				);

				$this->populateAddresses($order_info, $data);

				$transaction = $this->model_extension_ecomprocessing_payment_ecomprocessing_direct->sendTransaction($data);

				if (isset($transaction->unique_id)) {
					$timestamp = ($transaction->timestamp instanceof \DateTime) ? $transaction->timestamp->format('c') : $transaction->timestamp;

					$data = array(
						'reference_id'      => '0',
						'order_id'          => $order_info['order_id'],
						'unique_id'         => $transaction->unique_id,
						'type'              => $transaction->transaction_type,
						'status'            => $transaction->status,
						'message'           => $transaction->message,
						'technical_message' => $transaction->technical_message,
						'amount'            => $transaction->amount,
						'currency'          => $transaction->currency,
						'timestamp'         => $timestamp,
					);

					$this->model_extension_ecomprocessing_payment_ecomprocessing_direct->populateTransaction($data);

					$redirect_url = $this->buildUrl('checkout/success');

					switch ($transaction->status) {
						case States::PENDING_ASYNC:
							$this->model_checkout_order->addHistory(
								$this->session->data['order_id'],
								$this->config->get('ecomprocessing_direct_async_order_status_id'),
								$this->language->get('text_payment_status_init_async'),
								true
							);

							if (isset($transaction->threeds_method_continue_url)) {
								throw new \Exception(
									$this->language->get('text_payment_3ds_v2_error')
								);
							}

							if (isset($transaction->redirect_url)) {
								$redirect_url = $transaction->redirect_url;
							}

							break;
						case States::APPROVED:
							$this->model_checkout_order->addHistory(
								$this->session->data['order_id'],
								$this->config->get('ecomprocessing_direct_order_status_id'),
								$this->language->get('text_payment_status_successful'),
								false
							);

							break;
						case States::DECLINED:
						case States::ERROR:
							$this->model_checkout_order->addHistory(
								$this->session->data['order_id'],
								$this->config->get('ecomprocessing_direct_order_failure_status_id'),
								$this->language->get('text_payment_status_unsuccessful'),
								true
							);

							throw new \Exception(
								$transaction->message
							);

							break;
					}

					if ($this->model_extension_ecomprocessing_payment_ecomprocessing_direct->isRecurringOrder()) {
						$this->addOrderRecurring($transaction->unique_id);
						$this->model_extension_ecomprocessing_payment_ecomprocessing_direct->populateRecurringTransaction($data);
						$this->model_extension_ecomprocessing_payment_ecomprocessing_direct->updateOrderRecurring($data);
					}

					$json = array(
						'redirect' => $redirect_url
					);
				} else {
					$json = array(
						'error' => $this->language->get('text_payment_system_error')
					);
				}
			} catch (\Exception $exception) {
				$json = array(
					'error' => ($exception->getMessage()) ?: $this->language->get('text_payment_system_error')
				);

				$this->model_extension_ecomprocessing_payment_ecomprocessing_direct->logEx($exception);
			}
		} else {
			$exception = new \Exception('Incorrect call!');
			$this->model_extension_ecomprocessing_payment_ecomprocessing_direct->logEx($exception);
			$json = array(
				'error' => $exception->getMessage()
			);
		}

		$this->response->addHeader('Content-Type: application/json');

		$this->response->setOutput(
			json_encode($json)
		);
	}

	/**
	 * Process Gateway Notification
	 *
	 * @return void
	 */
	public function callback(): void
	{
		$this->load->model('checkout/order');
		$this->load->model('extension/ecomprocessing/payment/ecomprocessing_direct');

		$this->load->language('extension/ecomprocessing/payment/ecomprocessing_direct');

		try {
			$this->model_extension_ecomprocessing_payment_ecomprocessing_direct->bootstrap();

			$notification = new Notification(
				$this->request->post
			);

			if ($notification->isAuthentic()) {
				$notification->initReconciliation();

				$reconcile = $notification->getReconciliationObject();

				if (isset($reconcile->unique_id)) {

					$transaction = $this->model_extension_ecomprocessing_payment_ecomprocessing_direct->getTransactionById($reconcile->unique_id);

					if (isset($transaction['order_id']) && abs((int)$transaction['order_id']) > 0) {

						$timestamp = ($reconcile->timestamp instanceof \DateTime) ? $reconcile->timestamp->format('c') : $reconcile->timestamp;

						$data = array(
							'order_id'          => $transaction['order_id'],
							'unique_id'         => $reconcile->unique_id,
							'type'              => $reconcile->transaction_type,
							'mode'              => $reconcile->mode,
							'status'            => $reconcile->status,
							'currency'          => $reconcile->currency,
							'amount'            => $reconcile->amount,
							'timestamp'         => $timestamp,
							'message'           => isset($reconcile->message) ? $reconcile->message : '',
							'technical_message' => isset($reconcile->technical_message) ? $reconcile->technical_message : '',
						);

						$this->model_extension_ecomprocessing_payment_ecomprocessing_direct->populateTransaction($data);

						switch ($reconcile->status) {
							case States::APPROVED:
								$this->model_checkout_order->addHistory(
									$transaction['order_id'],
									$this->config->get('ecomprocessing_direct_order_status_id'),
									$this->language->get('text_payment_status_successful')
								);
								break;
							case States::DECLINED:
							case States::ERROR:
								$this->model_checkout_order->addHistory(
									$transaction['order_id'],
									$this->config->get('ecomprocessing_direct_order_failure_status_id'),
									$this->language->get('text_payment_status_unsuccessful')
								);
								break;
						}

						if ($this->model_extension_ecomprocessing_payment_ecomprocessing_direct->isRecurringOrder()) {
							$this->model_extension_ecomprocessing_payment_ecomprocessing_direct->populateRecurringTransaction($data);
							$this->model_extension_ecomprocessing_payment_ecomprocessing_direct->updateOrderRecurring($data);
						}

						$this->response->addHeader('Content-Type: text/xml');

						$this->response->setOutput(
							$notification->generateResponse()
						);
					}
				}
			}
		} catch (\Exception $exception) {
			$this->model_extension_ecomprocessing_payment_ecomprocessing_direct->logEx($exception);
		}
	}

	/**
	 * Handle client redirection for successful status
	 *
	 * @return void
	 */
	public function success(): void
	{
		$this->response->redirect($this->buildUrl('checkout/success'));
	}

	/**
	 * Handle client redirection for failure status
	 *
	 * @return void
	 */
	public function failure(): void
	{
		$this->load->language('extension/ecomprocessing/payment/ecomprocessing_direct');

		$this->session->data['error'] = $this->language->get('text_payment_failure');

		$this->response->redirect($this->buildUrl('checkout/checkout'));
	}

	/**
	 * Sanitize incoming data
	 *
	 * @param string $input Field value
	 * @param string $type Field type
	 *
	 * @return mixed|string
	 */
	protected function inputFilter($input, $type): mixed
	{
		switch ($type) {
			case 'number':
				return str_replace(' ', '', $input);
				break;
			case 'cvv':
				return strval($input);
			case 'year':
				$expire = explode('/', $input);
				if (count($expire) == 2) {
					list(, $year) = $expire;
					$year = trim($year);

					if (strlen($year) == 2) {
						return sprintf('20%s', $year);
					}

					return substr($year, 0, 4);
				}
				break;
			case 'month':
				$expire = explode('/', $input);
				if (count($expire) == 2) {
					list($month,) = $expire;
					$month = trim($month);

					if ($month) {
						return substr(strval($month), 0, 2);
					}
				}
				break;
		}

		return trim($input);
	}

	/**
	 * Redirect the user (to the login page), if they are not logged-in
	 *
	 * @return void
	 */
	protected function isUserLoggedIn(): void
	{
		$is_callback = strpos((string)$this->request->get['route'], 'callback') !== false;

		if (!$this->customer->isLogged() && !$is_callback) {
			$this->response->redirect($this->buildUrl('account/login'));
		}
	}

	/**
	 * Generate a legal text for this store
	 *
	 * @return string
	 */
	protected function getLegalText(): string
	{
		$store_name = $this->config->get('config_name');

		return sprintf('&copy; %s E-Comprocessing Ltd.<br/><br/>%s', date('Y'), $store_name);
	}

	/**
	 * Adds recurring order
	 *
	 * @param string $payment_reference
	 *
	 * @return void
	 */
	public function addOrderRecurring($payment_reference): void
	{
		$recurring_products = $this->cart->getRecurringProducts();
		if (!empty($recurring_products)) {
			$this->load->model('extension/payment/ecomprocessing_direct');
			$this->model_extension_ecomprocessing_payment_ecomprocessing_direct->addOrderRecurring(
				$recurring_products,
				$payment_reference
			);
		}
	}

	/**
	 * Process the cron if the request is local
	 *
	 * @return void
	 */
	public function cron(): void
	{
		$this->load->model('extension/payment/ecomprocessing_direct');
		$this->model_extension_ecomprocessing_payment_ecomprocessing_direct->processRecurringOrders();
	}
}
