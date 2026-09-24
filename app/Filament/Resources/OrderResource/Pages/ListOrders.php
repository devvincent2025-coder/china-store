<?php

namespace App\Filament\Resources\OrderResource\Pages;

use App\Filament\Resources\OrderResource;
use App\Filament\Support\CsvExporter;
use App\Filament\Widgets\VendorEarningsWidget;
use App\Models\Order;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListOrders extends ListRecords
{
    protected static string $resource = OrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('export')
                ->label('Export CSV')
                ->icon('heroicon-o-arrow-down-tray')
                ->action(fn () => $this->exportCsv()),
            Actions\Action::make('exportPdf')
                ->label('Export PDF')
                ->icon('heroicon-o-document-text')
                ->color('gray')
                ->url(route('admin.exports.orders', ['format' => 'pdf']))
                ->openUrlInNewTab(),
            Actions\Action::make('exportExcel')
                ->label('Export Excel')
                ->icon('heroicon-o-table-cells')
                ->color('gray')
                ->url(route('admin.exports.orders', ['format' => 'xlsx']))
                ->openUrlInNewTab(),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            VendorEarningsWidget::class,
        ];
    }

    private function exportCsv()
    {
        $rows = Order::with('user')->latest()->get()->map(fn (Order $order) => [
            $order->order_number,
            $order->user->name,
            $order->user->email,
            number_format($order->total / 100, 2),
            $order->payment_status,
            $order->created_at->toDateTimeString(),
        ]);

        return CsvExporter::stream(
            'orders-'.now()->format('Y-m-d').'.csv',
            ['Order #', 'Buyer', 'Email', 'Total (NGN)', 'Payment Status', 'Placed At'],
            $rows,
        );
    }
}
