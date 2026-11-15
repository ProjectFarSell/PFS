<?php

return [
    // Platform planning estimate, not a live carrier quotation or a packed-box measurement.
    'volumetric_divisor' => (int) env('SHIPPING_VOLUMETRIC_DIVISOR', 5000),
];
