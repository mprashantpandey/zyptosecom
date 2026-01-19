<?php

namespace App\Core\Providers\Shipping;

use App\Core\Contracts\ShippingProviderInterface;
use App\Core\Services\SecretsService;
use App\Models\Order;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ShiprocketProvider implements ShippingProviderInterface
{
    protected SecretsService $secrets;
    protected string $environment;
    protected array $credentials;
    protected ?string $token = null;

    public function __construct(SecretsService $secrets, string $environment = 'sandbox')
    {
        $this->secrets = $secrets;
        $this->environment = $environment;
        $this->loadCredentials();
    }

    protected function loadCredentials(): void
    {
        $this->credentials = $this->secrets->getCredentials('shipping', 'shiprocket', $this->environment);
    }

    protected function authenticate(): string
    {
        $cacheKey = "shiprocket_token:{$this->environment}";
        
        // Check cache first
        $cached = Cache::get($cacheKey);
        if ($cached) {
            return $cached;
        }

        try {
            $response = Http::post('https://apiv2.shiprocket.in/v1/external/auth/login', [
                'email' => $this->credentials['email'] ?? '',
                'password' => $this->credentials['password'] ?? '',
            ]);

            if ($response->successful()) {
                $data = $response->json();
                $token = $data['token'] ?? null;
                
                if ($token) {
                    // Cache token for 1 hour (Shiprocket tokens typically last longer)
                    Cache::put($cacheKey, $token, now()->addHour());
                    return $token;
                }
            }

            throw new \Exception('Failed to authenticate with Shiprocket: ' . $response->body());

        } catch (\Exception $e) {
            Log::error('Shiprocket authentication failed', [
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    protected function getHeaders(): array
    {
        $token = $this->authenticate();
        return [
            'Authorization' => 'Bearer ' . $token,
            'Content-Type' => 'application/json',
        ];
    }

    public function createShipment(Order $order, array $options = []): array
    {
        try {
            $payload = [
                'order_id' => $order->order_number,
                'order_date' => $order->created_at->format('Y-m-d H:i'),
                'pickup_location' => $options['pickup_location'] ?? $this->credentials['pickup_location_name'] ?? 'Primary',
                'billing_customer_name' => $order->user->name ?? 'Customer',
                'billing_last_name' => '',
                'billing_address' => $order->shipping_address->address_line_1 ?? '',
                'billing_address_2' => $order->shipping_address->address_line_2 ?? '',
                'billing_city' => $order->shipping_address->city ?? '',
                'billing_pincode' => $order->shipping_address->pincode ?? '',
                'billing_state' => $order->shipping_address->state ?? '',
                'billing_country' => $order->shipping_address->country ?? 'India',
                'billing_email' => $order->user->email ?? '',
                'billing_phone' => $order->user->phone ?? '',
                'shipping_is_billing' => true,
                'order_items' => [],
                'payment_method' => $order->payment_method ?? 'Prepaid',
                'sub_total' => $order->total_amount,
                'length' => 10,
                'breadth' => 10,
                'height' => 10,
                'weight' => $options['weight'] ?? 0.5,
            ];

            // Add order items
            foreach ($order->items ?? [] as $item) {
                $payload['order_items'][] = [
                    'name' => $item->product_name ?? 'Product',
                    'sku' => $item->sku ?? '',
                    'units' => $item->quantity ?? 1,
                    'selling_price' => $item->price ?? 0,
                ];
            }

            $response = Http::withHeaders($this->getHeaders())
                ->post('https://apiv2.shiprocket.in/v1/external/orders/create/adhoc', $payload);

            if ($response->successful()) {
                $data = $response->json();
                return [
                    'shipment_id' => $data['order_id'] ?? null,
                    'awb_number' => $data['awb_code'] ?? null,
                    'tracking_url' => $data['tracking_url'] ?? null,
                    'status' => $data['status'] ?? 'created',
                    'metadata' => $data,
                ];
            }

            throw new \Exception('Failed to create Shiprocket shipment: ' . $response->body());

        } catch (\Exception $e) {
            Log::error('Shiprocket createShipment failed', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    public function cancelShipment(string $shipmentId): array
    {
        try {
            $response = Http::withHeaders($this->getHeaders())
                ->post("https://apiv2.shiprocket.in/v1/external/orders/cancel/shipment/awbs", [
                    'awbs' => [$shipmentId],
                ]);

            if ($response->successful()) {
                $data = $response->json();
                return [
                    'status' => 'cancelled',
                    'message' => $data['message'] ?? 'Shipment cancelled',
                ];
            }

            throw new \Exception('Failed to cancel shipment: ' . $response->body());

        } catch (\Exception $e) {
            Log::error('Shiprocket cancelShipment failed', [
                'shipment_id' => $shipmentId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    public function getRates(array $params): array
    {
        try {
            $payload = [
                'pickup_postcode' => $params['pickup_pincode'] ?? '',
                'delivery_postcode' => $params['delivery_pincode'] ?? '',
                'weight' => $params['weight'] ?? 0.5,
                'cod_amount' => $params['cod_amount'] ?? 0,
            ];

            $response = Http::withHeaders($this->getHeaders())
                ->post('https://apiv2.shiprocket.in/v1/external/courier/serviceability/rate', $payload);

            if ($response->successful()) {
                $data = $response->json();
                $rates = [];
                
                foreach ($data['data']['available_courier_companies'] ?? [] as $courier) {
                    $rates[] = [
                        'courier_id' => $courier['courier_company_id'] ?? null,
                        'courier_name' => $courier['courier_name'] ?? '',
                        'rate' => $courier['rate'] ?? 0,
                        'estimated_days' => $courier['estimated_delivery_days'] ?? 0,
                    ];
                }
                
                return $rates;
            }

            throw new \Exception('Failed to get rates: ' . $response->body());

        } catch (\Exception $e) {
            Log::error('Shiprocket getRates failed', [
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    public function track(string $awbNumber): array
    {
        try {
            $response = Http::withHeaders($this->getHeaders())
                ->get("https://apiv2.shiprocket.in/v1/external/courier/track/awb/{$awbNumber}");

            if ($response->successful()) {
                $data = $response->json();
                $trackingData = $data['tracking_data'] ?? [];
                
                return [
                    'status' => $trackingData['shipment_status'] ?? 'unknown',
                    'events' => $trackingData['tracking'] ?? [],
                    'current_location' => $trackingData['current_location'] ?? '',
                    'metadata' => $data,
                ];
            }

            throw new \Exception('Failed to track shipment: ' . $response->body());

        } catch (\Exception $e) {
            Log::error('Shiprocket track failed', [
                'awb_number' => $awbNumber,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    public function checkServiceability(string $pincode): array
    {
        try {
            $response = Http::withHeaders($this->getHeaders())
                ->get("https://apiv2.shiprocket.in/v1/external/courier/serviceability/pincode/{$pincode}");

            if ($response->successful()) {
                $data = $response->json();
                $serviceable = ($data['data']['serviceable'] ?? false) === true;
                
                return [
                    'serviceable' => $serviceable,
                    'estimated_days' => $data['data']['estimated_delivery_days'] ?? 0,
                    'metadata' => $data,
                ];
            }

            return [
                'serviceable' => false,
                'estimated_days' => 0,
                'metadata' => [],
            ];

        } catch (\Exception $e) {
            Log::error('Shiprocket checkServiceability failed', [
                'pincode' => $pincode,
                'error' => $e->getMessage(),
            ]);
            return [
                'serviceable' => false,
                'estimated_days' => 0,
                'metadata' => [],
            ];
        }
    }
}

