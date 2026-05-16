<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 - الصفحة غير موجودة</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .error-container {
            text-align: center;
            color: white;
            padding: 2rem;
        }
        .error-code {
            font-size: 10rem;
            font-weight: bold;
            line-height: 1;
            text-shadow: 4px 4px 8px rgba(0,0,0,0.3);
            animation: pulse 2s ease-in-out infinite;
        }
        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }
        .error-message {
            font-size: 2rem;
            margin: 2rem 0;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        }
        .error-description {
            font-size: 1.2rem;
            margin-bottom: 2rem;
            opacity: 0.9;
        }
        .btn-home {
            background: white;
            color: #667eea;
            padding: 1rem 2.5rem;
            font-size: 1.1rem;
            border-radius: 50px;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }
        .btn-home:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0,0,0,0.3);
            color: #764ba2;
        }
        .icon-404 {
            font-size: 5rem;
            margin-bottom: 1rem;
            animation: bounce 2s ease-in-out infinite;
        }
        @keyframes bounce {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-20px); }
        }
    </style>
</head>
<body>
    <div class="error-container">
        <div class="icon-404">
            <i class="bi bi-exclamation-triangle"></i>
        </div>
        <div class="error-code">404</div>
        <div class="error-message">الصفحة غير موجودة</div>
        <div class="error-description">
            عذراً، الصفحة التي تبحث عنها غير موجودة أو تم نقلها
        </div>
        <a href="/" class="btn-home">
            <i class="bi bi-house-door me-2"></i>
            العودة للصفحة الرئيسية
        </a>
    </div>
</body>
</html>

