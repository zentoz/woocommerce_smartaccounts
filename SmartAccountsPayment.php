<?php

class SmartAccountsPayment
{

    public function __construct($order, $invoice)
    {
        $this->api     = new SmartAccountsApi();
        $this->order   = $order;
        $this->invoice = $invoice["invoice"];
    }

    public function createPayment()
    {
        $settings           = json_decode(get_option("sa_settings"));
        $orderPaymentMethod = $this->order->get_payment_method_title();
        if (isset($settings->paymentMethods) && $settings->paymentMethods->$orderPaymentMethod) {
            $paymentMethod = $settings->paymentMethods->$orderPaymentMethod;
            if ( ! isset($settings->paymentMethodsPaid) || ! $settings->paymentMethodsPaid->$orderPaymentMethod) {
                error_log("Payment method $orderPaymentMethod is not allowed to be marked paid");

                return;
            }
        } else {
            $paymentMethod = $settings->defaultPayment;
        }

        $apiUrl            = "purchasesales/payments:add";
        $body              = new stdClass();
        $body->date        = $this->order->get_date_created()->date("d.m.Y");
        $body->partnerType = "CLIENT";
        $body->clientId    = $this->invoice["clientId"];
        $body->accountType = "BANK";
        $body->accountName = $paymentMethod;
        $body->currency    = $this->order->get_currency();
        $body->amount      = $this->invoice["totalAmount"];
        $body->rows        = [
            [
                "type"   => "CLIENT_INVOICE",
                "id"     => $this->invoice["invoiceId"],
                "amount" => $this->invoice["totalAmount"]
            ]
        ];
        $this->api->sendRequest($body, $apiUrl);
    }
}