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

namespace Opencart\Admin\Controller\Extension\Ecomprocessing\Payment;

use Opencart\Extension\Ecomprocessing\System\Admin\BaseController;

/**
 * Backend controller for the "ecomprocessing Direct" module
 *
 * @package EcomprocessingDirect
 */
class EcomprocessingDirect extends BaseController
{
	/**
	 * Module Name (Used in View - Templates)
	 *
	 * @var string
	 */
	protected $module_name = 'ecomprocessing_direct';

	/**
	 * ControllerExtensionPaymentEcomprocessingDirect constructor.
	 *
	 * @param $registry
	 */
	public function __construct($registry) {
		parent::__construct($registry);
		// TODO array_push with single element
		array_push($this->error_field_key_list, 'token');
	}

	/**
	 * Used to find out if the payment method requires SSL
	 *
	 * @return bool
	 */
	protected function isModuleRequiresSsl(): bool {
		return true;
	}

	/**
	 * Ensure that the current user has permissions to see/modify this module
	 *
	 * @return void
	 */
	protected function validateRequiredFields(?array $required_fields = []): void {
		$required_fields["{$this->module_name}_async_order_status_id"] = 'order_async_status';
		parent::validateRequiredFields($required_fields);
	}
}
