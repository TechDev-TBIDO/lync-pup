<x-layouts.admin title="Manage Admins">

    @php
    // One class string so both Invite triggers stay identical (mirrors
    // admin/coordinators/index).
    $addBtn = 'items-center gap-2 whitespace-nowrap rounded-lg bg-gradient-to-r from-[#6D0D23] to-[#11386A] px-3 py-2.5 text-sm font-medium text-white transition hover:from-[#5A0A1D] hover:to-[#0D2F59] sm:px-4';

    // A failed Invite comes back with default-bag errors — reopen the modal
    // so they're visible. A failed Transfer (wrong password) comes back in
    // the 'transfer' bag with the target admin's id.
    $reopenInvite = $errors->any();
    $reopenTransferFor = $errors->transfer->any() ? (int) old('_transfer_user_id') : null;

    $statusBadge = [
        'Active' => ['label' => 'Active', 'class' => 'bg-green-50 text-green-700 ring-green-600/20'],
        'Pending' => ['label' => 'Invitation Pending', 'class' => 'bg-amber-50 text-amber-700 ring-amber-600/20'],
        'Inactive' => ['label' => 'Disabled', 'class' => 'bg-gray-100 text-gray-600 ring-gray-500/20'],
    ];

    $initials = fn (string $name) => collect(preg_split('/\s+/', trim($name)))
        ->filter()->take(2)->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)))->implode('');
    @endphp

    <div x-data="{ inviteOpen: @js($reopenInvite) }">

        {{-- Page header --}}
        <div class="mb-6 flex items-start justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 sm:text-3xl">Manage Admins</h1>
                <p class="mt-1 text-sm text-gray-500 sm:text-base">Invite TBI staff to the Admin Console and control who can sign in. Only you, as Super Admin, can see this page.</p>
            </div>

            <div class="flex flex-shrink-0 items-center gap-3">
                <x-version-history-panel :entries="$adminVersionHistory" :show-cohort="false" />

                <button type="button" @click="inviteOpen = true" class="{{ $addBtn }} hidden sm:flex">
                    <span class="text-lg leading-none">+</span> Invite Admin
                </button>
            </div>
        </div>

        <div class="mb-4 flex items-center justify-between gap-4">
            <h2 class="font-bold text-gray-900">Admin Accounts <span class="font-normal text-gray-500">({{ $admins->count() }})</span></h2>

            <button type="button" @click="inviteOpen = true" class="{{ $addBtn }} flex sm:hidden">
                <span class="text-lg leading-none">+</span> Invite
            </button>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white">
            {{-- Column headings (desktop only; rows stack on phones) --}}
            <div class="hidden grid-cols-12 gap-4 border-b border-gray-200 px-5 py-3 text-xs font-semibold uppercase tracking-wide text-gray-500 md:grid">
                <div class="col-span-5">Admin</div>
                <div class="col-span-3">Status</div>
                <div class="col-span-3">Since</div>
                <div class="col-span-1 text-right"><span class="sr-only">Actions</span></div>
            </div>

            <ul class="divide-y divide-gray-100">
                @foreach ($admins as $admin)
                @php
                $isMe = $admin->is(auth()->user());
                $badge = $statusBadge[$admin->account_status] ?? ['label' => $admin->account_status, 'class' => 'bg-gray-100 text-gray-600 ring-gray-500/20'];
                $canAct = ! $isMe && ! $admin->isSuperAdmin();
                $label = $admin->name . ' (' . $admin->email . ')';
                @endphp

                <li class="grid grid-cols-12 items-center gap-x-4 gap-y-2 px-5 py-4"
                    x-data="{ menuOpen: false, confirm: null, transferOpen: @js($reopenTransferFor === $admin->id) }">

                    {{-- Name + email --}}
                    <div class="col-span-10 flex min-w-0 items-center gap-3 md:col-span-5">
                        <div class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full text-sm font-bold
                            {{ $admin->account_status === 'Inactive' ? 'bg-gray-100 text-gray-400' : 'bg-gradient-to-r from-[#6D0D23] to-[#11386A] text-white' }}">
                            {{ $initials($admin->name) ?: '?' }}
                        </div>
                        <div class="min-w-0">
                            <p class="flex flex-wrap items-center gap-x-2 gap-y-1 text-sm font-semibold {{ $admin->account_status === 'Inactive' ? 'text-gray-500' : 'text-gray-900' }}">
                                <span class="truncate">{{ $admin->name }}</span>
                                @if ($admin->isSuperAdmin())
                                <span class="inline-flex items-center gap-1 rounded-full bg-gradient-to-r from-[#6D0D23] to-[#11386A] px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-white">
                                    <svg class="h-3 w-3" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l2.9 6.9L22 9.6l-5.5 4.8L18.2 22 12 18.3 5.8 22l1.7-7.6L2 9.6l7.1-.7z" /></svg>
                                    Super Admin
                                </span>
                                @endif
                                @if ($isMe)
                                <span class="text-xs font-normal text-gray-500">(You)</span>
                                @endif
                            </p>
                            <p class="truncate text-xs text-gray-500">{{ $admin->email }}</p>
                        </div>
                    </div>

                    {{-- Actions menu (sits top-right on phones) --}}
                    <div class="col-span-2 flex justify-end md:order-last md:col-span-1">
                        @if ($canAct)
                        <div class="relative" @click.outside="menuOpen = false" @keydown.escape.window="menuOpen = false">
                            <button type="button" @click="menuOpen = !menuOpen" aria-label="Actions for {{ $admin->name }}"
                                class="flex h-8 w-8 items-center justify-center rounded-full text-gray-500 transition hover:bg-gray-100 hover:text-[#6D0D23]">
                                <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="5" r="2" /><circle cx="12" cy="12" r="2" /><circle cx="12" cy="19" r="2" /></svg>
                            </button>

                            <div x-show="menuOpen" x-cloak x-transition.origin.top.right
                                class="absolute right-0 top-9 z-30 w-56 overflow-hidden rounded-xl border border-gray-100 bg-white py-1 text-left shadow-xl">
                                @php $item = 'block w-full px-4 py-2 text-left text-sm font-medium text-gray-800 transition hover:bg-gray-50'; @endphp

                                @if ($admin->account_status === 'Pending')
                                <form method="POST" action="{{ route('admin.admins.resend', $admin) }}">
                                    @csrf
                                    <button type="submit" class="{{ $item }}">Resend Invitation</button>
                                </form>
                                <button type="button" class="{{ $item }} text-red-700" @click="confirm = 'cancel'; menuOpen = false">Cancel Invitation</button>
                                @elseif ($admin->account_status === 'Active')
                                <button type="button" class="{{ $item }}" @click="transferOpen = true; menuOpen = false">Make Super Admin</button>
                                <button type="button" class="{{ $item }} text-red-700" @click="confirm = 'disable'; menuOpen = false">Disable Account</button>
                                @elseif ($admin->account_status === 'Inactive')
                                <button type="button" class="{{ $item }}" @click="confirm = 'enable'; menuOpen = false">Re-enable Account</button>
                                <button type="button" class="{{ $item }} text-red-700" @click="confirm = 'delete'; menuOpen = false">Delete Account</button>
                                @endif
                            </div>
                        </div>
                        @endif
                    </div>

                    {{-- Status --}}
                    <div class="col-span-6 md:col-span-3">
                        <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold ring-1 ring-inset {{ $badge['class'] }}">{{ $badge['label'] }}</span>
                    </div>

                    {{-- Since --}}
                    <div class="col-span-6 text-xs text-gray-500 md:col-span-3">
                        @if ($admin->account_status === 'Pending' && $admin->invitation_sent_at)
                            @php $expired = $admin->invitation_sent_at->copy()->addHours(\App\Models\User::INVITATION_EXPIRES_HOURS)->isPast(); @endphp
                            Invited {{ $admin->invitation_sent_at->diffForHumans() }}
                            @if ($expired)
                            <span class="block font-semibold text-red-600">Link expired — resend</span>
                            @endif
                        @else
                            Added {{ optional($admin->created_at)->format('M j, Y') ?? '—' }}
                        @endif
                    </div>

                    @if ($canAct)
                    {{-- Cancel / Disable / Re-enable confirmations --}}
                    <template x-teleport="body">
                        <div>
                            <x-confirm-action-modal
                                show="confirm === 'cancel'" close="confirm = null"
                                title="Cancel Invitation"
                                :message="'Cancel the invitation for ' . $label . '? Their link will stop working and the pending account will be removed.'"
                                :action="route('admin.admins.destroy', $admin)" method="DELETE" confirm-label="Cancel Invite" />

                            <x-confirm-action-modal
                                show="confirm === 'disable'" close="confirm = null"
                                title="Disable Admin"
                                :message="$admin->name . ' will be signed out and will no longer be able to sign in. Their name stays on everything they worked on. You can re-enable them anytime.'"
                                :action="route('admin.admins.disable', $admin)" method="PATCH" confirm-label="Disable" icon="people" />

                            <x-confirm-action-modal
                                show="confirm === 'enable'" close="confirm = null"
                                title="Re-enable Admin"
                                :message="$admin->name . ' will be able to sign in to the Admin Console again.'"
                                :action="route('admin.admins.enable', $admin)" method="PATCH" confirm-label="Re-enable" icon="people" />

                            {{-- Delete: permanent, so the admin has to type DELETE
                                 (checked again server-side) before the button unlocks. --}}
                            @if ($admin->account_status === 'Inactive')
                            <div x-show="confirm === 'delete'" x-cloak x-transition.opacity
                                x-data="{ typed: '' }" x-effect="if (confirm !== 'delete') typed = ''"
                                class="fixed inset-0 z-[100] flex items-center justify-center bg-black/50 p-4" style="display:none;">
                                <div class="relative w-full max-w-lg rounded-2xl bg-white px-5 pb-5 pt-8 text-center shadow-2xl sm:px-6">
                                    <button type="button" @click="confirm = null"
                                        class="absolute right-3 top-3 flex h-6 w-6 items-center justify-center rounded-full border border-gray-900 text-gray-900 transition hover:border-transparent hover:bg-gradient-to-r hover:from-[#6D0D23] hover:to-[#11386A] hover:text-white"
                                        aria-label="Close">
                                        <svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6L6 18M6 6l12 12" /></svg>
                                    </button>

                                    <div class="mx-auto flex h-11 w-11 items-center justify-center rounded-full bg-gradient-to-r from-[#6D0D23] to-[#11386A]">
                                        <img src="{{ asset('images/icons/trash.svg') }}" alt="" class="h-5 w-5">
                                    </div>

                                    <h2 class="mt-2.5 bg-gradient-to-r from-[#6D0D23] to-[#11386A] bg-clip-text text-base font-bold text-transparent sm:text-lg">
                                        Delete Admin
                                    </h2>

                                    <p class="mt-1.5 text-xs leading-5 text-gray-600">
                                        Permanently delete <span class="font-semibold text-gray-900">{{ $label }}</span>? This cannot be undone.
                                        Their name will still appear on everything they worked on in Edit History. To give them access again later,
                                        you would need to send a new invitation.
                                    </p>

                                    <form method="POST" action="{{ route('admin.admins.destroy', $admin) }}" class="mt-4 text-left">
                                        @csrf
                                        @method('DELETE')

                                        <label for="delete_confirm_{{ $admin->id }}" class="mb-1 block text-sm font-medium text-gray-700">
                                            Type <span class="font-bold text-red-700">DELETE</span> to confirm
                                        </label>
                                        <input id="delete_confirm_{{ $admin->id }}" type="text" name="confirmation" x-model="typed" autocomplete="off" spellcheck="false"
                                            placeholder="DELETE"
                                            class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-red-600 focus:ring-2 focus:ring-red-600">

                                        <div class="mt-4 grid grid-cols-2 gap-3 sm:gap-4">
                                            <button type="button" @click="confirm = null"
                                                class="h-10 w-full rounded-md border border-gray-300 bg-white text-sm font-bold text-gray-800 transition hover:bg-gray-50">
                                                Cancel
                                            </button>
                                            <button type="submit" :disabled="typed.trim().toUpperCase() !== 'DELETE'"
                                                class="h-10 w-full rounded-md bg-gradient-to-r from-red-600 to-red-800 text-sm font-bold text-white transition hover:opacity-95 disabled:cursor-not-allowed disabled:opacity-40">
                                                Delete Permanently
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                            @endif
                        </div>
                    </template>

                    {{-- Transfer Super Admin: needs the current Super Admin's password --}}
                    @if ($admin->account_status === 'Active')
                    <template x-teleport="body">
                        <div x-show="transferOpen" x-cloak x-transition.opacity
                            class="fixed inset-0 z-[100] flex items-center justify-center bg-black/50 p-4" style="display:none;">
                            <div class="relative w-full max-w-lg rounded-2xl bg-white px-5 pb-5 pt-8 text-center shadow-2xl sm:px-6">
                                <button type="button" @click="transferOpen = false"
                                    class="absolute right-3 top-3 flex h-6 w-6 items-center justify-center rounded-full border border-gray-900 text-gray-900 transition hover:border-transparent hover:bg-gradient-to-r hover:from-[#6D0D23] hover:to-[#11386A] hover:text-white"
                                    aria-label="Close">
                                    <svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6L6 18M6 6l12 12" /></svg>
                                </button>

                                <div class="mx-auto flex h-11 w-11 items-center justify-center rounded-full bg-gradient-to-r from-[#6D0D23] to-[#11386A] text-white">
                                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l2.9 6.9L22 9.6l-5.5 4.8L18.2 22 12 18.3 5.8 22l1.7-7.6L2 9.6l7.1-.7z" /></svg>
                                </div>

                                <h2 class="mt-2.5 bg-gradient-to-r from-[#6D0D23] to-[#11386A] bg-clip-text text-base font-bold text-transparent sm:text-lg">
                                    Transfer Super Admin
                                </h2>

                                <p class="mt-1.5 text-xs leading-5 text-gray-600">
                                    <span class="font-semibold text-gray-900">{{ $admin->name }}</span> will become the Super Admin.
                                    You'll become a regular admin and will no longer see Manage Admins. There is only one Super Admin at a time.
                                </p>

                                <form method="POST" action="{{ route('admin.admins.transfer', $admin) }}" class="mt-4 text-left">
                                    @csrf
                                    <input type="hidden" name="_transfer_user_id" value="{{ $admin->id }}">

                                    <label for="current_password_{{ $admin->id }}" class="mb-1 block text-sm font-medium text-gray-700">Enter your password to confirm</label>
                                    <input id="current_password_{{ $admin->id }}" type="password" name="current_password" required autocomplete="current-password"
                                        class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-rose-800 focus:ring-2 focus:ring-rose-800">
                                    @if ($reopenTransferFor === $admin->id)
                                    @foreach ($errors->transfer->get('current_password') as $message)
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @endforeach
                                    @endif

                                    <div class="mt-4 grid grid-cols-2 gap-3 sm:gap-4">
                                        <button type="button" @click="transferOpen = false"
                                            class="h-10 w-full rounded-md border border-gray-300 bg-white text-sm font-bold text-gray-800 transition hover:bg-gray-50">
                                            Cancel
                                        </button>
                                        <button type="submit"
                                            class="h-10 w-full rounded-md bg-gradient-to-r from-[#6D0D23] to-[#11386A] text-sm font-bold text-white transition hover:opacity-95">
                                            Transfer
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </template>
                    @endif
                    @endif
                </li>
                @endforeach
            </ul>
        </div>

        {{-- Invite modal --}}
        <template x-teleport="body">
            <div x-show="inviteOpen" x-cloak x-transition.opacity
                class="fixed inset-0 z-[100] flex items-center justify-center bg-black/50 p-4" style="display:none;">
                <div class="relative w-full max-w-lg overflow-hidden rounded-xl bg-white shadow-2xl">
                    <div class="flex items-center justify-between bg-gradient-to-r from-[#6D0D23] to-[#11386A] px-6 py-4 text-white">
                        <h3 class="font-bold">Invite Admin</h3>
                        <button type="button" @click="inviteOpen = false"
                            class="flex h-6 w-6 items-center justify-center rounded-full border border-white text-white transition hover:border-transparent hover:bg-white hover:text-[#6D0D23]"
                            aria-label="Close">
                            <svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M18 6L6 18M6 6l12 12" /></svg>
                        </button>
                    </div>

                    <form method="POST" action="{{ route('admin.admins.store') }}" class="space-y-4 px-6 py-5">
                        @csrf
                        <p class="text-sm text-gray-600">
                            We'll email them a link to set their own password. The link expires in {{ \App\Models\User::INVITATION_EXPIRES_HOURS }} hours.
                            They'll have the same access as every other admin.
                        </p>

                        <div>
                            <label for="invite_name" class="mb-1 block text-sm font-medium text-gray-700">Full Name</label>
                            <input id="invite_name" type="text" name="name" value="{{ old('name') }}" required maxlength="255"
                                class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-rose-800 focus:ring-2 focus:ring-rose-800">
                            @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        {{-- Email typed twice: the invitation link lets whoever has it
                             set the password, so a typo here would hand the account
                             to the wrong person. Compared live (case-insensitive) and
                             again server-side ('confirmed'). --}}
                        <div x-data="{ email: @js(old('email', '')), confirmEmail: @js(old('email_confirmation', '')),
                                get mismatch() { return this.confirmEmail.length > 0 && this.email.trim().toLowerCase() !== this.confirmEmail.trim().toLowerCase() },
                                get matches() { return this.confirmEmail.length > 0 && ! this.mismatch } }"
                            class="space-y-4">
                            <div>
                                <label for="invite_email" class="mb-1 block text-sm font-medium text-gray-700">Email Address</label>
                                <input id="invite_email" type="email" name="email" x-model="email" required maxlength="255" autocomplete="off"
                                    class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-rose-800 focus:ring-2 focus:ring-rose-800">
                                @error('email') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>

                            <div>
                                <label for="invite_email_confirmation" class="mb-1 block text-sm font-medium text-gray-700">Confirm Email Address</label>
                                <input id="invite_email_confirmation" type="email" name="email_confirmation" x-model="confirmEmail" required maxlength="255" autocomplete="off"
                                    @paste.prevent
                                    :class="mismatch ? 'border-red-500 focus:border-red-500 focus:ring-red-500' : 'border-gray-300 focus:border-rose-800 focus:ring-rose-800'"
                                    class="w-full rounded-lg border px-3 py-2.5 text-sm focus:ring-2">
                                <p x-show="mismatch" x-cloak class="mt-1 text-sm text-red-600">The email addresses do not match.</p>
                                <p x-show="matches" x-cloak class="mt-1 text-sm text-green-600">Email addresses match.</p>
                                <p class="mt-1 text-xs text-gray-500">Type it again (pasting is disabled) so the invitation can't go to the wrong person.</p>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-3 pt-1 sm:gap-4">
                            <button type="button" @click="inviteOpen = false"
                                class="h-10 w-full rounded-md border border-gray-300 bg-white text-sm font-bold text-gray-800 transition hover:bg-gray-50">
                                Cancel
                            </button>
                            <button type="submit"
                                class="h-10 w-full rounded-md bg-gradient-to-r from-[#6D0D23] to-[#11386A] text-sm font-bold text-white transition hover:opacity-95">
                                Send Invitation
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </template>
    </div>
</x-layouts.admin>
