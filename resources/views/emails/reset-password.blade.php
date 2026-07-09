<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - Carikan Amikom</title>
    <style>
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background-color: #FAFAFC;
            margin: 0;
            padding: 0;
            -webkit-font-smoothing: antialiased;
        }
        .container {
            max-width: 600px;
            margin: 40px auto;
            background-color: #ffffff;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05);
            border: 1px solid #f1edf7;
        }
        .header {
            background: linear-gradient(135deg, #431357, #623376);
            padding: 40px 20px;
            text-align: center;
            color: #ffffff;
        }
        .header h1 {
            margin: 0;
            font-size: 28px;
            font-weight: 800;
            letter-spacing: -0.5px;
            font-family: 'Outfit', sans-serif;
        }
        .header p {
            margin: 8px 0 0;
            font-size: 13px;
            color: #eaddfb;
            font-weight: 500;
        }
        .content {
            padding: 40px 30px;
            color: #2c2738;
            line-height: 1.6;
        }
        .content h2 {
            margin-top: 0;
            font-size: 20px;
            font-weight: 700;
            color: #1f182d;
        }
        .content p {
            font-size: 14px;
            color: #5a5266;
            margin-bottom: 24px;
        }
        .btn-container {
            text-align: center;
            margin: 35px 0;
        }
        .btn {
            display: inline-block;
            background-color: #873da6;
            color: #ffffff !important;
            text-decoration: none;
            padding: 14px 35px;
            font-size: 14px;
            font-weight: 700;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(135, 61, 166, 0.3);
            transition: transform 0.2s ease;
        }
        .btn:hover {
            background-color: #661a86;
        }
        .warning-box {
            background-color: #fef7ff;
            border: 1px solid #eaddfb;
            border-radius: 12px;
            padding: 16px;
            margin-top: 30px;
        }
        .warning-box p {
            margin: 0;
            font-size: 12px;
            color: #6c228c;
            line-height: 1.5;
        }
        .footer {
            background-color: #f9f8fa;
            padding: 30px;
            text-align: center;
            border-top: 1px solid #f1edf7;
        }
        .footer p {
            margin: 0 0 8px;
            font-size: 11px;
            color: #a39cae;
            line-height: 1.4;
        }
        .footer a {
            color: #873da6;
            text-decoration: none;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Brand Header -->
        <div class="header">
            <h1>Cari<span>kan.</span></h1>
            <p>Platform Barang Hilang & Temuan Universitas Amikom Yogyakarta</p>
        </div>
        
        <!-- Mail Content -->
        <div class="content">
            <h2>Halo Civitas Akademika,</h2>
            <p>Kami menerima permintaan untuk mereset kata sandi akun Carikan Anda. Klik tombol di bawah ini untuk mengatur ulang kata sandi Anda. Tautan ini hanya berlaku selama <b>60 menit</b>.</p>
            
            <!-- Button CTA -->
            <div class="btn-container">
                <a href="{{ $resetUrl }}" class="btn" target="_blank">Atur Ulang Kata Sandi</a>
            </div>
            
            <p>Jika tombol di atas tidak berfungsi, Anda dapat menyalin dan menempelkan tautan berikut ke browser Anda:</p>
            <p style="word-break: break-all; font-size: 12px; color: #873da6; background-color: #f6f5f8; padding: 10px; border-radius: 8px;">
                {{ $resetUrl }}
            </p>

            <div class="warning-box">
                <p><b>Penting:</b> Jika Anda tidak merasa mengajukan permintaan ini, silakan abaikan email ini secara aman. Kata sandi Anda tidak akan berubah sampai Anda membuat yang baru.</p>
            </div>
        </div>
        
        <!-- Branded Footer -->
        <div class="footer">
            <p>&copy; 2026 Universitas Amikom Yogyakarta. All rights reserved.</p>
            <p>Jl. Ring Road Utara, Condongcatur, Depok, Sleman, Yogyakarta</p>
            <p><a href="https://amikom.ac.id">Website Amikom</a> | <a href="http://localhost:5173">Buka Carikan</a></p>
        </div>
    </div>
</body>
</html>
