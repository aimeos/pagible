<!DOCTYPE html>
<html lang="{{ str_replace( '_', '-', app()->getLocale() ) }}">
<head>
    <meta charset="utf-8">
    <meta name="robots" content="noindex">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config( 'app.name' ) }}</title>
    @paddleJS
    <style>
        html { color-scheme: light dark; }
        body { font-family: sans-serif; margin: 0; }
        main { margin: 5vh auto; max-width: 48rem; padding: 1rem; }
        a { display: inline-block; font-size: 1.75rem; margin-block-end: 1rem; text-decoration: none; }
    </style>
</head>
<body>
    <main>
        <a href="{{ $cancelUrl }}" aria-label="{{ __( 'Close' ) }}">&#8592;</a>
        <div class="paddle-checkout-container"></div>
        <script type="text/javascript">
            Paddle.Checkout.open(@json($options));
        </script>
    </main>
</body>
</html>
