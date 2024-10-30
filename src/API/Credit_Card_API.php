<?php

namespace Click2pay_Payments\API;

use Exception;

defined('ABSPATH') || exit;

class Credit_Card_API extends API
{
    public function create_transaction($order, $installments, $card_hash = null, $card_token = null, $save_card = false, $capture = true, $total_amount = false)
    {
        if (! $card_token && ! $card_hash) {
            throw new Exception(__('Os dados do cartão não foram encontrados. Tente novamente.', 'click2pay-pagamentos'));
        }

        if (! $total_amount) {
            $total_amount = $order->get_total();
        }

        $birthdate = $this->gateway->get_birthdate($order);

        if ($order->get_shipping_address_1() && $order->get_meta('_shipping_number')) {
            $address = [
                'place'        => $order->get_shipping_address_1(),
                'number'       => $order->get_meta('_shipping_number'),
                'complement'   => $order->get_shipping_address_2(),
                'neighborhood' => $order->get_meta('_shipping_neighborhood'),
                'city'         => $order->get_shipping_city(),
                'state'        => $order->get_shipping_state(),
                'zipcode'      => $this->gateway->only_numbers($order->get_shipping_postcode()),
            ];
        } else {
            $address = [
                'place'        => $order->get_billing_address_1(),
                'number'       => $order->get_meta('_billing_number'),
                'complement'   => $order->get_billing_address_2(),
                'neighborhood' => $order->get_meta('_billing_neighborhood'),
                'city'         => $order->get_billing_city(),
                'state'        => $order->get_billing_state(),
                'zipcode'      => $this->gateway->only_numbers($order->get_billing_postcode()),
            ];
        }

        $args = [
            'id'              => $this->gateway->prefix . $order->get_id() . uniqid('|'),
            'totalAmount'     => $total_amount,
            'payerInfo'       => [
                'name'        => $order->get_formatted_billing_full_name(),
                'taxid'       => $this->gateway->get_order_document($order),
                'phonenumber' => $this->gateway->get_order_phone($order),
                'email'       => $order->get_billing_email(),
                'birth_date'  => $birthdate,
                'address'     => $address,
            ],
            'softDescriptor'  => sanitize_text_field(substr($this->gateway->soft_descriptor, 0, 50)),
            'capture'         => $capture,
            'saveCard'        => $save_card,
            'recurrent'       => false,
            'installments'    => intval($installments),
            'callbackAddress' => WC()->api_request_url($this->gateway->id),
        ];

        if ($card_hash) {
            $args['cardHash'] = sanitize_text_field($card_hash);
        } else {
            $args['card_token'] = sanitize_text_field($card_token);
            $args['recurrent'] = true;
        }

        $args = apply_filters($this->gateway->id . '_transaction_args', $args, $this);

        $response = $this->do_request('transactions/creditcard', $args);

        $body = json_decode($response['body']);

        $this->gateway->log('response:' . print_r($body, true));

        return $body;
    }

    public function refund_transaction($order, $amount = null)
    {
        $amount = $amount ? $amount : $order->get_total();

        $args = [
            'totalAmount' => wc_format_decimal(sanitize_text_field($amount), 2),
        ];

        $response = $this->do_request(sprintf('transactions/creditcard/%s/refund', $order->get_transaction_id()), $args);

        $this->gateway->log('refund response:' . print_r($response, true));

        return $response;
    }
}
