<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>تم الدفع بنجاح</title>
    <style>
        body {
            font-family: 'Cairo', Arial, sans-serif;
            text-align: center;
            padding: 50px;
            background: #f0f8ff;
        }
        .container {
            max-width: 500px;
            margin: 0 auto;
            background: white;
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        }
        .success { color: #27ae60; font-size: 60px; }
        h1 { color: #2D1B69; }
        .btn {
            display: inline-block;
            padding: 12px 30px;
            background: #2D1B69;
            color: white;
            border-radius: 10px;
            text-decoration: none;
            margin-top: 20px;
        }
        .donation-id {
            background: #EDE8FA;
            padding: 10px;
            border-radius: 10px;
            margin: 15px 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="success">✅</div>
        <h1>تم الدفع بنجاح! 🎉</h1>
        <p>شكراً لك على تبرعك. تم تأكيد عملية الدفع.</p>
        <div class="donation-id">
            <strong>رقم التبرع:</strong> #{{ request('donation') ?? 'غير محدد' }}
        </div>
        <a href="/" class="btn">العودة إلى الرئيسية</a>
    </div>
</body>
</html>