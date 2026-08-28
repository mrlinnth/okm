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

    {{-- Add server modal --}}
    <div x-show="showAdd" x-cloak class="modal modal-open">
        <div class="modal-box max-w-sm">
            <h3 class="font-semibold">Add saved server</h3>

            <div class="mt-3 space-y-3">
                <div>
                    <label class="label label-text text-xs">Label</label>
                    <input type="text" x-model="form.label" placeholder="e.g. Contabo SG" class="input input-bordered w-full" @keydown.enter="submitAdd()">
                </div>
                <div>
                    <label class="label label-text text-xs">Public host <span class="text-base-content/40">(optional)</span></label>
                    <input type="text" x-model="form.publicHost" placeholder="e.g. vpn1.example.com" class="input input-bordered w-full">
                </div>
                <div>
                    <label class="label label-text text-xs">Server JSON</label>
                    <textarea x-model="form.json" rows="3" placeholder='{"apiUrl": "https://...", "certSha256": "..."}' class="textarea textarea-bordered w-full font-mono text-sm"></textarea>
                </div>
            </div>

            <p x-show="addError" x-text="addError" class="mt-2 text-xs text-error"></p>

            <div class="modal-action">
                <button @click="showAdd = false" class="btn btn-ghost flex-1">Cancel</button>
                <button @click="submitAdd()" :disabled="adding" class="btn btn-neutral flex-1" x-text="adding ? 'Saving…' : 'Save'"></button>
            </div>
        </div>
        <div class="modal-backdrop" @click="showAdd = false"></div>
    </div>

    {{-- Delete confirm --}}
    <div x-show="deleteTarget" x-cloak class="modal modal-open">
        <div class="modal-box max-w-sm">
            <h3 class="font-semibold">Delete saved server?</h3>
            <p class="mt-1 text-sm text-base-content/60" x-text="deleteTarget ? 'This removes ' + deleteTarget.label + ' from the registry.' : ''"></p>
            <p x-show="deleteError" x-text="deleteError" class="mt-2 text-xs text-error"></p>
            <div class="modal-action">
                <button @click="deleteTarget = null" class="btn btn-ghost flex-1">Cancel</button>
                <button @click="confirmDelete()" :disabled="deleting" class="btn btn-error flex-1" x-text="deleting ? 'Deleting…' : 'Delete'"></button>
            </div>
        </div>
        <div class="modal-backdrop" @click="deleteTarget = null"></div>
    </div>

</div>

<script>
    function savedServers() {
        return {
            servers: {!! json_encode($servers, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) !!},

            showAdd: false,
            form: { label: '', publicHost: '', json: '' },
            addError: '',
            adding: false,

            deleteTarget: null,
            deleteError: '',
            deleting: false,

            csrfHeaders() {
                const token = document.querySelector('meta[name="X-CSRF-TOKEN"]');
                return token ? { 'X-CSRF-TOKEN': token.content } : {};
            },

            displayHost(srv) {
                if (srv.publicHost) return srv.publicHost;
                try {
                    return new URL(srv.apiUrl).host;
                } catch (e) {
                    return srv.apiUrl;
                }
            },

            // Loose validation, matching Classic key manager's Connect check.
            parseApiUrl(jsonText) {
                let parsed;
                try {
                    parsed = JSON.parse(jsonText);
                } catch (e) {
                    return null;
                }
                if (!parsed || typeof parsed.apiUrl !== 'string' || !parsed.apiUrl.startsWith('https://')) {
                    return null;
                }
                return parsed.apiUrl;
            },

            openAdd() {
                this.form = { label: '', publicHost: '', json: '' };
                this.addError = '';
                this.showAdd = true;
            },

            async submitAdd() {
                if (!this.form.label.trim()) {
                    this.addError = 'Label is required.';
                    return;
                }
                if (!this.parseApiUrl(this.form.json)) {
                    this.addError = 'Invalid server JSON — must include an https apiUrl.';
                    return;
                }

                this.addError = '';
                this.adding = true;
                try {
                    const response = await fetch('/servers', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', ...this.csrfHeaders() },
                        body: JSON.stringify({
                            label: this.form.label.trim(),
                            serverJson: this.form.json,
                            publicHost: this.form.publicHost.trim() || null,
                        }),
                    });
                    const data = await response.json();

                    if (response.status === 401) {
                        window.location.assign(data.login || '/manage');
                        return;
                    }

                    if (!response.ok) {
                        // 422 carries the specific reason — invalid JSON vs unreachable.
                        this.addError = data.error || 'Failed to add server.';
                        return;
                    }

                    this.servers.push(data);
                    this.showAdd = false;
                } catch (e) {
                    this.addError = 'Failed to add server.';
                } finally {
                    this.adding = false;
                }
            },

            async toggleActive(srv) {
                const action = srv.active ? 'deactivate' : 'activate';
                srv.busy = true;
                try {
                    const response = await fetch(`/servers/${srv.id}/${action}`, { method: 'POST', headers: this.csrfHeaders() });
                    if (response.status === 401) {
                        const data = await response.json();
                        window.location.assign(data.login || '/manage');
                        return;
                    }
                    if (response.ok) {
                        const updated = await response.json();
                        srv.active = updated.active;
                    }
                } finally {
                    srv.busy = false;
                }
            },

            askDelete(srv) {
                this.deleteError = '';
                this.deleteTarget = srv;
            },

            async confirmDelete() {
                const target = this.deleteTarget;
                this.deleting = true;
                try {
                    const response = await fetch(`/servers/${target.id}/delete`, { method: 'POST', headers: this.csrfHeaders() });
                    const data = await response.json();
                    if (response.status === 401) {
                        window.location.assign(data.login || '/manage');
                        return;
                    }
                    if (response.ok && data.success) {
                        this.servers = this.servers.filter(s => s.id !== target.id);
                        this.deleteTarget = null;
                    } else {
                        this.deleteError = 'Failed to delete server.';
                    }
                } catch (e) {
                    this.deleteError = 'Failed to delete server.';
                } finally {
                    this.deleting = false;
                }
            },
        };
    }
</script>
@endsection
