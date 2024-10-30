<?php

namespace Click2pay_Payments\Gateways;

defined('ABSPATH') || exit;

use Click2pay_Payments\Credit_Card\WC_Payment_Token_CC_Click2pay;
use Exception;
use WC_Payment_Tokens;

class Credit_Card_Subscriptions extends Credit_Card
{
    public $supports = [
        'products',
        'refunds',
        'tokenization',
        'subscriptions',
        'subscription_cancellation',
        'subscription_suspension',
        'subscription_reactivation',
        'subscription_amount_changes',
        'subscription_date_changes',
        'subscription_payment_method_change',
        'subscription_payment_method_change_customer',
        'subscription_payment_method_change_admin',
        'multiple_subscriptions',
    ];

    /**
     * Constructor for the gateway.
     */
    public function __construct()
    {
        parent::__construct();

        add_action('woocommerce_scheduled_subscription_payment_' . $this->id, [$this, 'scheduled_subscription_payment'], 10, 2);

        // credit card changes and failed payments
        add_action('woocommerce_subscription_failing_payment_method_updated_' . $this->id, [$this, 'failing_payment_method_updated'], 10, 2);

        add_filter('woocommerce_subscription_payment_meta', [$this, 'add_subscription_payment_meta'], 10, 2);

        add_filter('woocommerce_subscription_status_active', [$this, 'save_subscription_card_token']);

        add_filter('wcs_renewal_order_created', [$this, 'delete_custom_meta_fom_renewal_order'], 100, 2);
    }

    /**
     * Check if the gateway is available to take payments.
     *
     * @return bool
     */
    public function is_available()
    {
        return parent::is_available();
    }

    public function payment_fields()
    {
        if (0 === $this->get_order_total() && $this->is_subscription()) {
            echo '<div style="font-weight: normal; padding: 5px 10px; border: 1px solid #ccc; color: #585454; border-radius: 5px; font-size: 10px;">' . esc_html__('Uma cobrança de R$ 1,00 será para validar o cartão e estornada imediatamente.', 'click2pay-pagamentos') . '</div>';
        }

        parent::payment_fields();
    }

    /**
     * Process the payment.
     *
     * @param  int  $order_id Order ID.
     * @return array Redirect data.
     */
    public function process_payment($order_id)
    {
        try {

            $is_change_method = isset($_POST['_wcsnonce']) && wp_verify_nonce(wc_clean(wp_unslash($_POST['_wcsnonce'])), 'wcs_change_payment_method');

            $subscription = null;

            if ($is_change_method || 0 === $this->get_order_total() && $this->is_subscription()) {
                $order = wc_get_order($order_id);

                if ('shop_subscription' === $order->get_type()) {
                    $subscription = $order;
                    $order = $order->get_parent();
                }

                $card_hash = isset($_POST[$this->id . '_hash']) ? wc_clean(wp_unslash($_POST[$this->id . '_hash'])) : '';
                $card_token = isset($_POST['wc-' . $this->id . '-payment-token']) ? wc_clean(wp_unslash($_POST['wc-' . $this->id . '-payment-token'])) : '';

                $token = $this->save_credit_card_without_payment($order, $card_hash, $card_token);

                // remove to tokens to add again
                $tokens = WC_Payment_Tokens::get_order_tokens($order->get_id());
                foreach ($tokens as $_token) {
                    $tokens = WC_Payment_Tokens::delete($_token->get_id());
                }

                $order->add_payment_token($token);
                $order->save();

                if ($is_change_method && $subscription) {

                    $subscription = wc_get_order($subscription->get_id()); // refresh from DB
                    $this->save_subscription_card_token($subscription);
                }

                // mark as paid
                $order->payment_complete();

                return [
                    'result'   => 'success',
                    'redirect' => $this->get_return_url($order),
                ];
            }

            return parent::process_payment($order_id);

        } catch (Exception $e) {
            // o changing method, show the error
            if (isset($_POST['woocommerce_change_payment'])) {
                wc_add_notice(__('Não foi possível processar sua solicitação. Entre em contato para obter assistência ou tente novamente.'), 'error');

                return false;
            }

            throw new Exception($e->getMessage(), $e->getCode());
        }

        return [
            'result'   => 'success',
            'redirect' => $this->get_return_url($order),
        ];
    }

    public function get_available_installments()
    {
        if ($this->is_subscription()) {
            return [];
        }

        return parent::get_available_installments();
    }

    public function should_save_credit_card()
    {
        if ($this->is_subscription()) {
            return true;
        }

        return parent::should_save_credit_card();
    }

    /**
     * Outputs a checkbox for saving a new payment method to the database.
     *
     * @since 2.6.0
     */
    public function save_payment_method_checkbox()
    {
        if ($this->is_subscription()) {
            return '';
        }

        return parent::save_payment_method_checkbox();
    }

    /**
     * Charge recurring subscription
     *
     * @param  float  $amount_to_charge
     * @param  WC_Order  $renewal_order
     * @return void
     */
    public function scheduled_subscription_payment($amount_to_charge, $renewal_order)
    {
        $order = $renewal_order;

        // using saved card!
        try {
            $card_token = $order->get_meta('_click2pay_card_id');

            if (! $card_token) {
                $subscription = wc_get_order( $order->get_meta('_subscription_renewal') );

                if ( $subscription->get_parent() ) {
                    $tokens = \WC_Payment_Tokens::get_order_tokens($subscription->get_parent()->get_id());

                    foreach ( $tokens as $token ) {
                        $card_token = $token->get_token();
                        break;
                    }
                }

                if(! $card_token ) {
                    throw new Exception(__('Token não encontrado no pedido.', 'click2pay-pagamentos'));
                }
            }

            $response_data = $this->api->create_transaction($order, 1, false, $card_token, false);

            $data = $response_data->data;

            if (isset($response_data->error, $response_data->errorDescription)) {
                throw new Exception(sprintf(__('Ocorreu um erro ao processar sua solicitação: %s', 'click2pay-pagamentos'), $response_data->errorDescription));
            }

            $order->set_transaction_id($data->tid);
            $order->update_meta_data('_click2pay_external_identifier',
                $data->externalIdentifier);
            $order->update_meta_data('_click2pay_data', $data);
            $order->update_meta_data('_click2pay_installment_data', []);
            $order->update_meta_data('_click2pay_total_amount', $data->totalAmount);

            if ('paid' === $data->status) {
                $order->add_order_note(__('Pagamento já confirmado pela Click2Pay.', 'click2pay-pagamentos'));

                $order->payment_complete();

            } else {
                $order->set_status('on-hold', __('Pagamento iniciado. Aguardando confirmação.', 'click2pay-pagamentos'));
            }

            $order->save();

        } catch (Exception $e) {
            $order->update_status('failed', sprintf(__('Não foi possível processar o pagamento com cartão salvo: %s', 'click2pay-pagamentos'), $e->getMessage()));
        }
    }

    public function failing_payment_method_updated($subscription, $renewal_order)
    {
    }

    /**
     * Include the payment meta data required to process automatic recurring payments so that store managers can
     * manually set up automatic recurring payments for a customer via the Edit Subscriptions screen in 2.0+.
     *
     * @since 2.5
     *
     * @param  array  $payment_meta associative array of meta data required for automatic payments
     * @param  WC_Subscription  $subscription An instance of a subscription object
     * @return array
     */
    public function add_subscription_payment_meta($payment_meta, $subscription)
    {
        $payment_meta[$this->id] = [
            'post_meta' => [
                '_click2pay_card_id' => [
                    'value' => get_post_meta($subscription->get_id(), '_click2pay_card_id', true ),
                    'label' => __('ID do cartão Click2Pay', 'click2pay-pagamentos'),
                ],
            ],
        ];

        return $payment_meta;
    }

    /**
     * Add payment method via account screen. This should be extended by gateway plugins.
     *
     * @since 3.2.0 Included here from 3.2.0, but supported from 3.0.0.
     *
     * @return array
     */
    public function save_credit_card_without_payment($order, $card_hash = false, $card_token = false)
    {
        $capture = false;
        $save_card = true;

        $token = null;

        $this->log('save_credit_card_without_payment for order ' . $order->get_id());

        // using saved card!
        if (is_numeric($card_token)) {
            $card_hash = null;
            $save_card = false;

            try {
                $token = new WC_Payment_Token_CC_Click2pay($card_token);

                if (! $token->get_token()) {
                    throw new Exception(__('Token não disponível.', 'click2pay-pagamentos'));
                }

                $card_token = $token->get_token();

            } catch (Exception $e) {
                $order->add_order_note(sprintf(__('Não foi possível processar o pagamento com cartão salvo: %s', 'click2pay-pagamentos'), $e->getMessage()));

                $this->log('Error: ' . $e->getMessage());

                throw new Exception(__('Não foi possível processar o pagamento com cartão salvo. Por favor, adicione um novo cartão.', 'click2pay-pagamentos'));
            }
        }

        $response_data = $this->api->create_transaction($order, 1, $card_hash, $card_token, $save_card, $capture, 1.00);

        $this->log('Result: ' . print_r($response_data, true));

        $status = isset($response_data->data->status) ? $response_data->data->status : __('Status desconhecido', 'click2pay-pagamentos');

        if (isset($response_data->error)) {
            $order->add_order_note(sprintf(__('Não foi possível validar o cartão de crédito com a cobrança de R$ 1,00. %s', 'click2pay-pagamentos'), $response_data->errorDescription));

            throw new Exception(sprintf(__('Não foi possível validar seu cartão: %s.', 'click2pay-pagamentos'), $response_data->errorDescription));
        } elseif ('pre_authorized' !== $status) {
            $order->add_order_note(sprintf(__('Não foi possível validar o cartão de crédito com a cobrança de R$ 1,00. Status da tentativa: %s', 'click2pay-pagamentos'), $status));

            throw new Exception(__('Não foi possível validar seu cartão. Tente novamente ou utilize outro cartão.', 'click2pay-pagamentos'));
        }

        $order->add_order_note(__('Transação de R$ 1,00 validada com sucesso!', 'click2pay-pagamentos'));

        $order->set_transaction_id($response_data->data->tid);
        $order->update_meta_data('_click2pay_external_identifier',
            $response_data->data->externalIdentifier);
        $order->update_meta_data('_click2pay_data', $response_data->data);
        $order->update_meta_data('_click2pay_installment_data', []);
        $order->update_meta_data('_click2pay_total_amount', $response_data->data->totalAmount);
        $order->save();

        try {
            $response_refud_data = $this->api->refund_transaction($order, 1.00);
            $response_refud_data = json_decode($response_refud_data['body']);

            if (isset($response_refud_data->error, $response_refud_data->errorDescription)) {
                throw new Exception(sprintf(__('Ocorreu um erro ao processar o reembolso da transação de validação: %s', 'click2pay-pagamentos'), $response_data->errorDescription));
            }

            $order->add_order_note(__('Transação de R$ 1,00 cancelada com sucesso!', 'click2pay-pagamentos'));

            $this->log('Paymet refunded! ' . print_r($response_refud_data, true));
        } catch (\Exception $e) {
            $order->add_order_note(sprintf(__('Não foi possível reembolsar a transação: %s', 'click2pay-pagamentos'), $e->getMessage()));

            $this->log('Error on refund: ' . $e->getMessage());
        }

        // using a saved card
        if ($token && $card_token && ! $card_hash) {
            return $token;
        }

        return $this->save_card_token($response_data->data->card);
    }

    /**
     * We need to copy the mian order card token
     * to our subscription, so we can take advantage
     * of custom features such as
     * "update payment method for all subscriptions"
     *
     * @param  WC_Subscription  $subscription
     * @return void
     */
    public function save_subscription_card_token($subscription)
    {
        $order = $subscription->get_parent();

        $tokens = WC_Payment_Tokens::get_order_tokens($order->get_id());

        foreach ($tokens as $token) {
            update_post_meta( $subscription->get_id(), '_click2pay_card_id', $token->get_token());
            break;
        }
    }

    public function delete_custom_meta_fom_renewal_order($renewal_order, $subscription)
    {
        $renewal_order->delete_meta_data('_click2pay_external_identifier');
        $renewal_order->delete_meta_data('_click2pay_data');
        $renewal_order->delete_meta_data('_click2pay_data_refund');
        $renewal_order->delete_meta_data('_click2pay_installment_data');
        $renewal_order->delete_meta_data('_click2pay_total_amount');

        $renewal_order->save();

        return $renewal_order;
    }
}
