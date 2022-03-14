<?php

defined( 'ABSPATH' ) || exit;

/**
 * A class for Settle and Void requests.
 */
class Nuvei_Settle_Void extends Nuvei_Request {

	/**
	 * Main method of the class.
	 * Expected parameters are:
	 * 
	 * @param array [order_id, action, method]
	 * @return array|false
	 */
	public function process() {
		$data = current(func_get_args());
		
		if (empty($data['order_id']) 
			|| empty($data['action'])
			|| empty($data['method'])
		) {
			Nuvei_Logger::write($data, 'Nuvei_Settle_Void error missing mandatoriy parameters.');
			return false;
		}
		
		$order      = wc_get_order($data['order_id']);
		$curr       = get_woocommerce_currency();
		$tr_curr    = $order->get_meta(NUVEI_TRANS_CURR);
        $notify_url = Nuvei_String::get_notify_url($this->plugin_settings);
		
		if (!empty($tr_curr)) {
			$curr = $tr_curr;
		}
		
		$params = array(
			'clientUniqueId'        => $data['order_id'],
			'amount'                => (string) $order->get_total(),
			'currency'              => $curr,
			'relatedTransactionId'  => $order->get_meta(NUVEI_TRANS_ID),
			'authCode'              => $order->get_meta(NUVEI_AUTH_CODE_KEY),
            'url'                   => $notify_url,
            'urlDetails'            => ['notificationUrl' => $notify_url],
		);

		return $this->call_rest_api($data['method'], $params);
	}
	
	/**
	 * Create Settle and Void
	 * 
	 * @param int $order_id
	 * @param string $action
	 */
	public function create_settle_void( $order_id, $action) {
		$this->is_order_valid($order_id);
		
		$method  = 'settle' == $action ? 'settleTransaction' : 'voidTransaction';
		$nsv_obj = new Nuvei_Settle_Void($this->plugin_settings);
		$resp    = $nsv_obj->process(array(
			'order_id' => $order_id, 
			'action'   => $action, 
			'method'   => $method
		));
		
		if (!empty($resp['status']) && 'SUCCESS' == $resp['status']) {
			$ord_status = 1;
			$this->sc_order->update_status('processing');
		} else {
			$ord_status = 0;
		}
		
		wp_send_json(array('status' => $ord_status, 'data' => $resp));
		exit;
	}

	protected function get_checksum_params() {
		return array('merchantId', 'merchantSiteId', 'clientRequestId', 'clientUniqueId', 'amount', 'currency', 'relatedTransactionId', 'authCode', 'url', 'timeStamp');
	}
}
