@extends('layouts.master')

@section('title', $title)

@section('content')
<div x-data="savedServers()" x-cloak x-init="init()">

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
                        <button @click="openSync(srv)" class="btn btn-outline btn-xs gap-1.5">
                            Sync now
                            <span
                                x-show="unresolved[srv.id] > 0"
                                class="inline-block h-2 w-2 rounded-full bg-warning"
                                title="Unresolved differences between this server and the ledger"
                            ></span>
                        </button>
                        <button @click="openMigrate(srv)" :disabled="!srv.active" class="btn btn-outline btn-xs">Migrate</button>
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
    <div x-cloak class="modal" :class="{ 'modal-open': showAdd }">
        <div class="modal-box max-w-sm">
            <template x-if="!importSummary">
                <div>
                    <h3 class="font-semibold">Add saved server</h3>

                    <div class="mt-3 space-y-3">
                        <fieldset class="fieldset">
                            <legend class="fieldset-legend">Label</legend>
                            <input type="text" x-model="form.label" placeholder="e.g. Contabo SG" class="input w-full" @keydown.enter="submitAdd()">
                        </fieldset>
                        <fieldset class="fieldset">
                            <legend class="fieldset-legend">Public host <span class="font-normal text-base-content/40">(optional)</span></legend>
                            <input type="text" x-model="form.publicHost" placeholder="e.g. vpn1.example.com" class="input w-full">
                        </fieldset>
                        <fieldset class="fieldset">
                            <legend class="fieldset-legend">Server JSON</legend>
                            <textarea x-model="form.json" rows="3" placeholder='{"apiUrl": "https://...", "certSha256": "..."}' class="textarea w-full font-mono text-sm"></textarea>
                        </fieldset>
                    </div>

                    <p x-show="addError" x-text="addError" class="mt-2 text-xs text-error"></p>

                    <div class="modal-action">
                        <button @click="closeAdd()" class="btn btn-ghost flex-1">Cancel</button>
                        <button @click="submitAdd()" :disabled="adding" class="btn btn-neutral flex-1" x-text="adding ? 'Saving…' : 'Save'"></button>
                    </div>
                </div>
            </template>

            <template x-if="importSummary">
                <div>
                    <h3 class="font-semibold">Server added</h3>
                    <p class="mt-2 text-sm">
                        Imported <span class="font-semibold" x-text="importSummary.imported"></span>
                        existing <span x-text="importSummary.imported === 1 ? 'key' : 'keys'"></span> as subscriptions.
                    </p>
                    <template x-if="importSummary.failed > 0">
                        <div class="mt-2 text-xs text-error">
                            <p x-text="importSummary.failed + ' key(s) could not be imported:'"></p>
                            <ul class="mt-1 list-disc pl-4">
                                <template x-for="failure in importSummary.failures" :key="failure.name">
                                    <li><span x-text="failure.name || '(unnamed)'"></span> — <span x-text="failure.error"></span></li>
                                </template>
                            </ul>
                        </div>
                    </template>
                    <div class="modal-action">
                        <button @click="closeAdd()" class="btn btn-neutral flex-1">Done</button>
                    </div>
                </div>
            </template>
        </div>
        <div class="modal-backdrop" @click="closeAdd()"></div>
    </div>

    {{-- Sync now modal --}}
    <div x-cloak class="modal" :class="{ 'modal-open': syncTarget }">
        <div class="modal-box max-w-lg">
            <h3 class="font-semibold">
                Sync now <span class="text-base-content/50" x-text="syncTarget ? '· ' + syncTarget.label : ''"></span>
            </h3>

            <p x-show="syncBusy" class="mt-4 text-sm text-base-content/60">
                Importing keys created outside the app and dropping records for keys that no longer exist…
            </p>
            <p x-show="syncError" x-text="syncError" class="mt-4 text-sm text-error"></p>

            <template x-if="syncSummary && !syncBusy">
                <div class="mt-4 space-y-4 text-sm">
                    <p x-show="syncSummary.imported.length === 0 && syncSummary.removed.length === 0 && syncSummary.failed === 0" class="text-success">
                        Already in sync — nothing to do.
                    </p>

                    <div x-show="syncSummary.imported.length > 0">
                        <p class="font-medium" x-text="'Imported ' + syncSummary.imported.length + ' new ' + (syncSummary.imported.length === 1 ? 'key' : 'keys')"></p>
                        <ul class="mt-2 space-y-1">
                            <template x-for="name in syncSummary.imported" :key="name">
                                <li class="truncate rounded border border-success/40 px-2 py-1 font-mono text-xs text-success" x-text="name || '(unnamed)'"></li>
                            </template>
                        </ul>
                    </div>

                    <div x-show="syncSummary.removed.length > 0">
                        <p class="font-medium" x-text="'Removed ' + syncSummary.removed.length + ' stale ' + (syncSummary.removed.length === 1 ? 'record' : 'records')"></p>
                        <ul class="mt-2 space-y-1">
                            <template x-for="name in syncSummary.removed" :key="name">
                                <li class="truncate rounded border border-base-300 px-2 py-1 text-xs" x-text="name"></li>
                            </template>
                        </ul>
                    </div>

                    <p x-show="syncSummary.failed > 0" class="text-error"
                       x-text="syncSummary.failed + ' operation(s) failed — see the server log. Try Sync now again.'"></p>
                </div>
            </template>

            <div class="modal-action">
                <button @click="openSync(syncTarget)" :disabled="syncBusy" class="btn btn-ghost" x-show="syncSummary && !syncBusy">Run again</button>
                <button @click="closeSync()" class="btn btn-neutral">Close</button>
            </div>
        </div>
        <div class="modal-backdrop" @click="closeSync()"></div>
    </div>

    {{-- Migrate modal --}}
    <div x-cloak class="modal" :class="{ 'modal-open': migrateTarget }">
        <div class="modal-box max-w-lg">
            <h3 class="font-semibold">
                Migrate <span class="text-base-content/50" x-text="migrateTarget ? '· ' + migrateTarget.label : ''"></span>
            </h3>
            <p class="mt-1 text-sm text-base-content/60">
                Moves every subscription on this server — active and inactive — to another active server.
            </p>

            <template x-if="!migrateResults">
                <div>
                    <fieldset class="fieldset mt-3">
                        <legend class="fieldset-legend">Destination server</legend>
                        <select x-model="migrateDest" class="select w-full">
                            <option value="">Select a destination…</option>
                            <template x-for="opt in migrateDestinations()" :key="opt.id">
                                <option :value="opt.id" x-text="opt.label"></option>
                            </template>
                        </select>
                    </fieldset>
                    <p x-show="migrateError" x-text="migrateError" class="mt-2 text-xs text-error"></p>
                    <div class="modal-action">
                        <button @click="closeMigrate()" class="btn btn-ghost flex-1">Cancel</button>
                        <button @click="submitMigrate()" :disabled="migrating || !migrateDest" class="btn btn-neutral flex-1"
                            x-text="migrating ? 'Migrating…' : 'Migrate'"></button>
                    </div>
                </div>
            </template>

            <template x-if="migrateResults">
                <div>
                    <p class="mt-3 text-sm">
                        Moved <span class="font-semibold" x-text="migrateResults.moved"></span>,
                        failed <span class="font-semibold" x-text="migrateResults.failed"></span>.
                    </p>
                    <ul class="mt-2 max-h-64 space-y-1 overflow-y-auto text-sm">
                        <template x-for="row in migrateResults.results" :key="row.id">
                            <li class="rounded border border-base-300 px-2 py-1"
                                :class="row.status === 'failed' ? 'border-error' : ''">
                                <div class="flex items-center justify-between gap-2">
                                    <span class="truncate" x-text="row.recipientName || row.id"></span>
                                    <span class="text-xs" :class="row.status === 'failed' ? 'text-error' : 'text-success'" x-text="row.status"></span>
                                </div>
                                <p x-show="row.renamed_from" class="text-xs text-base-content/50">renamed from <span x-text="row.renamed_from"></span></p>
                                <p x-show="row.warning" class="text-xs text-warning" x-text="row.warning"></p>
                                <p x-show="row.error" class="text-xs text-error" x-text="row.error"></p>
                            </li>
                        </template>
                    </ul>
                    <div class="modal-action">
                        <button @click="closeMigrate()" class="btn btn-neutral flex-1">Done</button>
                    </div>
                </div>
            </template>
        </div>
        <div class="modal-backdrop" @click="closeMigrate()"></div>
    </div>

    {{-- Delete confirm --}}
    <div x-cloak class="modal" :class="{ 'modal-open': deleteTarget }">
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
            importSummary: null,

            unresolved: {},

            syncTarget: null,
            syncSummary: null,
            syncBusy: false,
            syncError: '',

            migrateTarget: null,
            migrateDest: '',
            migrateError: '',
            migrating: false,
            migrateResults: null,

            deleteTarget: null,
            deleteError: '',
            deleting: false,

            init() {
                this.servers.filter(s => s.active).forEach(s => this.refreshDiff(s.id));
            },

            csrfHeaders() {
                const token = document.querySelector('meta[name="X-CSRF-TOKEN"]');
                return token ? { 'X-CSRF-TOKEN': token.content } : {};
            },

            async postJson(url, body) {
                const response = await fetch(url, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', ...this.csrfHeaders() },
                    body: JSON.stringify(body || {}),
                });
                if (response.status === 401) {
                    const data = await response.json().catch(() => ({}));
                    window.location.assign(data.login || '/manage');
                    return null;
                }
                return response;
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

            // --- Add server ---------------------------------------------

            openAdd() {
                this.form = { label: '', publicHost: '', json: '' };
                this.addError = '';
                this.importSummary = null;
                this.showAdd = true;
            },

            closeAdd() {
                this.showAdd = false;
                this.importSummary = null;
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
                    const response = await this.postJson('/servers', {
                        label: this.form.label.trim(),
                        serverJson: this.form.json,
                        publicHost: this.form.publicHost.trim() || null,
                    });
                    if (!response) return;
                    const data = await response.json();

                    if (!response.ok) {
                        // 422 carries the specific reason — invalid JSON vs unreachable.
                        this.addError = data.error || 'Failed to add server.';
                        return;
                    }

                    this.servers.push(data);
                    this.importSummary = data.import || { imported: 0, failed: 0, failures: [] };
                    if (data.active) this.refreshDiff(data.id);
                } catch (e) {
                    this.addError = 'Failed to add server.';
                } finally {
                    this.adding = false;
                }
            },

            // --- Reconciliation diff / amber dot -----------------------

            async refreshDiff(serverId) {
                try {
                    const response = await this.postJson(`/servers/${serverId}/sync`);
                    if (!response || !response.ok) return;
                    const diff = await response.json();
                    this.unresolved[serverId] = (diff.foundOnServer?.length || 0) + (diff.missingOnServer?.length || 0);
                } catch (e) {
                    // Best-effort — leave the dot off if the check fails.
                }
            },

            // --- Sync now ---------------------------------------------

            // One step: import every Outline key the ledger is missing and
            // drop every ledger record whose key is gone. Same operation the
            // servers:sync cron runs.
            async openSync(srv) {
                this.syncTarget = srv;
                this.syncSummary = null;
                this.syncError = '';
                this.syncBusy = true;
                try {
                    const response = await this.postJson(`/servers/${srv.id}/reconcile`);
                    if (!response) return;
                    const data = await response.json();
                    if (!response.ok) {
                        this.syncError = data.error || 'Could not reach this server.';
                        return;
                    }
                    this.syncSummary = data;
                    this.unresolved[srv.id] = data.failed;
                } catch (e) {
                    this.syncError = 'Could not reach this server.';
                } finally {
                    this.syncBusy = false;
                }
            },

            closeSync() {
                if (this.syncTarget) this.refreshDiff(this.syncTarget.id);
                this.syncTarget = null;
            },

            // --- Migrate --------------------------------------------

            migrateDestinations() {
                return this.servers.filter(s => s.active && s.id !== (this.migrateTarget && this.migrateTarget.id));
            },

            openMigrate(srv) {
                this.migrateTarget = srv;
                this.migrateDest = '';
                this.migrateError = '';
                this.migrateResults = null;
            },

            closeMigrate() {
                const source = this.migrateTarget;
                const dest = this.migrateDest;
                this.migrateTarget = null;
                if (this.migrateResults && source) {
                    this.refreshDiff(source.id);
                    if (dest) this.refreshDiff(dest);
                }
            },

            async submitMigrate() {
                if (!this.migrateDest) return;
                this.migrateError = '';
                this.migrating = true;
                try {
                    const response = await this.postJson(`/servers/${this.migrateTarget.id}/migrate`, {
                        destinationServerId: this.migrateDest,
                    });
                    if (!response) return;
                    const data = await response.json();
                    if (!response.ok) {
                        this.migrateError = data.error || 'Migration failed.';
                        return;
                    }
                    this.migrateResults = data;
                } catch (e) {
                    this.migrateError = 'Migration failed.';
                } finally {
                    this.migrating = false;
                }
            },

            // --- Activate / deactivate / delete --------------------

            async toggleActive(srv) {
                const action = srv.active ? 'deactivate' : 'activate';
                srv.busy = true;
                try {
                    const response = await this.postJson(`/servers/${srv.id}/${action}`);
                    if (!response || !response.ok) return;
                    const updated = await response.json();
                    srv.active = updated.active;
                    if (srv.active) this.refreshDiff(srv.id);
                    else delete this.unresolved[srv.id];
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
                    const response = await this.postJson(`/servers/${target.id}/delete`);
                    if (!response) return;
                    const data = await response.json();
                    if (response.ok && data.success) {
                        this.servers = this.servers.filter(s => s.id !== target.id);
                        this.deleteTarget = null;
                    } else {
                        this.deleteError = data.error || 'Failed to delete server.';
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
