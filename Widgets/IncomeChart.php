<?php

namespace App\Filament\Widgets;

use App\Models\Transaction;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;
use Flowframe\Trend\Trend;
use Flowframe\Trend\TrendValue;

class IncomeChart extends ChartWidget
{
    use HasWidgetShield;

    protected static ?string $heading = 'Income Chart';

    protected static string $color = 'info';

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    public ?string $filter = 'year';

    protected function getData(): array
    {
        $activeFilter = $this->filter;

        $trendData = Trend::model(Transaction::class)->between(now()->startOf($activeFilter), now()->endOf($activeFilter));

        $data = $this->getIntervalData($trendData)->sum('price');

        return [
            'datasets' => [
                [
                    'label' => 'Income ($)',
                    'data' => $data->map(fn (TrendValue $value) => $value->aggregate),
                ],
            ],
            'labels' => $data->map(fn (TrendValue $value) => $value->date),
        ];
    }

    public function getIntervalData(Trend $trendData): Trend
    {
        return match ($this->filter) {
            'day' => $trendData->perHour(),
            'week', 'month' => $trendData->perDay(),
            'year' => $trendData->perMonth(),
        };
    }

    protected function getFilters(): ?array
    {
        return [
            'day' => 'Today',
            'week' => 'Current week',
            'month' => 'Current month',
            'year' => 'Current year',
        ];
    }

    protected function getOptions(): RawJs
    {
        return RawJs::make(<<<'JS'
        {
            scales: {
                y: {
                    title: {
                        display: true,
                        text: 'Income'
                    },
                    ticks: {
                        callback: (value) => '$' + value,
                    },
                },
                x: {
                    title: {
                        display: true,
                        text: 'Interval'
                    }
                },
            },
        }
    JS);
    }

    protected function getType(): string
    {
        return 'line';
    }
}
