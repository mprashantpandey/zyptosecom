<?php

namespace App\Filament\Widgets;

use App\Models\OrderItem;
use Filament\Widgets\ChartWidget;

class TopProductsChart extends ChartWidget
{
    protected static ?string $heading = 'Top Products by Sales (Last 30 Days)';

    protected int | string | array $columnSpan = 'full';

    protected function getData(): array
    {
        // Get top 10 products by quantity sold in last 30 days
        $topProducts = OrderItem::join('orders', 'order_items.order_id', '=', 'orders.id')
            ->where('orders.status', '!=', 'cancelled')
            ->where('orders.created_at', '>=', now()->subDays(30))
            ->selectRaw('order_items.product_id, SUM(order_items.quantity) as total_quantity, SUM(order_items.total_price) as total_revenue')
            ->groupBy('order_items.product_id')
            ->orderByDesc('total_quantity')
            ->limit(10)
            ->get();

        $labels = [];
        $quantities = [];
        $revenues = [];

        foreach ($topProducts as $item) {
            $product = \App\Models\Product::find($item->product_id);
            $labels[] = $product ? substr($product->name, 0, 20) : 'Product #' . $item->product_id;
            $quantities[] = (int) $item->total_quantity;
            $revenues[] = (float) $item->total_revenue;
        }

        return [
            'datasets' => [
                [
                    'label' => 'Quantity Sold',
                    'data' => $quantities,
                    'backgroundColor' => 'rgba(16, 185, 129, 0.8)',
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}

