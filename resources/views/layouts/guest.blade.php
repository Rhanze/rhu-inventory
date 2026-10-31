<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('images/web-icon.png') }}">
    <title>PHO Supply Portal| Login</title>
    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: linear-gradient(180deg, #d4f0ea 0%, #85e0b5 100%);
            min-height: 100vh;
        }

        .page-center {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
        }

        .card {
            width: 100%;
            max-width: 440px;
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border-radius: 24px;
            padding: 36px 32px;
            border: 1px solid rgba(255, 255, 255, 0.6);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.08);
            text-align: center;
        }

        .logo-placeholder {
            width: 120px;
            height: 120px;
            margin: 0 auto 20px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .logo-placeholder img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }

        .logo-placeholder .logo-text {
            font-size: 28px;
            font-weight: 800;
            color: #0f5c4b;
            letter-spacing: -1px;
        }

        .title {
            font-size: 26px;
            font-weight: 800;
            color: #0f172a;
            margin: 0 0 4px 0;
        }

        .subtitle {
            font-size: 15px;
            color: #475569;
            margin: 0 0 28px 0;
        }

        .form-group {
            margin-bottom: 18px;
            text-align: left;
        }

        label {
            display: block;
            margin-bottom: 6px;
            font-size: 14px;
            font-weight: 600;
            color: #0f172a;
        }

        input {
            width: 100%;
            padding: 12px 16px;
            font-size: 15px;
            border: 2px solid #bec6cf;
            border-radius: 14px;
            outline: none;
            background: #ffffff;
            color: #0f172a;
            transition: border-color 0.2s;
        }

        input:focus {
            border-color: #42ad8a;
            box-shadow: 0 0 0 3px rgba(66,173,138,0.1);
        }


        button {
            width: 100%;
            background: #42ad8a;
            color: white;
            border: none;
            padding: 14px;
            font-size: 17px;
            font-weight: 700;
            border-radius: 14px;
            cursor: pointer;
            transition: all 0.25s ease;
            margin-top: 8px;
            box-shadow: 0 4px 12px rgba(47,158,143,0.25);
        }

        button:hover {
            background: #369f7d;
            transform: translateY(-2px);
            box-shadow: 0 8px 18px rgba(47,158,143,0.35);
        }

        button:active {
            transform: scale(0.97);
        }

        .error-box {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fca5a5;
            padding: 12px;
            border-radius: 12px;
            margin-bottom: 20px;
            font-size: 14px;
            text-align: left;
        }

        .error-text {
            color: #dc2626;
            font-size: 13px;
            margin-top: 4px;
        }

        .back-link {
            display: block;
            text-align: left;
            margin-bottom: 24px;
            font-size: 14px;
            color: #42ad8a;
            text-decoration: none;
            font-weight: 500;
        }

        .back-link:hover {
            text-decoration: underline;
        }

        .login-footer {
            margin-top: 28px;
            font-size: 13px;
            color: #64748b;
        }
    </style>
</head>
<body>
    @yield('content')
    <script>
    document.addEventListener('keydown', function (e) {
        if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
            e.preventDefault();
            window.location.href = "{{ route('public.home') }}";
        }
    });
</script>
</body>
</html>