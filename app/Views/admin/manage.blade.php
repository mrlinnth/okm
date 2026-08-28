<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title }} · Outline Key Manager</title>
    <link href="/css/output.css" rel="stylesheet">
</head>
<body class="min-h-screen bg-base-200">
    <main class="mx-auto flex min-h-screen max-w-md items-center px-4 py-10">
        <section class="card w-full border border-base-300 bg-base-100 shadow-xl">
            <div class="card-body">
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-primary">Outline Key Manager</p>
                <h1 class="mt-1 text-2xl font-semibold tracking-tight">Manage Subscriptions</h1>
                <p class="mt-2 text-sm text-base-content/60">Enter the shared admin password to manage subscriptions and saved servers.</p>

                <form method="post" action="/manage" class="mt-6 flex flex-col gap-4">
                    {!! csrf_field() !!}
                    <div class="flex flex-col gap-1.5">
                        <label for="password" class="text-sm font-medium">Password</label>
                        <input id="password" name="password" type="password" autocomplete="current-password" required autofocus class="input input-bordered w-full" />
                    </div>
                    @if ($error)
                        <p class="text-sm text-error">{{ $error }}</p>
                    @endif
                    <button type="submit" class="btn btn-neutral w-full">Continue</button>
                </form>
            </div>
        </section>
    </main>
</body>
</html>
