<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class RoyaltyExport implements FromArray, WithHeadings, WithStyles, ShouldAutoSize
{
    protected $report;
    protected $streamType;

    public function __construct(array $report, string $streamType)
    {
        $this->report = $report;
        $this->streamType = $streamType;
    }

    public function array(): array
    {
        $rows = [];

        foreach ($this->report as $row) {
            $line = [
                $row['payee_name'],
                $row['is_agency'] ? 'Агентство' : 'Автор',
                $row['details'] ?? '',
                $row['percent'] . '%',
                $row['books_string'],
            ];

            // Paid данные
            if ($this->streamType === 'all' || $this->streamType === 'paid') {
                $line[] = $row['paid_seconds'];
                $line[] = round($row['earned_subs'], 4);
            }

            // Free данные
            if ($this->streamType === 'all' || $this->streamType === 'free') {
                $line[] = $row['free_seconds'];
                $line[] = round($row['earned_ads'], 4);
            }

            // ИТОГО
            $finalPayout = 0;
            if ($this->streamType === 'all') {
                $finalPayout = $row['payout'];
            } elseif ($this->streamType === 'paid') {
                $finalPayout = $row['earned_subs'] * ($row['percent'] / 100);
            } elseif ($this->streamType === 'free') {
                $finalPayout = $row['earned_ads'] * ($row['percent'] / 100);
            }
            
            $line[] = round($finalPayout, 2);

            $rows[] = $line;
        }

        return $rows;
    }

    public function headings(): array
    {
        $headers = ['Получатель', 'Тип', 'Реквизиты', 'Ставка', 'Книги'];

        if ($this->streamType === 'all' || $this->streamType === 'paid') {
            $headers[] = 'Секунды (Paid)';
            $headers[] = 'Доход (Paid) $';
        }
        if ($this->streamType === 'all' || $this->streamType === 'free') {
            $headers[] = 'Секунды (Free)';
            $headers[] = 'Доход (Free) $';
        }

        $headers[] = 'К ВЫПЛАТЕ ($)';

        return $headers;
    }

    public function styles(Worksheet $sheet)
    {
        return [
            // Первая строка (заголовки) - жирный шрифт
            1 => ['font' => ['bold' => true]],
        ];
    }
}