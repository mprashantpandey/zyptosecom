<?php

namespace App\Core\Contracts;

use App\Models\Order;

interface ShippingProviderInterface
{
    /**
     * Create a shipment
     * 
     * @param Order $order
     * @param array $options Additional options (pickup_location, etc.)
     * @return array ['shipment_id', 'awb_number', 'tracking_url', 'status', 'metadata']
     */
    public function createShipment(Order $order, array $options = []): array;

    /**
     * Cancel a shipment
     * 
     * @param string $shipmentId
     * @return array ['status', 'message']
     */
    public function cancelShipment(string $shipmentId): array;

    /**
     * Get shipping rates
     * 
     * @param array $params ['weight', 'cod_amount', 'pickup_pincode', 'delivery_pincode']
     * @return array List of rate options
     */
    public function getRates(array $params): array;

    /**
     * Track shipment
     * 
     * @param string $awbNumber
     * @return array ['status', 'events', 'current_location', 'metadata']
     */
    public function track(string $awbNumber): array;

    /**
     * Check pincode serviceability
     * 
     * @param string $pincode
     * @return array ['serviceable', 'estimated_days', 'metadata']
     */
    public function checkServiceability(string $pincode): array;
}
