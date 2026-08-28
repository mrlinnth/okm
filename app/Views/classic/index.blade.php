@extends('layouts.master')

@section('title', $title)

@section('content')
<div
    x-data="classicManager()"
    x-cloak
>
    <h1 class="text-2xl font-semibold tracking-tight">Classic Manager</h1>
    <p class="mt-1 text-sm text-base-content/60">Quick-connect workspace — paste server JSON to list, create, delete, or migrate keys directly.</p>

    <div class="mt-5 grid grid-cols-1 lg:grid-cols-[1fr_auto_1fr] gap-4 items-start">

        {{-- Current server panel --}}
        <div class="card bg-base-100 border border-base-300">
            <div class="card-body">
                <h2 class="card-title text-sm">Current server</h2>

                <template x-if="!current.connected">
                    <div class="mt-2">
                        <textarea x-model="current.json" rows="4" placeholder='Paste server JSON, e.g. {"apiUrl": "https://...", "certSha256": "..."}' class="textarea textarea-bordered w-full font-mono text-sm"></textarea>
                        <p x-show="current.error" x-text="current.error" class="mt-1 text-xs text-error"></p>
                        <button @click="connectCurrent()" :disabled="current.loading" class="btn btn-neutral btn-block mt-3" x-text="current.loading ? 'Connecting…' : 'Connect'"></button>
                    </div>
                </template>

                <template x-if="current.connected">
                    <div class="mt-2">
                        <div class="flex items-center justify-between">
                            <p class="text-xs text-base-content/50" x-text="current.label"></p>
                            <button @click="startOverCurrent()" class="link link-hover text-xs text-base-content/50">Start over</button>
                        </div>

                        <div class="mt-3 space-y-2 max-h-[28rem] overflow-y-auto pr-1">
                            <template x-for="key in current.keys" :key="key.id">
                                <div class="flex items-center justify-between rounded-md border border-base-300 px-3 py-2">
                                    <div class="min-w-0">
                                        <p class="text-sm font-medium truncate" x-text="key.name"></p>
                                        <p class="text-xs text-base-content/50" x-text="key.usage"></p>
                                    </div>
                                    <div class="flex items-center gap-2 shrink-0">
                                        <button @click="copy(key)" class="btn btn-ghost btn-xs" x-text="copiedId === key.id ? 'Copied!' : 'Copy'"></button>
                                        <button @click="deleteTarget = key" class="btn btn-ghost btn-xs text-error">Delete</button>
                                    </div>
                                </div>
                            </template>
                            <p x-show="current.keys.length === 0" class="text-sm text-base-content/40 text-center py-3">No keys on this server.</p>
                        </div>

                        <div class="mt-4 flex gap-2">
                            <button @click="showCreateKey = true" class="btn btn-outline btn-sm flex-1">Create key</button>
                            <button @click="showDeleteAll = true" :disabled="current.keys.length === 0" class="btn btn-outline btn-error btn-sm flex-1">Delete all</button>
                        </div>
                    </div>
                </template>
            </div>
        </div>

        {{-- Connector --}}
        <div class="hidden lg:flex items-center justify-center h-full pt-16">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-base-content/30" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 8l4 4m0 0l-4 4m4-4H3" /></svg>
        </div>

        {{-- Migrate-to panel --}}
        <div class="card bg-base-100 border border-base-300">
            <div class="card-body">
                <h2 class="card-title text-sm">Migrate to</h2>

                <template x-if="!current.connected">
                    <p class="mt-2 text-sm text-base-content/40">Connect a current server first.</p>
                </template>

                <template x-if="current.connected && !migrateTo.connected">
                    <div class="mt-2">
                        <textarea x-model="migrateTo.json" rows="4" placeholder="Paste destination server JSON" class="textarea textarea-bordered w-full font-mono text-sm"></textarea>
                        <p x-show="migrateTo.error" x-text="migrateTo.error" class="mt-1 text-xs text-error"></p>
                        <button @click="connectMigrateTo()" :disabled="migrateTo.loading" class="btn btn-neutral btn-block mt-3" x-text="migrateTo.loading ? 'Connecting…' : 'Connect'"></button>
                    </div>
                </template>

                <template x-if="current.connected && migrateTo.connected">
                    <div class="mt-2">
                        <div class="flex items-center justify-between">
                            <p class="text-xs text-base-content/50" x-text="migrateTo.label"></p>
                            <button @click="startOverMigrate()" class="link link-hover text-xs text-base-content/50">Start over</button>
                        </div>

                        <button
                            x-show="!migrateResults"
                            @click="startMigration()"
                            :disabled="migrating"
                            class="btn btn-neutral btn-block mt-3"
                            x-text="migrating ? 'Migrating…' : 'Migrate ' + current.keys.length + ' key' + (current.keys.length === 1 ? '' : 's')"
                        ></button>

                        <div x-show="migrateResults" class="mt-3 space-y-2">
                            <template x-for="r in migrateResults" :key="r.name">
                                <div class="rounded-md border border-base-300 px-3 py-2">
                                    <div class="flex items-center justify-between">
                                        <p class="text-sm font-medium" x-text="r.name"></p>
                                        <span class="text-xs font-medium" :class="r.status === 'success' ? 'text-success' : 'text-error'" x-text="r.status === 'success' ? 'Success' : 'Failed'"></span>
                                    </div>
                                    <p x-show="r.renamed_from" class="text-xs text-base-content/50">renamed from <span x-text="r.renamed_from"></span></p>
                                    <p x-show="r.status === 'failed'" class="text-xs text-error" x-text="r.error"></p>
                                </div>
                            </template>
                            <div class="flex gap-2 pt-1">
                                <button
                                    x-show="migrateResults.some(r => r.status === 'failed')"
                                    @click="retryFailed()"
                                    :disabled="migrating"
                                    class="btn btn-outline btn-sm flex-1"
                                >Retry failed keys</button>
                                <button @click="startOverMigrate()" class="btn btn-outline btn-sm flex-1">Start over</button>
                            </div>
                        </div>
                    </div>
                </template>
            </div>
        </div>

    </div>

    {{-- Create key modal --}}
    <div x-show="showCreateKey" x-cloak class="modal modal-open">
        <div class="modal-box max-w-sm">
            <h3 class="font-semibold">Create key</h3>
            <input type="text" x-model="newKeyName" placeholder="Key name" class="input input-bordered w-full mt-3" @keydown.enter="createKey()">
            <p x-show="createError" x-text="createError" class="mt-1 text-xs text-error"></p>
            <div class="modal-action">
                <button @click="showCreateKey = false; newKeyName = ''; createError = ''" class="btn btn-ghost flex-1">Cancel</button>
                <button @click="createKey()" class="btn btn-neutral flex-1">Create</button>
            </div>
        </div>
        <div class="modal-backdrop" @click="showCreateKey = false"></div>
    </div>

    {{-- Delete one key confirm --}}
    <div x-show="deleteTarget" x-cloak class="modal modal-open">
        <div class="modal-box max-w-sm">
            <h3 class="font-semibold">Delete key?</h3>
            <p class="mt-1 text-sm text-base-content/60" x-text="deleteTarget ? 'This permanently deletes ' + deleteTarget.name + '.' : ''"></p>
            <div class="modal-action">
                <button @click="deleteTarget = null" class="btn btn-ghost flex-1">Cancel</button>
                <button @click="confirmDeleteKey()" class="btn btn-error flex-1">Delete</button>
            </div>
        </div>
        <div class="modal-backdrop" @click="deleteTarget = null"></div>
    </div>

    {{-- Delete all confirm / result --}}
    <div x-show="showDeleteAll || deleteAllResult" x-cloak class="modal modal-open">
        <div class="modal-box max-w-sm">
            <template x-if="showDeleteAll && !deleteAllResult">
                <div>
                    <h3 class="font-semibold">Delete all keys?</h3>
                    <p class="mt-1 text-sm text-base-content/60" x-text="'This permanently deletes all ' + current.keys.length + ' keys on this server.'"></p>
                    <div class="modal-action">
                        <button @click="showDeleteAll = false" class="btn btn-ghost flex-1">Cancel</button>
                        <button @click="confirmDeleteAll()" class="btn btn-error flex-1">Delete all</button>
                    </div>
                </div>
            </template>
            <template x-if="deleteAllResult">
                <div>
                    <h3 class="font-semibold">Delete all — results</h3>
                    <p class="mt-1 text-sm text-base-content/60" x-text="deleteAllResult.deleted + ' deleted, ' + deleteAllResult.failed + ' failed.'"></p>
                    <div class="mt-3 space-y-1.5 max-h-48 overflow-y-auto">
                        <template x-for="d in deleteAllResult.results" :key="d.name">
                            <div class="flex items-center justify-between text-xs">
                                <span x-text="d.name"></span>
                                <span :class="d.status === 'deleted' ? 'text-success' : 'text-error'" x-text="d.status === 'deleted' ? 'Deleted' : d.error"></span>
                            </div>
                        </template>
                    </div>
                    <div class="modal-action">
                        <button @click="deleteAllResult = null" class="btn btn-neutral btn-block">Done</button>
                    </div>
                </div>
            </template>
        </div>
        <div class="modal-backdrop" @click="showDeleteAll = false; deleteAllResult = null"></div>
    </div>

</div>

<script>
    function classicManager() {
        return {
            current: { json: '', apiUrl: '', connected: false, label: '', keys: [], loading: false, error: '' },
            migrateTo: { json: '', apiUrl: '', connected: false, label: '', loading: false, error: '' },
            showCreateKey: false,
            newKeyName: '',
            createError: '',
            copiedId: null,
            deleteTarget: null,
            showDeleteAll: false,
            deleteAllResult: null,
            migrating: false,
            migrateResults: null,

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

            async postJson(url, body) {
                const response = await fetch(url, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(body),
                });
                const data = await response.json();
                return { ok: response.ok, data };
            },

            async connectCurrent() {
                const apiUrl = this.parseApiUrl(this.current.json);
                if (!apiUrl) {
                    this.current.error = 'Invalid server JSON — must include an https apiUrl.';
                    return;
                }
                this.current.error = '';
                this.current.loading = true;
                try {
                    const { ok, data } = await this.postJson('/classic/keys/list', { apiUrl });
                    if (!ok) {
                        this.current.error = data.error || 'Failed to connect.';
                        return;
                    }
                    this.current.apiUrl = apiUrl;
                    this.current.connected = true;
                    this.current.label = new URL(apiUrl).host;
                    this.current.keys = data;
                } catch (e) {
                    this.current.error = 'Failed to connect.';
                } finally {
                    this.current.loading = false;
                }
            },

            startOverCurrent() {
                this.current = { json: '', apiUrl: '', connected: false, label: '', keys: [], loading: false, error: '' };
                this.startOverMigrate();
            },

            copy(key) {
                navigator.clipboard.writeText(key.accessUrl);
                this.copiedId = key.id;
                setTimeout(() => { this.copiedId = null }, 1500);
            },

            async createKey() {
                if (!this.newKeyName.trim()) {
                    this.createError = 'Name is required.';
                    return;
                }
                this.createError = '';
                try {
                    const { ok, data } = await this.postJson('/classic/keys/create', {
                        apiUrl: this.current.apiUrl,
                        name: this.newKeyName,
                    });
                    if (!ok) {
                        this.createError = data.error || 'Failed to create key.';
                        return;
                    }
                    this.current.keys.push(data);
                    this.showCreateKey = false;
                    this.newKeyName = '';
                } catch (e) {
                    this.createError = 'Failed to create key.';
                }
            },

            async confirmDeleteKey() {
                const target = this.deleteTarget;
                this.deleteTarget = null;
                const { ok } = await this.postJson('/classic/keys/delete', {
                    apiUrl: this.current.apiUrl,
                    name: target.name,
                });
                if (ok) {
                    this.current.keys = this.current.keys.filter(k => k.id !== target.id);
                }
            },

            async confirmDeleteAll() {
                this.showDeleteAll = false;
                const { data } = await this.postJson('/classic/keys/delete-all', { apiUrl: this.current.apiUrl });
                this.deleteAllResult = data;
                const failedNames = new Set(data.results.filter(r => r.status === 'failed').map(r => r.name));
                this.current.keys = this.current.keys.filter(k => failedNames.has(k.name));
            },

            async connectMigrateTo() {
                const apiUrl = this.parseApiUrl(this.migrateTo.json);
                if (!apiUrl) {
                    this.migrateTo.error = 'Invalid server JSON — must include an https apiUrl.';
                    return;
                }
                this.migrateTo.error = '';
                this.migrateTo.loading = true;
                try {
                    const { ok, data } = await this.postJson('/classic/keys/list', { apiUrl });
                    if (!ok) {
                        this.migrateTo.error = data.error || 'Failed to connect.';
                        return;
                    }
                    this.migrateTo.apiUrl = apiUrl;
                    this.migrateTo.connected = true;
                    this.migrateTo.label = new URL(apiUrl).host;
                } catch (e) {
                    this.migrateTo.error = 'Failed to connect.';
                } finally {
                    this.migrateTo.loading = false;
                }
            },

            startOverMigrate() {
                this.migrateTo = { json: '', apiUrl: '', connected: false, label: '', loading: false, error: '' };
                this.migrateResults = null;
            },

            async startMigration() {
                this.migrating = true;
                try {
                    const { data } = await this.postJson('/classic/keys/migrate', {
                        sourceKeys: this.current.keys,
                        destApiUrl: this.migrateTo.apiUrl,
                    });
                    this.migrateResults = data;
                } finally {
                    this.migrating = false;
                }
            },

            async retryFailed() {
                const failedNames = this.migrateResults.filter(r => r.status === 'failed').map(r => r.name);
                this.migrating = true;
                try {
                    const { data: retried } = await this.postJson('/classic/keys/migrate', {
                        sourceKeys: this.current.keys,
                        destApiUrl: this.migrateTo.apiUrl,
                        onlyNames: failedNames,
                    });
                    // A retried result correlates to its original entry by
                    // (renamed_from ?? name) — failures always keep the
                    // original requested name, successes carry renamed_from
                    // only when a suffix was applied.
                    this.migrateResults = this.migrateResults.map(r => {
                        const match = retried.find(nr => (nr.renamed_from || nr.name) === r.name);
                        return match || r;
                    });
                } finally {
                    this.migrating = false;
                }
            },
        };
    }
</script>
@endsection
