<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ config('app.name', 'Laravel') }}</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body>
        <script id="app-session" type="application/json">{!! \Illuminate\Support\Js::encode([
            'user' => auth()->user()?->only(['id', 'name', 'email', 'date_format']),
            'csrfToken' => csrf_token(),
            'dateFormats' => config('date-formats'),
            'tagIcons' => config('tag-icons'),
            'urls' => [
                'updateSettings' => route('settings.update', absolute: false),
                'login' => route('login', absolute: false),
                'logout' => route('logout', absolute: false),
                'dashboard' => route('dashboard', absolute: false),
                'expenses' => route('expenses.index', absolute: false),
                'payers' => route('payers.index', absolute: false),
                'tags' => route('tags.index', absolute: false),
                'downloadReport' => route('reports.download', absolute: false),
                'createTag' => route('tags.store', absolute: false),
                'updateTag' => route('tags.update', ['tag' => '__ID__'], absolute: false),
                'deleteTag' => route('tags.destroy', ['tag' => '__ID__'], absolute: false),
                'createExpense' => route('expenses.store', absolute: false),
                'incomes' => route('incomes.index', absolute: false),
                'createIncome' => route('incomes.store', absolute: false),
                'deleteExpense' => route('expenses.destroy', ['expense' => '__ID__'], absolute: false),
                'updateExpense' => route('expenses.update', ['expense' => '__ID__'], absolute: false),
                'deleteIncome' => route('incomes.destroy', ['income' => '__ID__'], absolute: false),
                'updateIncome' => route('incomes.update', ['income' => '__ID__'], absolute: false),
            ],
        ]) !!}</script>
        <div id="app"></div>
    </body>
</html>
