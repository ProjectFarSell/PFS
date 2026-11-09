<?php

return [
    // Snapshotted per shipment at checkout; changes never reprice existing orders.
    'commission_bps' => (int) env('MARKETPLACE_COMMISSION_BPS', 800),
    'rider_pay_cents' => (int) env('MARKETPLACE_RIDER_PAY_CENTS', 4000),
    'cod_cost_cents' => (int) env('MARKETPLACE_COD_COST_CENTS', 700),
    'operating_cost_cents' => (int) env('MARKETPLACE_OPERATING_COST_CENTS', 1500),
    'max_delivery_attempts' => 3,
];
