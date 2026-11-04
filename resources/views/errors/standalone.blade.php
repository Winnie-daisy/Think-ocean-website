{{-- Self-contained error page: no database, settings or compiled assets, so it renders even when those are unavailable. --}}
<!DOCTYPE html>
<html lang="en-GB">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ $title }} | Think Ocean CIC</title>
    <style>
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; font-family: system-ui, -apple-system, 'Segoe UI', sans-serif;
               color: #fff; background: linear-gradient(180deg, #0b3d5b 0%, #082f47 55%, #06263a 100%); }
        main { max-width: 36rem; padding: 2rem 1.5rem; }
        p.code { letter-spacing: .18em; text-transform: uppercase; font-size: .75rem; color: #ddf4f5; }
        h1 { font-family: Poppins, system-ui, sans-serif; font-weight: 600; font-size: clamp(2rem, 6vw, 3.25rem); line-height: 1.1; margin: .5rem 0 1rem; }
        p { line-height: 1.6; color: rgb(255 255 255 / .85); }
        a { display: inline-block; margin-top: 1.5rem; padding: .85rem 1.5rem; border-radius: 999px; background: #fff; color: #0b3d5b; font-weight: 600; text-decoration: none; }
        a:focus-visible { outline: 3px solid #00a6a6; outline-offset: 3px; }
    </style>
</head>
<body>
<main>
    <p class="code">{{ $code }}</p>
    <h1>{{ $title }}</h1>
    <p>{{ $message }}</p>
    <a href="/">Go to the homepage</a>
</main>
</body>
</html>
