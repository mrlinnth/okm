@extends('layouts.master')

@section('title', $title)

@section('content')
<div x-data="subscriptionLedger()" x-cloak>
    <div class="flex items-start justify-between gap-4">
        <div>
            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-primary">Key ledger</p>
            <h1 class="mt-1 text-2xl font-semibold tracking-tight">Subscriptions</h1>
            <p class="mt-1 text-sm text-base-content/60">Every recipient, key, and renewal in one place.</p>
        </div>
        <button @click="openCreate()" class="btn btn-neutral btn-sm shrink-0 gap-1.5">
            <span class="text-lg leading-none">+</span><span class="hidden sm:inline">New subscription</span>
        </button>
    </div>

    <div class="mt-6 rounded-box border border-base-300 bg-base-100 p-3 shadow-sm sm:flex sm:items-center sm:gap-3">
        <label class="input flex items-center gap-2 sm:flex-1">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 opacity-50" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m21 21-4.35-4.35M17 10.5A6.5 6.5 0 1 1 4 10.5a6.5 6.5 0 0 1 13 0Z" /></svg>
            <input x-model="filters.search" type="search" class="grow" placeholder="Search recipient" />
        </label>
        <div class="mt-2 grid grid-cols-2 gap-2 sm:mt-0 sm:flex">
            <select x-model="filters.status" class="select select-sm w-full sm:w-36"><option value="">All statuses</option><option value="active">Active</option><option value="disabled">Disabled</option><option value="expired">Expired</option></select>
            <select x-model="filters.serverId" class="select select-sm w-full sm:w-40"><option value="">All servers</option><template x-for="server in servers" :key="server._id"><option :value="server._id" x-text="server.label"></option></template></select>
        </div>
        <label class="mt-2 flex cursor-pointer items-center gap-2 px-1 text-sm sm:mt-0">
            <input x-model="filters.expiringSoon" type="checkbox" class="checkbox checkbox-sm checkbox-warning" /> Expiring soon
        </label>
    </div>

    <template x-if="notice"><div class="alert alert-warning mt-4 text-sm"><span x-text="notice"></span><button @click="notice = ''" class="btn btn-ghost btn-xs">Dismiss</button></div></template>

    <div class="mt-5 hidden overflow-visible rounded-box border border-base-300 bg-base-100 lg:block">
        <table class="table table-zebra"><thead><tr><th>Recipient / key</th><th>Server</th><th>Status</th><th>Expiry</th><th class="text-right">Actions</th></tr></thead>
            <tbody><template x-for="sub in filtered()" :key="sub._id"><tr>
                <td><div x-show="editing.id !== sub._id || editing.field !== 'recipientName'" @click="startEdit(sub, 'recipientName')" class="cursor-pointer font-medium hover:underline decoration-dotted" x-text="sub.recipientName"></div><input x-show="editing.id === sub._id && editing.field === 'recipientName'" x-model="editing.value" @blur="saveEdit(sub)" @keydown.enter.prevent="saveEdit(sub)" class="input input-sm" />
                    <div x-show="editing.id !== sub._id || editing.field !== 'keyName'" @click="startEdit(sub, 'keyName')" class="cursor-pointer text-xs text-base-content/50 hover:underline decoration-dotted" x-text="sub.keyName"></div><input x-show="editing.id === sub._id && editing.field === 'keyName'" x-model="editing.value" @blur="saveEdit(sub)" @keydown.enter.prevent="saveEdit(sub)" class="input input-sm mt-1" /></td>
                <td x-text="serverLabel(sub.serverId)"></td><td><span class="badge badge-sm capitalize" :class="statusClass(sub.status)" x-text="sub.status"></span><span x-show="isSoon(sub)" class="ml-1 text-xs text-warning">soon</span></td>
                <td><button x-show="editing.id !== sub._id || editing.field !== 'expiryDate'" @click="startEdit(sub, 'expiryDate')" class="hover:underline decoration-dotted" x-text="formatDate(sub.expiryDate)"></button><input x-show="editing.id === sub._id && editing.field === 'expiryDate'" type="date" x-model="editing.value" @change="saveEdit(sub)" @blur="saveEdit(sub)" class="input input-sm" /></td>
                <td><div class="flex justify-end gap-1"><button @click="copyKey(sub)" class="btn btn-ghost btn-xs" x-text="copiedId === sub._id ? 'Copied!' : 'Copy key'"></button><button @click="copyText(sub.shareLink, sub._id + '-link')" class="btn btn-ghost btn-xs" x-text="copiedId === sub._id + '-link' ? 'Copied!' : 'Copy link'"></button><div class="dropdown dropdown-end"><button tabindex="0" class="btn btn-ghost btn-xs">•••</button><ul tabindex="0" class="dropdown-content menu z-20 w-44 rounded-box border border-base-300 bg-base-100 p-2 shadow"><li><button @click="extend(sub)">Extend</button></li><li><button @click="openMove(sub)">Move</button></li><li><button @click="reroll(sub)" x-text="rerolledId === sub._id ? 'New key issued' : 'Reroll key'"></button></li><li><button @click="toggleStatus(sub)" x-text="sub.status === 'active' ? 'Disable' : 'Enable'"></button></li><li><button @click="deleteTarget = sub" class="text-error">Delete</button></li></ul></div></div></td>
            </tr></template></tbody>
        </table>
    </div>

    <div class="mt-5 space-y-3 lg:hidden"><template x-for="sub in filtered()" :key="sub._id"><article class="rounded-box border border-base-300 bg-base-100 p-4 shadow-sm"><div class="flex justify-between gap-3"><div class="min-w-0"><p class="truncate font-semibold" x-text="sub.recipientName"></p><p class="truncate text-xs text-base-content/50" x-text="sub.keyName"></p></div><span class="badge badge-sm shrink-0 capitalize" :class="statusClass(sub.status)" x-text="sub.status"></span></div><div class="mt-3 flex justify-between text-xs text-base-content/60"><span x-text="serverLabel(sub.serverId)"></span><span x-text="formatDate(sub.expiryDate) + (isSoon(sub) ? ' · soon' : '')"></span></div><div class="mt-4 flex gap-2"><button @click="copyKey(sub)" class="btn btn-outline btn-sm flex-1" x-text="copiedId === sub._id ? 'Copied!' : 'Copy key'"></button><button @click="copyText(sub.shareLink, sub._id + '-link')" class="btn btn-outline btn-sm flex-1" x-text="copiedId === sub._id + '-link' ? 'Copied!' : 'Copy link'"></button><button @click="openMove(sub)" class="btn btn-ghost btn-sm">Manage</button></div></article></template></div>
    <p x-show="filtered().length === 0" class="py-12 text-center text-sm text-base-content/45">No subscriptions match these filters.</p>

    <div class="modal" :class="{ 'modal-open': showCreate }"><div class="modal-box"><h2 class="text-lg font-semibold">New subscription</h2><div class="mt-4 space-y-3"><input x-model="createForm.recipientName" class="input w-full" placeholder="Recipient name" /><input x-model="createForm.keyName" class="input w-full" placeholder="Key name" /><select x-model="createForm.serverId" class="select w-full"><option value="">Select active server</option><template x-for="server in servers" :key="server._id"><option :value="server._id" x-text="server.label"></option></template></select><select x-model.number="createForm.duration" class="select w-full"><option :value="1">1 month</option><option :value="2">2 months</option><option :value="3">3 months</option></select><textarea x-model="createForm.notes" class="textarea w-full" placeholder="Internal notes (optional)"></textarea></div><p x-show="createError" class="mt-2 text-sm text-error" x-text="createError"></p><div class="modal-action"><button @click="showCreate = false" class="btn btn-ghost">Cancel</button><button @click="create()" :disabled="busy" class="btn btn-neutral" x-text="busy ? 'Creating…' : 'Create subscription'"></button></div></div><div class="modal-backdrop" @click="showCreate = false"></div></div>
    <div class="modal" :class="{ 'modal-open': successSub }"><div class="modal-box"><h2 class="text-lg font-semibold">Subscription created</h2><p class="mt-2 text-sm"><span x-text="successSub?.recipientName"></span> expires <span class="font-medium" x-text="formatDate(successSub?.expiryDate)"></span>.</p><div class="mt-4 join w-full"><input :value="successSub?.shareLink" readonly class="input join-item w-full text-xs" /><button @click="copyText(successSub?.shareLink, 'share')" class="btn join-item" x-text="copiedId === 'share' ? 'Copied!' : 'Copy link'"></button></div><div class="modal-action"><button @click="successSub = null" class="btn btn-neutral">Done</button></div></div><div class="modal-backdrop" @click="successSub = null"></div></div>
    <div class="modal" :class="{ 'modal-open': moveTarget }"><div class="modal-box"><h2 class="text-lg font-semibold">Move subscription</h2><p class="mt-1 text-sm text-base-content/60" x-text="moveTarget ? 'Issue a replacement key for ' + moveTarget.recipientName + ' on:' : ''"></p><select x-model="moveServerId" class="select mt-4 w-full"><option value="">Select destination</option><template x-for="server in availableMoveServers()" :key="server._id"><option :value="server._id" x-text="server.label"></option></template></select><p x-show="moveError" class="mt-2 text-sm text-error" x-text="moveError"></p><div class="modal-action"><button @click="moveTarget = null" class="btn btn-ghost">Cancel</button><button @click="move()" :disabled="busy" class="btn btn-neutral">Move</button></div></div><div class="modal-backdrop" @click="moveTarget = null"></div></div>
    <div class="modal" :class="{ 'modal-open': deleteTarget }"><div class="modal-box"><h2 class="text-lg font-semibold">Delete subscription?</h2><p class="mt-2 text-sm text-base-content/60">This permanently removes the subscription and its active Outline key.</p><div class="modal-action"><button @click="deleteTarget = null" class="btn btn-ghost">Cancel</button><button @click="remove()" :disabled="busy" class="btn btn-error">Delete</button></div></div><div class="modal-backdrop" @click="deleteTarget = null"></div></div>
</div>

<script>
function subscriptionLedger() {
    return {
        subscriptions: {!! json_encode($subscriptions, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) !!}, servers: {!! json_encode($servers, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) !!},
        filters: { search: '', status: '', serverId: '', expiringSoon: false }, editing: { id: null, field: '', value: '' }, copiedId: null, rerolledId: null, notice: '', showCreate: false, successSub: null, deleteTarget: null, moveTarget: null, moveServerId: '', moveError: '', createError: '', busy: false,
        createForm: { recipientName: '', keyName: '', serverId: '', duration: 1, notes: '' },
        filtered() { const query = this.filters.search.trim().toLowerCase(); return this.subscriptions.filter(s => (!query || (s.recipientName || '').toLowerCase().includes(query)) && (!this.filters.status || s.status === this.filters.status) && (!this.filters.serverId || s.serverId === this.filters.serverId) && (!this.filters.expiringSoon || this.isSoon(s))); },
        formatDate(value) { return value ? new Date(value + 'T00:00:00').toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' }) : '—'; },
        serverLabel(id) { return this.servers.find(s => s._id === id)?.label || 'Unknown server'; }, statusClass(status) { return { active: 'badge-success', disabled: 'badge-ghost', expired: 'badge-error' }[status] || 'badge-ghost'; },
        isSoon(s) { const today = new Date(); today.setHours(0,0,0,0); const due = new Date(s.expiryDate + 'T00:00:00'); const days = Math.round((due - today) / 86400000); return s.status === 'active' && days >= 0 && days <= 7; },
        async post(url, body = {}) { const token = document.querySelector('meta[name="X-CSRF-TOKEN"]'); const headers = { 'Content-Type': 'application/json' }; if (token) headers['X-CSRF-TOKEN'] = token.content; const response = await fetch(url, { method: 'POST', headers, body: JSON.stringify(body) }); const data = await response.json(); if (response.status === 401) { window.location.assign(data.login || '/manage'); return; } if (!response.ok) throw new Error(data.error || 'Request failed.'); return data; },
        replace(updated) { const index = this.subscriptions.findIndex(s => s._id === updated._id); if (index >= 0) this.subscriptions.splice(index, 1, { ...this.subscriptions[index], ...updated }); if (updated.warning) this.notice = updated.warning; },
        copyText(value, id) { navigator.clipboard.writeText(value || ''); this.copiedId = id; setTimeout(() => this.copiedId = null, 1500); }, copyKey(sub) { this.copyText(sub.accessUrl, sub._id); },
        openCreate() { this.createForm = { recipientName: '', keyName: '', serverId: this.servers[0]?._id || '', duration: 1, notes: '' }; this.createError = ''; this.showCreate = true; },
        async create() { this.createError = ''; this.busy = true; try { const created = await this.post('/subscriptions', this.createForm); this.subscriptions.unshift(created); this.showCreate = false; this.successSub = created; } catch (e) { this.createError = e.message; } finally { this.busy = false; } },
        startEdit(sub, field) { this.editing = { id: sub._id, field, value: sub[field] || '' }; },
        async saveEdit(sub) { if (this.editing.id !== sub._id) return; const { field, value } = this.editing; this.editing = { id: null, field: '', value: '' }; if (value === sub[field]) return; try { const updated = field === 'expiryDate' ? await this.post(`/subscriptions/${sub._id}/expiry`, { date: value }) : await this.post(`/subscriptions/${sub._id}`, { [field]: value }); this.replace(updated); } catch (e) { this.notice = e.message; } },
        async extend(sub) { try { this.replace(await this.post(`/subscriptions/${sub._id}/extend`)); } catch (e) { this.notice = e.message; } },
        async toggleStatus(sub) { try { this.replace(await this.post(`/subscriptions/${sub._id}/${sub.status === 'active' ? 'disable' : 'enable'}`)); } catch (e) { this.notice = e.message; } },
        async reroll(sub) { try { this.replace(await this.post(`/subscriptions/${sub._id}/reroll`)); this.rerolledId = sub._id; setTimeout(() => this.rerolledId = null, 1800); } catch (e) { this.notice = e.message; } },
        openMove(sub) { this.moveTarget = sub; this.moveServerId = ''; this.moveError = ''; }, availableMoveServers() { return this.servers.filter(server => server.active && (!this.moveTarget || server._id !== this.moveTarget.serverId)); },
        async move() { if (!this.moveServerId) { this.moveError = 'Choose a destination server.'; return; } this.busy = true; try { this.replace(await this.post(`/subscriptions/${this.moveTarget._id}/move`, { destinationServerId: this.moveServerId })); this.moveTarget = null; } catch (e) { this.moveError = e.message; } finally { this.busy = false; } },
        async remove() { this.busy = true; try { await this.post(`/subscriptions/${this.deleteTarget._id}/delete`); this.subscriptions = this.subscriptions.filter(s => s._id !== this.deleteTarget._id); this.deleteTarget = null; } catch (e) { this.notice = e.message; } finally { this.busy = false; } },
    };
}
</script>
@endsection
