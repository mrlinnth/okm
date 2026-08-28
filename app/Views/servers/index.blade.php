@extends('layouts.master')

@section('title', $title)

@section('content')
<div x-data="savedServers()" x-cloak>

    <div class="flex items-start justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight">Saved Servers</h1>
            <p class="mt-1 text-sm text-base-content/60">
                Outline server credentials stored in Cockpit — no more pasting JSON every visit.
            </p>
        </div>
        <button @click="openAdd()" class="btn btn-neutral btn-sm shrink-0 gap-1.5">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
            </svg>
            <span class="hidden sm:inline">Add server</span>
        </button>
    </div>

    <div class="mt-5 grid grid-cols-1 sm:grid-cols-2 gap-4">
        <template x-for="srv in servers" :key="srv.id">
            <div class="card bg-base-100 border border-base-300">
                <div class="card-body gap-0">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="font-medium truncate" x-text="srv.label"></p>
                            <p class="mt-0.5 text-xs text-base-content/50 truncate" x-text="displayHost(srv)"></p>
                        </div>
                        <span
                            class="badge badge-sm shrink-0"
                            :class="srv.active ? 'badge-success' : 'badge-ghost'"
                            x-text="srv.active ? 'active' : 'inactive'"
                        ></span>
                    </div>

                    <div class="mt-4 flex flex-wrap gap-2">
                        <button
                            @click="toggleActive(srv)"
                            :disabled="srv.busy"
                            class="btn btn-outline btn-xs"
                            x-text="srv.active ? 'Deactivate' : 'Activate'"
                        ></button>
                        <button @click="askDelete(srv)" class="btn btn-outline btn-error btn-xs">Delete</button>
                    </div>
                </div>
            </div>
        </template>

        <p x-show="servers.length === 0" class="text-sm text-base-content/40 py-6">
            No saved servers yet. Add one to get started.
        </p>
    </div>

</div>

<script>
    function savedServers() {
        return {
            servers: {!! json_encode($servers, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) !!},

            displayHost(srv) {
                if (srv.publicHost) return srv.publicHost;
                try {
                    return new URL(srv.apiUrl).host;
                } catch (e) {
                    return srv.apiUrl;
                }
            },

            openAdd() {
                // Add server modal is wired in task 3.2.
            },

            async toggleActive(srv) {
                // Immediate toggle wired in task 3.3.
            },

            askDelete(srv) {
                // Delete confirm modal wired in task 3.3.
            },
        };
    }
</script>
@endsection
