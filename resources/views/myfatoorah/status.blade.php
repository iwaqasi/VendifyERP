<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Status - Vendify</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background-color: #0f172a;
            color: #f8fafc;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
        }
        .card {
            background-color: #1e293b;
            border: 1px solid #334155;
            border-radius: 16px;
            padding: 32px;
            max-width: 420px;
            width: 100%;
            text-align: center;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.5);
        }
        .icon {
            font-size: 54px;
            margin-bottom: 16px;
        }
        .status {
            font-size: 22px;
            font-weight: 700;
            margin-bottom: 8px;
        }
        .success { color: #10b981; }
        .failed { color: #ef4444; }
        .details {
            background-color: #0f172a;
            border-radius: 8px;
            padding: 16px;
            margin: 20px 0;
            text-align: left;
            font-size: 14px;
            color: #94a3b8;
        }
        .details div {
            display: flex;
            justify-content: space-between;
            padding: 6px 0;
            border-bottom: 1px solid #1e293b;
        }
        .details div:last-child { border-bottom: none; }
        .details strong { color: #f8fafc; }
        .btn {
            display: inline-block;
            background-color: #06b6d4;
            color: #042f2e;
            font-weight: 600;
            text-decoration: none;
            padding: 12px 24px;
            border-radius: 8px;
            transition: opacity 0.2s;
        }
        .btn:hover { opacity: 0.9; }
    </style>
</head>
<body>
    <div class="card">
        @if($invoiceStatus === 'Paid')
            <div class="icon">✅</div>
            <div class="status success">Payment Successful</div>
            <p style="color: #94a3b8; margin: 0;">Your transaction has been approved and registered.</p>
        @else
            <div class="icon">❌</div>
            <div class="status failed">Payment {{ $invoiceStatus ?: 'Incomplete' }}</div>
            <p style="color: #94a3b8; margin: 0;">We were unable to complete this transaction.</p>
        @endif

        <div class="details">
            <div>
                <span>Transaction Ref:</span>
                <strong>#{{ $transactionId }}</strong>
            </div>
            <div>
                <span>Payment ID:</span>
                <strong>{{ $paymentId }}</strong>
            </div>
            <div>
                <span>Status:</span>
                <strong class="{{ $invoiceStatus === 'Paid' ? 'success' : 'failed' }}">{{ $invoiceStatus }}</strong>
            </div>
        </div>

        <a href="/vendify-pos/" class="btn">Return to POS</a>
    </div>
</body>
</html>

