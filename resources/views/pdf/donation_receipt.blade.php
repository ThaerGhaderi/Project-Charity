<!DOCTYPE html>
<html dir="rtl" lang="ar">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>إيصال التبرع</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Arial', sans-serif;
            font-weight: normal;
            background: #f8f7ff;
            padding: 40px;
            unicode-bidi: embed;
            color: #333;
            direction: rtl;
        }

        .container {
            max-width: 700px;
            margin: 0 auto;
            background: #ffffff;
            border-radius: 20px;
            padding: 40px;
            border: 2px solid #E2DCF7;
        }

        .header {
            text-align: center;
            border-bottom: 3px solid #2D1B69;
            padding-bottom: 20px;
            margin-bottom: 25px;
        }

        .title {
            font-size: 26px;
            font-weight: normal;
            color: #2D1B69;
            margin-top: 8px;
        }

        .subtitle {
            font-size: 14px;
            color: #8B7FB8;
            margin-top: 4px;
        }

        .receipt-table {
            width: 100%;
            border-collapse: collapse;
            direction: rtl;
        }

        .receipt-table tr {
            border-bottom: 1px solid #EDE8FA;
        }

        .receipt-table tr:last-child {
            border-bottom: none;
        }

        .receipt-table td {
            padding: 12px 0;
            font-size: 14px;
        }

        .label {
            color: #8B7FB8;
            font-weight: normal;
            text-align: right;
            width: 40%;
        }

        .value {
            color: #2D1B69;
            font-weight: normal;
            text-align: left;
            width: 60%;
        }

        .amount-row {
            background: #EDE8FA;
            border-radius: 12px;
            padding: 16px 20px;
            margin: 10px 0;
            display: table;
            width: 100%;
        }

        .amount-row .amount-label,
        .amount-row .amount-value {
            display: table-cell;
            vertical-align: middle;
        }

        .amount-value {
            font-size: 24px;
            font-weight: normal;
            color: #2D1B69;
            text-align: left;
        }

        .status-badge {
            display: inline-block;
            padding: 4px 16px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: normal;
        }

        .status-completed {
            background: #D4EDDA;
            color: #155724;
        }

        .footer {
            text-align: center;
            margin-top: 25px;
            padding-top: 20px;
            border-top: 2px solid #EDE8FA;
            font-size: 12px;
            color: #8B7FB8;
        }

        .footer .thankyou {
            font-size: 16px;
            font-weight: normal;
            color: #2D1B69;
            margin-bottom: 8px;
        }
    </style>
</head>

<body>

    <div class="container">
        <!-- Header -->
        <div class="header">
            <div class="title">إيصال التبرع</div>
            <div class="subtitle">شكراً لدعمك</div>
        </div>

        <table class="receipt-table">
            <tr>
                <td class="label">الحملة</td>
                <td class="value">{{ $campaign_title ?? 'غير محدد' }}</td>
            </tr>
            <tr>
                <td class="label">التصنيف</td>
                <td class="value">{{ $campaign_category ?? 'عامة' }}</td>
            </tr>
            <tr>
                <td class="label">رقم الإيصال</td>
                <td class="value">{{ $receipt_number ?? 'N/A' }}</td>
            </tr>
            <tr>
                <td class="label">المتبرع</td>
                <td class="value">{{ $donor_name ?? 'غير محدد' }}</td>
            </tr>
            <tr>
                <td class="label">طريقة الدفع</td>
                <td class="value">{{ $payment_method ?? 'غير محدد' }}</td>
            </tr>
            <tr>
                <td class="label">التاريخ</td>
                <td class="value">{{ $date ?? now()->format('Y-m-d H:i:s') }}</td>
            </tr>
            <tr>
                <td class="label">الحالة</td>
                <td class="value">
                    <span class="status-badge status-completed">{{ $status ?? 'مكتمل' }}</span>
                </td>
            </tr>
        </table>

        <!-- Amount -->
        <div class="amount-row">
            <span class="amount-label" style="font-weight:normal;color:#2D1B69;">المبلغ</span>
            <span class="amount-value">{{ $currency ?? '$' }} {{ $amount ?? '0.00' }}</span>
        </div>

        <!-- Footer -->
        <div class="footer">
            <div class="thankyou">شكراً لك على تبرعك</div>
            <div>
                هذا الإيصال صادر من المنصة الخيرية<br>
                تاريخ الإصدار: {{ now()->format('Y-m-d H:i:s') }}
            </div>
        </div>
    </div>

</body>

</html>
