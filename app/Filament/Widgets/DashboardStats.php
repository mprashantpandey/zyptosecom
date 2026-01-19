<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use App\Models\Product;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class DashboardStats extends BaseWidget
{
    protected function getStats(): array
    {
        // Calculate sales
        $totalSales = Order::where('status', '!=', 'cancelled')
            ->sum('total_amount');
        
        $todaySales = Order::where('status', '!=', 'cancelled')
            ->whereDate('created_at', today())
            ->sum('total_amount');
        
        $yesterdaySales = Order::where('status', '!=', 'cancelled')
            ->whereDate('created_at', today()->subDay())
            ->sum('total_amount');
        
        $salesTrend = $yesterdaySales > 0 
            ? (($todaySales - $yesterdaySales) / $yesterdaySales) * 100 
            : 0;

        // Calculate orders
        $totalOrders = Order::count();
        $todayOrders = Order::whereDate('created_at', today())->count();
        $yesterdayOrders = Order::whereDate('created_at', today()->subDay())->count();
        
        $ordersTrend = $yesterdayOrders > 0 
            ? (($todayOrders - $yesterdayOrders) / $yesterdayOrders) * 100 
            : 0;

        // Low stock products
        $lowStockCount = Product::where('track_inventory', true)
            ->whereRaw('stock_quantity <= low_stock_threshold')
            ->count();

        // Failed webhooks (if webhooks table exists)
        $failedWebhooks = 0;
        try {
            $failedWebhooks = \DB::table('webhooks')
                ->where('status', 'failed')
                ->where('created_at', '>=', now()->subDays(7))
                ->count();
        } catch (\Exception $e) {
            // Table might not exist
        }

        return [
            Stat::make('Total Sales', '₹' . number_format($totalSales, 2))
                ->description($salesTrend >= 0 ? '+' . number_format($salesTrend, 1) . '%' : number_format($salesTrend, 1) . '%')
                ->descriptionIcon($salesTrend >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->color($salesTrend >= 0 ? 'success' : 'danger'),
            
            Stat::make('Total Orders', number_format($totalOrders))
                ->description($ordersTrend >= 0 ? '+' . number_format($ordersTrend, 1) . '%' : number_format($ordersTrend, 1) . '%')
                ->descriptionIcon($ordersTrend >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->color($ordersTrend >= 0 ? 'success' : 'warning'),
            
            Stat::make('Low Stock Alerts', $lowStockCount)
                ->description('Products below threshold')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color($lowStockCount > 0 ? 'danger' : 'success'),
            
            Stat::make('Failed Webhooks', $failedWebhooks)
                ->description('Last 7 days')
                ->descriptionIcon('heroicon-m-x-circle')
                ->color($failedWebhooks > 0 ? 'danger' : 'success'),
        ];
    }
}

