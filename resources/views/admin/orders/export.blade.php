<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title>{{ is_site_mode_b() ? '岚山履行任务单' : '岚山发货单' }} | Arashiyama Orders</title>
    <style>
        @php
            $ipaFontCandidates = [
                public_path('fonts/ipa-gothic.ttf'),
                storage_path('fonts/ipa-gothic.ttf'),
                public_path('fonts/IPAGothic.ttf'),
                storage_path('fonts/IPAGothic.ttf'),
            ];
            $ipaFontPath = null;
            foreach ($ipaFontCandidates as $candidate) {
                if (file_exists($candidate)) {
                    $ipaFontPath = $candidate;
                    break;
                }
            }
        @endphp
        @if($ipaFontPath)
        @font-face {
            font-family: 'IPA Gothic';
            src: url('file://{{ str_replace('\\', '/', $ipaFontPath) }}') format('truetype');
            font-weight: normal;
            font-style: normal;
        }
        @endif
        @page {
            margin: 18mm 12mm;
        }
        body {
            font-family: 'IPA Gothic', 'DejaVu Sans', sans-serif;
            font-size: 11px;
            color: #222;
            line-height: 1.45;
        }
        .sheet-title {
            text-align: center;
            margin-bottom: 12px;
        }
        .sheet-title h1 {
            font-size: 20px;
            margin: 0 0 4px;
        }
        .sheet-title .subtitle {
            font-size: 12px;
            color: #666;
        }
        .meta {
            margin: 0 0 14px;
            padding: 8px 10px;
            border: 1px solid #ccc;
            background: #fafafa;
        }
        .section {
            margin-top: 14px;
        }
        .section-title {
            font-size: 14px;
            font-weight: bold;
            margin: 0 0 8px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            border: 1px solid #333;
            padding: 6px 8px;
            vertical-align: top;
            word-break: break-word;
        }
        th {
            background: #efefef;
            text-align: left;
        }
        .summary-table th:nth-child(2),
        .summary-table td:nth-child(2),
        .detail-table th:nth-child(4),
        .detail-table td:nth-child(4),
        .detail-table th:nth-child(5),
        .detail-table td:nth-child(5) {
            text-align: center;
        }
        .note {
            color: #666;
            font-size: 10px;
        }
        .page-break {
            page-break-after: always;
        }
    </style>
</head>
<body>
    <div class="sheet-title">
        <h1>{{ is_site_mode_b() ? '岚山履行任务单' : '岚山发货单' }} | Arashiyama Orders</h1>
        <div class="subtitle">{{ is_site_mode_b() ? '任务履行集計表 / 转寄清单' : '备货集計表 / 配送リスト' }}</div>
    </div>

    <div class="meta">
        <div>导出时间(出力日時)：{{ $generatedAt->format('Y-m-d H:i:s') }}</div>
        <div>统计区间(集計期間)：{{ $startAt->format('Y-m-d H:i:s') }} ～ {{ $endAt->format('Y-m-d H:i:s') }}</div>
        <div>订单数量(注文件数)：{{ $totalOrders }}</div>
        <div>商品总件数(合計数量)：{{ $totalItems }}</div>
    </div>

    <div class="section">
        <div class="section-title">第一部分：备货汇总表 | 集計表 - Summary</div>
        <table class="summary-table">
            <thead>
                <tr>
                    <th>商品名(品名)</th>
                    <th>总数量(合計数量)</th>
                </tr>
            </thead>
            <tbody>
                @forelse($summaryRows as $row)
                    <tr>
                        <td>{{ $row['display_name'] }}</td>
                        <td>{{ $row['total_qty'] }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="2">暂无订单数据 / データなし</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="page-break"></div>

    <div class="section">
        <div class="section-title">{{ is_site_mode_b() ? '第二部分：详细履行清单 | 转寄リスト - Details' : '第二部分：详细发货单 | 配送リスト - Details' }}</div>
        <table class="detail-table">
            <thead>
                <tr>
                    <th>收件人(氏名)</th>
                    <th>电话(電話番号)</th>
                    <th>地址(住所)</th>
                    <th>烟草简称(品名)</th>
                    <th>数量(数量)</th>
                </tr>
            </thead>
            <tbody>
                @forelse($detailRows as $row)
                    <tr>
                        <td>{{ $row['recipient'] }}</td>
                        <td>{{ $row['phone'] }}</td>
                        <td>{{ $row['address'] }}</td>
                        <td>{{ $row['display_name'] }}</td>
                        <td>{{ $row['qty'] }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5">暂无订单数据 / データなし</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        <p class="note">地址保留原始输入内容，便于按日本地址格式直接核对。</p>
    </div>
</body>
</html>
