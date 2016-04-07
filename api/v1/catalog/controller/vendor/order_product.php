<?php


class ControllerVendorOrderProductAPI extends ApiController {

	public function index($args = array()) {

		//must be logged in to see list of orders - probably still not tight enough security here
		if ($this->user->isLogged()) {

			if (isset($args['id'])) {
				if ($this->request->isGetRequest()) {
					return $this->response->setOutput($this->getVendorOrderProduct($args['id']));
				} elseif ($this->request->isPostRequest()) {
					return $this->response->setOutput($this->editVendorOrderProduct($args['id']));
				}
			}
		}
		else {
			throw new ApiException(ApiResponse::HTTP_RESPONSE_CODE_UNAUTHORIZED, ErrorCodes::ERRORCODE_USER_NOT_LOGGED_IN, "not allowed");
		}
	}

	//Get the products for a specific order belonging to this vendor
	protected function getVendorOrderProduct($id) {

		$this->load->model('sale/vdi_order');

		$orders = $this->model_sale_vdi_order->getOrderProduct($id);

		return $orders;
	}

	protected function editVendorOrderProduct($id) {

		$this->load->model('sale/vdi_order');
		$order_product = $this->model_sale_vdi_order->getOrderProduct($id);

		if (isset($this->request->post['order_status_id'])) {
			$order_product['order_status_id'] = (int)$this->request->post['order_status_id'];
		}

		$orders = $this->model_sale_vdi_order->editOrderProduct($id, $order_product);

		$this->sendEditOrderStatusNotification($order_product['order_status_id'], $id);
	}

    public function sendEditOrderStatusNotification($orderStatusId, $orderProductId) {

		$this->log->write('sendEditOrderStatusNotification ' . $orderStatusId . ', ' . $orderProductId);

		$this->language->load('mail/email_notification');

		$this->load->model('sale/vdi_order');

		//get the order product in question
		//this gives us order_id, product_id, [product] name, model, quantity, price, vendor_id
		$orderProduct = $this->model_sale_vdi_order->getOrderProduct($orderProductId);

		//get the order
		//this gives us customer_id, customer, firstname, lastname, email, shipping_address_1,
		//shipping_address_2, shipping_postcode, shipping_city
		$order = $this->model_sale_vdi_order->getOrder($orderProduct['order_id']);


		$customer_email = $order['email'];
		$customer_name = $order['firstname'] . ' ' . $order['lastname'];
		$shipping_address = $order['shipping_address_1'] . '<br/>\n';
		if (!empty($order['shipping_address_2'])) {
            $shipping_address .= $order['shipping_address_2'] . '<br/>\n';
		}
		$shipping_address .= $order['shipping_city'] . '<br/>\n' .
                            $order['shipping_postcode'] . '<br/>\n' .
		$order_id = $orderProduct['order_id'];
		$productName = $orderProduct['name'];
		$quantity = $orderProduct['quantity'];
		$productTotal = number_format((float)$orderProduct['total'], 2, '.', '');;

		//want 'g:i A, F j, Y'
        $date = date($this->language->get('date_format_email'));

		$this->log->write($customer_email);
		$this->log->write($customer_name);
		$this->log->write($shipping_address);
		$this->log->write($order_id);
		$this->log->write($productName);
		$this->log->write($quantity);
		$this->log->write($date);


        //This is not really what we should be doing, for both subject and email texts.
        //  We hard-code the order status IDs. Really we should DB lookup (oc_order_status)
        //  for the correct status values, or even better have this entirely configurable on the
        //  admin dashboard.

        switch ($orderStatusId) {
            case 3:
                $subject = $this->language->get('text_subject_shipped');
                break;
            case 5:
                $subject = $this->language->get('text_subject_complete');
                break;
            case 7:
                $subject = $this->language->get('text_subject_cancelled');
                break;
        }

		$this->log->write($subject);

        $text = sprintf($this->language->get('text_to'), $customer_name) . "<br><br>";

        switch ($orderStatusId) {
            case 3:
                $text .= sprintf($this->language->get('text_message_shipped'), $order_id, $productName, $date). "<br><br>";
                break;
            case 5:
                $text .= sprintf($this->language->get('text_message_complete'), $productName). "<br><br>";
                break;
            case 7:
                $text .= sprintf($this->language->get('text_message_cancelled'), $date, $productName, $quantity, $productTotal). "<br><br>";
                break;
        }

		$text .= $this->language->get('text_thanks') . "<br>";
		$text .= $this->config->get('config_name') . "<br><br>";
		$text .= $this->language->get('text_system');

		$mail = new Mail();
		$mail->protocol = $this->config->get('config_mail_protocol');
		$mail->parameter = $this->config->get('config_mail_parameter');
		$mail->smtp_hostname = $this->config->get('config_mail_smtp_hostname');
		$mail->smtp_username = $this->config->get('config_mail_smtp_username');
		$mail->smtp_password = html_entity_decode($this->config->get('config_mail_smtp_password'), ENT_QUOTES, 'UTF-8');
		$mail->smtp_port = $this->config->get('config_mail_smtp_port');
		$mail->smtp_timeout = $this->config->get('config_mail_smtp_timeout');

		$mail->setTo($customer_email);
		$mail->setFrom($this->config->get('config_email'));
		$mail->setSender($this->config->get('config_name'));
		$mail->setSubject(html_entity_decode($subject, ENT_QUOTES, 'UTF-8'));
		$mail->setHtml(html_entity_decode($text, ENT_QUOTES, 'UTF-8'));
		$mail->send();

        $this->log->write('message sent to customer: ' . $subject . ' ::: ' . $text);
    }






}
