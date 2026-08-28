@extends('layouts.master')

@section('title', $title)

@section('content')
<h1 class="text-2xl font-semibold tracking-tight">Saved Servers</h1>
<p class="mt-1 text-sm text-base-content/60">
    Registry of stored Outline servers. The full grid, Add Server modal, and
    activate/delete actions are built in Phase 3.
</p>

{{-- Placeholder: dumps the server list the controller loaded from Cockpit. --}}
<pre class="mt-4 rounded-md border border-base-300 bg-base-200 p-3 text-xs overflow-x-auto">{{ json_encode($servers, JSON_PRETTY_PRINT) }}</pre>
@endsection
