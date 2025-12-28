@extends('layouts.app')

@section('content')
    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            
            <h2 class="font-semibold text-xl text-gray-800 leading-tight mb-6">
                💰 Расчет Роялти (Pro-Rata)
            </h2>
            
            <form method="POST" id="mainForm" class="mb-8">
                @csrf
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6 p-6">
                    <div class="grid grid-cols-1 md:grid-cols-5 gap-4 items-end">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Отчетный месяц</label>
                            <input type="month" name="month" value="{{ $month }}" 
                                   class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Доход (Subs) $</label>
                            <input type="number" step="0.01" name="income_subs" value="{{ $income_subs }}" 
                                   class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Доход (Ads) $</label>
                            <input type="number" step="0.01" name="income_ads" value="{{ $income_ads }}" 
                                   class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Базовая доля (%)</label>
                            <input type="number" step="1" name="royalty_percent" value="{{ $royalty_percent }}" 
                                   class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
                        </div>
                        
                        <div class="md:col-span-5 border-t pt-4 mt-2 grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Кого показывать/экспортировать?</label>
                                <select name="export_mode" id="export_mode" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
                                    <option value="all" {{ $export_mode == 'all' ? 'selected' : '' }}>Все авторы и агенты</option>
                                    <option value="authors" {{ $export_mode == 'authors' ? 'selected' : '' }}>Только авторы</option>
                                    <option value="agencies" {{ $export_mode == 'agencies' ? 'selected' : '' }}>Только агенты</option>
                                    <option value="selected">Только выбранные (галочкой)</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Тип прослушиваний</label>
                                <select name="stream_type" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
                                    <option value="all" {{ $stream_type == 'all' ? 'selected' : '' }}>Платное + Бесплатное</option>
                                    <option value="paid" {{ $stream_type == 'paid' ? 'selected' : '' }}>Только Платное (Paid)</option>
                                    <option value="free" {{ $stream_type == 'free' ? 'selected' : '' }}>Только Бесплатное (Free)</option>
                                </select>
                            </div>
                            
                            <div class="flex gap-2">
                                <button type="submit" formaction="{{ route('admin.royalties.index') }}" formmethod="GET"
                                    class="flex-1 bg-gray-600 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded shadow">
                                    🔄 Применить
                                </button>
                                
                                <button type="button" onclick="submitExport()"
                                    class="flex-1 bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded shadow flex justify-center items-center gap-2">
                                    📥 Excel
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <input type="hidden" name="selected_keys" id="selected_keys_input">
            </form>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                <div class="bg-green-50 p-4 rounded border-l-4 border-green-500">
                    <h3 class="font-bold text-green-700">Subs Пул</h3>
                    <p class="text-sm">Бюджет: ${{ $income_subs }}</p>
                    <p class="text-xs text-gray-600">Rate: ${{ number_format($rate_paid, 8) }}</p>
                </div>
                <div class="bg-yellow-50 p-4 rounded border-l-4 border-yellow-500">
                    <h3 class="font-bold text-yellow-700">Ads Пул</h3>
                    <p class="text-sm">Бюджет: ${{ $income_ads }}</p>
                    <p class="text-xs text-gray-600">Rate: ${{ number_format($rate_ads, 8) }}</p>
                </div>
                <div class="bg-white border border-gray-200 p-4 rounded flex flex-col justify-center shadow-sm">
                    <div class="flex justify-between text-sm">
                        <span>Выплаты (по фильтру):</span>
                        <span class="font-bold text-red-600">-${{ number_format($total_payout, 2) }}</span>
                    </div>
                    <div class="flex justify-between text-lg mt-2 pt-2 border-t">
                        <span class="font-bold text-gray-700">Прибыль компании:</span>
                        <span class="font-bold text-green-600">+${{ number_format($company_profit, 2) }}</span>
                    </div>
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-3 py-3 text-center w-10">
                                    <input type="checkbox" id="selectAll" class="rounded border-gray-300 text-blue-600 shadow-sm focus:ring-blue-500">
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Получатель</th>
                                <th class="px-3 py-3 text-center text-xs font-medium text-gray-500 uppercase">Ставка %</th>
                                
                                @if($stream_type == 'all' || $stream_type == 'paid')
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Paid ($)</th>
                                @endif
                                
                                @if($stream_type == 'all' || $stream_type == 'free')
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Free ($)</th>
                                @endif
                                
                                <th class="px-6 py-3 text-right text-xs font-bold text-gray-900 uppercase bg-gray-100">ВЫПЛАТА</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @forelse($report as $row)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-3 py-4 text-center">
                                        <input type="checkbox" name="row_select" value="{{ $row['key_id'] }}" class="row-checkbox rounded border-gray-300 text-blue-600 shadow-sm focus:ring-blue-500">
                                    </td>
                                    
                                    <td class="px-6 py-4 text-sm text-gray-900">
                                        <div class="font-bold">
                                            {{ $row['payee_name'] }}
                                            @if($row['is_agency'])
                                                <span class="ml-2 px-2 py-0.5 rounded text-xs bg-purple-100 text-purple-800">Агентство</span>
                                            @endif
                                        </div>
                                        <div class="text-xs text-gray-500 mt-1 truncate max-w-xs" title="{{ $row['books_string'] }}">
                                            {{ $row['books_string'] }}
                                        </div>
                                    </td>

                                    <td class="px-3 py-4 text-center text-sm font-bold text-blue-600">
                                        {{ $row['percent'] }}%
                                    </td>

                                    @if($stream_type == 'all' || $stream_type == 'paid')
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-green-600 text-right">
                                        <span class="text-xs text-gray-400">{{ number_format($row['paid_seconds']) }}s</span><br>
                                        <b>${{ number_format($row['earned_subs'], 2) }}</b>
                                    </td>
                                    @endif

                                    @if($stream_type == 'all' || $stream_type == 'free')
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-yellow-600 text-right">
                                        <span class="text-xs text-gray-400">{{ number_format($row['free_seconds']) }}s</span><br>
                                        <b>${{ number_format($row['earned_ads'], 2) }}</b>
                                    </td>
                                    @endif
                                    
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-right font-bold text-blue-700 bg-blue-50">
                                        @php
                                            $finalRowPayout = 0;
                                            if ($stream_type == 'all') {
                                                $finalRowPayout = $row['payout'];
                                            } elseif ($stream_type == 'paid') {
                                                $finalRowPayout = $row['earned_subs'] * ($row['percent'] / 100);
                                            } elseif ($stream_type == 'free') {
                                                $finalRowPayout = $row['earned_ads'] * ($row['percent'] / 100);
                                            }
                                        @endphp
                                        ${{ number_format($finalRowPayout, 2) }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-6 py-4 text-center text-gray-500">
                                        Нет данных под выбранные фильтры
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>

    <script>
        document.getElementById('selectAll').addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('.row-checkbox');
            checkboxes.forEach(cb => cb.checked = this.checked);
        });

        function submitExport() {
            const form = document.getElementById('mainForm');
            const mode = document.getElementById('export_mode').value;
            
            // Собираем ID, если выбран режим "Selected"
            if (mode === 'selected') {
                const selected = [];
                document.querySelectorAll('.row-checkbox:checked').forEach(cb => {
                    selected.push(cb.value);
                });
                
                if (selected.length === 0) {
                    alert('Выберите хотя бы одну строку для экспорта.');
                    return;
                }
                document.getElementById('selected_keys_input').value = selected.join(',');
            } else {
                document.getElementById('selected_keys_input').value = '';
            }

            // Меняем action и метод на POST для экспорта
            const originalAction = form.action;
            const originalMethod = form.method;
            
            form.action = "{{ route('admin.royalties.export') }}";
            form.method = "POST";
            
            form.submit();
            
            // Возвращаем как было (чтобы кнопка "Применить" работала после экспорта без перезагрузки)
            setTimeout(() => {
                form.action = originalAction;
                form.method = originalMethod; // Хотя у нас там GET через formaction, но на всякий случай
            }, 1000);
        }
    </script>
@endsection