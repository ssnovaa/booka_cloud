<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ListenLog;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel; // ⬅️ Фасад Excel
use App\Exports\RoyaltyExport;         // ⬅️ Ваш класс экспорта

class RoyaltyAdminController extends Controller
{
    /**
     * Отображение страницы (с кнопкой "Применить")
     */
    public function index(Request $request)
    {
        // 1. Считаем все данные (БД + Математика)
        $fullData = $this->calculateRawData($request);
        
        // 2. Получаем текущие фильтры из Request
        // Если режим "selected" (галочки), на странице сбрасываем в "all", 
        // так как галочки юзер ставит в браузере.
        $mode = $request->input('export_mode', 'all');
        if ($mode === 'selected') $mode = 'all'; 

        $streamType = $request->input('stream_type', 'all');

        // 3. Фильтруем отчет для отображения в таблице
        $filteredReport = $this->filterReport(
            $fullData['report'], 
            $mode, 
            $streamType, 
            [] // ID не нужны для просмотра, так как галочки еще не нажаты
        );

        // 4. Подменяем отчет в данных на отфильтрованный
        $fullData['report'] = $filteredReport;

        // Добавляем текущие настройки фильтров, чтобы они сохранились в select-ах
        $fullData['export_mode'] = $mode;
        $fullData['stream_type'] = $streamType;

        return view('admin.royalties.index', $fullData);
    }

    /**
     * Экспорт в Excel (.xlsx)
     */
    public function export(Request $request)
    {
        // 1. Считаем все данные
        $fullData = $this->calculateRawData($request);
        
        // 2. Получаем параметры
        $mode = $request->input('export_mode', 'all');
        $streamType = $request->input('stream_type', 'all');
        
        // Получаем ID выбранных чекбоксов (строка "hash1,hash2,hash3")
        $selectedKeys = $request->input('selected_keys', []);
        if (is_string($selectedKeys)) {
            $selectedKeys = array_filter(explode(',', $selectedKeys));
        }

        // 3. Фильтруем отчет
        $filteredReport = $this->filterReport($fullData['report'], $mode, $streamType, $selectedKeys);

        // 4. Генерируем имя файла
        $filename = 'royalty_' . $fullData['month'] . '.xlsx';

        // 5. Отдаем Excel через библиотеку
        return Excel::download(new RoyaltyExport($filteredReport, $streamType), $filename);
    }

    /**
     * Вспомогательный метод: Фильтрация массива результатов
     */
    private function filterReport($report, $mode, $streamType, $selectedKeys = [])
    {
        return array_filter($report, function ($row) use ($mode, $streamType, $selectedKeys) {
            // 1. Фильтр по типу получателя
            if ($mode === 'authors' && $row['is_agency']) return false;
            if ($mode === 'agencies' && !$row['is_agency']) return false;
            
            // 2. Фильтр "Только выбранные" (по ID строки)
            if ($mode === 'selected') {
                if (!in_array($row['key_id'], $selectedKeys)) return false;
            }

            // 3. Фильтр по типу потока (скрываем пустые строки)
            // Если выбрали "Только платные", а у автора 0 платных секунд - скрываем его
            if ($streamType === 'paid' && $row['paid_seconds'] <= 0) return false;
            if ($streamType === 'free' && $row['free_seconds'] <= 0) return false;

            return true;
        });
    }

    /**
     * Вспомогательный метод: Расчет математики (Без фильтрации)
     */
    private function calculateRawData(Request $request)
    {
        $month = $request->input('month', Carbon::now()->format('Y-m'));
        $netIncomeSubs = (float) $request->input('income_subs', 0);
        $netIncomeAds  = (float) $request->input('income_ads', 0);
        $defaultRoyaltyPercent = (float) $request->input('royalty_percent', 40);

        $startDt = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
        $endDt   = Carbon::createFromFormat('Y-m', $month)->endOfMonth();

        // Запрос к БД
        $stats = ListenLog::query()
            ->join('users', 'listen_logs.user_id', '=', 'users.id')
            ->join('a_books', 'listen_logs.a_book_id', '=', 'a_books.id')
            ->join('authors', 'a_books.author_id', '=', 'authors.id')
            ->leftJoin('agencies', 'a_books.agency_id', '=', 'agencies.id') 
            ->whereBetween('listen_logs.created_at', [$startDt, $endDt])
            ->select([
                'authors.name as author_name',
                'authors.royalty_percent as author_rate',
                'a_books.title as book_title',
                'agencies.name as agency_name',       
                'agencies.payment_details as agency_details',
                'agencies.royalty_percent as agency_rate',
                'users.is_paid', 
                DB::raw('SUM(listen_logs.seconds) as total_seconds')
            ])
            ->groupBy(
                'a_books.id', 'a_books.title', 'agencies.name', 'agencies.payment_details',
                'agencies.royalty_percent', 'authors.name', 'authors.royalty_percent', 'users.is_paid'
            )
            ->get();

        // Глобальные пулы
        $globalPaidSeconds = $stats->where('is_paid', true)->sum('total_seconds');
        $globalFreeSeconds = $stats->where('is_paid', false)->sum('total_seconds');

        $ratePaid = $globalPaidSeconds > 0 ? ($netIncomeSubs / $globalPaidSeconds) : 0;
        $rateAds  = $globalFreeSeconds > 0 ? ($netIncomeAds / $globalFreeSeconds) : 0;

        $report = [];

        // Сборка массива
        foreach ($stats as $row) {
            if ($row->agency_name) {
                $payeeName = $row->agency_name;
                $isAgency = true;
                $details = $row->agency_details;
                $appliedPercent = $row->agency_rate ?? $defaultRoyaltyPercent;
            } else {
                $payeeName = $row->author_name;
                $isAgency = false;
                $details = null; 
                $appliedPercent = $row->author_rate ?? $defaultRoyaltyPercent;
            }

            // Уникальный ключ
            $key = md5($payeeName . '_' . $appliedPercent);

            if (!isset($report[$key])) {
                $report[$key] = [
                    'key_id' => $key,
                    'is_agency' => $isAgency,
                    'payee_name' => $payeeName,
                    'details' => $details,
                    'percent' => $appliedPercent,
                    'books_list' => [],
                    'paid_seconds' => 0, 
                    'free_seconds' => 0,
                ];
            }

            $bookInfo = "{$row->book_title} ({$row->author_name})";
            if (!in_array($bookInfo, $report[$key]['books_list'])) {
                $report[$key]['books_list'][] = $bookInfo;
            }

            if ($row->is_paid) {
                $report[$key]['paid_seconds'] += $row->total_seconds;
            } else {
                $report[$key]['free_seconds'] += $row->total_seconds;
            }
        }

        // Финальный расчет денег
        $totalPayout = 0;

        foreach ($report as $key => &$data) {
            $data['earned_subs'] = $data['paid_seconds'] * $ratePaid;
            $data['earned_ads']  = $data['free_seconds'] * $rateAds;
            $data['total_gross'] = $data['earned_subs'] + $data['earned_ads'];
            
            // Расчет выплаты по проценту
            $data['payout']      = $data['total_gross'] * ($data['percent'] / 100);
            
            $totalPayout += $data['payout'];
            
            $allBooks = implode(', ', $data['books_list']);
            $data['books_string'] = (mb_strlen($allBooks) > 150) ? mb_substr($allBooks, 0, 150) . '...' : $allBooks;
        }
        unset($data);

        // Сортировка
        usort($report, fn($a, $b) => $b['payout'] <=> $a['payout']);

        return [
            'month' => $month,
            'income_subs' => $netIncomeSubs,
            'income_ads' => $netIncomeAds,
            'royalty_percent' => $defaultRoyaltyPercent,
            'global_paid_seconds' => $globalPaidSeconds,
            'global_free_seconds' => $globalFreeSeconds,
            'rate_paid' => $ratePaid,
            'rate_ads' => $rateAds,
            'report' => $report,
            'total_income' => $netIncomeSubs + $netIncomeAds,
            'total_payout' => $totalPayout,
            'company_profit' => ($netIncomeSubs + $netIncomeAds) - $totalPayout,
        ];
    }
}