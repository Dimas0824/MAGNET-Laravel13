<?php

use function Livewire\Volt\{state, layout, mount};
use App\Events\ChatMessageSent;
use App\Models\Chat;
use App\Models\KontrakMagang;
use Illuminate\Support\Facades\Auth;

layout('components.layouts.user.main');

state([
    'messageText' => '',
    'messages' => [],
    'kontrakMagangId' => null,
    'kontrakData' => null,
    'dosenPembimbing' => null,
    'isAuthorized' => false,
    'lastMessageId' => 0,
]);

mount(function () {
    $this->initializeChat();
});

$initializeChat = function () {
    try {
        $currentUserId = Auth::id();

        // Get active kontrak magang for current mahasiswa
        $kontrak = KontrakMagang::with(['dosenPembimbing', 'mahasiswa'])
            ->where('mahasiswa_id', $currentUserId)
            ->latest()
            ->first();

        if (!$kontrak) {
            session()->flash('error', 'Anda belum memiliki kontrak magang aktif.');
            return;
        }

        $this->kontrakMagangId = $kontrak->id;
        $this->kontrakData = $kontrak;
        $this->dosenPembimbing = $kontrak->dosenPembimbing;
        $this->isAuthorized = true;

        if (!$this->dosenPembimbing) {
            session()->flash('error', 'Dosen pembimbing tidak ditemukan.');
            return;
        }

        // Load initial messages
        $this->loadMessages();
    } catch (\Exception $e) {
        \Log::error('Error initializing chat', [
            'mahasiswa_id' => Auth::id(),
            'error' => $e->getMessage(),
        ]);
        session()->flash('error', 'Terjadi kesalahan saat memuat chat.');
    }
};

$loadMessages = function () {
    if (!$this->isAuthorized || !$this->dosenPembimbing) {
        return;
    }

    try {
        $mahasiswaId = Auth::id();
        $dosenId = $this->dosenPembimbing->id;

        // Load messages for this kontrak, identified by participant ROLE.
        // Raw id comparison is unsafe: mahasiswa.id and dosen.id can collide.
        $messages = Chat::where('kontrak_magang_id', $this->kontrakMagangId)
            ->orderBy('created_at', 'asc')
            ->get();

        $this->messages = $messages
            ->map(function ($chat) use ($mahasiswaId) {
                $isMine = $chat->isSentByMahasiswa();

                return [
                    'id' => $chat->id,
                    'message' => $chat->message,
                    'sender_id' => $chat->sender_id,
                    'sender_type' => $chat->sender_type,
                    'receiver_id' => $chat->receiver_id,
                    'receiver_type' => $chat->receiver_type,
                    'is_mine' => $isMine,
                    'created_at' => $chat->created_at->format('H:i'),
                    'created_date' => $chat->created_at->format('Y-m-d'),
                    'sender_name' => $isMine ? 'Saya' : $this->dosenPembimbing->nama,
                ];
            })
            ->toArray();

        // Update last message ID for polling
        if (!empty($this->messages)) {
            $this->lastMessageId = max(array_column($this->messages, 'id'));
        }
    } catch (\Exception $e) {
        \Log::error('Error loading messages', [
            'error' => $e->getMessage(),
            'kontrak_magang_id' => $this->kontrakMagangId,
        ]);
        $this->messages = [];
    }
};

$checkNewMessages = function () {
    $this->loadMessages();
};

$sendMessage = function () {
    // Validate input
    if (empty(trim($this->messageText))) {
        session()->flash('error', 'Pesan tidak boleh kosong.');
        return;
    }

    if (!$this->isAuthorized) {
        session()->flash('error', 'Anda tidak memiliki akses untuk mengirim pesan.');
        return;
    }

    if (!$this->dosenPembimbing || !$this->kontrakMagangId) {
        session()->flash('error', 'Data tidak lengkap untuk mengirim pesan.');
        return;
    }

    try {
        $mahasiswaId = Auth::id();
        $dosenId = $this->dosenPembimbing->id;

        $chatData = [
            'kontrak_magang_id' => $this->kontrakMagangId,
            'sender_id' => $mahasiswaId,
            'sender_type' => Chat::SENDER_MAHASISWA,
            'receiver_id' => $dosenId,
            'receiver_type' => Chat::SENDER_DOSEN,
            'message' => trim($this->messageText),
        ];

        // Periksa apakah kontrak magang benar-benar ada
        $kontrakExists = KontrakMagang::find($this->kontrakMagangId);
        if (!$kontrakExists) {
            session()->flash('error', 'Kontrak magang tidak ditemukan di database.');
            return;
        }

        // Create message
        $chat = Chat::create($chatData);

        event(new ChatMessageSent($chat));

        // Add message to current messages array immediately
        $this->messages[] = [
            'id' => $chat->id,
            'message' => $chat->message,
            'sender_id' => $chat->sender_id,
            'sender_type' => $chat->sender_type,
            'receiver_id' => $chat->receiver_id,
            'receiver_type' => $chat->receiver_type,
            'is_mine' => true,
            'created_at' => $chat->created_at->format('H:i'),
            'created_date' => $chat->created_at->format('Y-m-d'),
            'sender_name' => 'Saya',
        ];

        // Update last message ID
        $this->lastMessageId = $chat->id;

        // Clear input
        $this->messageText = '';

        // Trigger frontend events
        $this->dispatch('focus-input');
        $this->dispatch('scroll-to-bottom');
    } catch (\Illuminate\Database\QueryException $e) {
        \Log::error('Database error when sending message', [
            'error' => $e->getMessage(),
            'kontrak_magang_id' => $this->kontrakMagangId,
        ]);
        session()->flash('error', 'Gagal mengirim pesan. Silakan coba lagi.');
    } catch (\Exception $e) {
        \Log::error('General error sending message', [
            'error' => $e->getMessage(),
            'kontrak_magang_id' => $this->kontrakMagangId,
        ]);
        session()->flash('error', 'Gagal mengirim pesan. Silakan coba lagi.');
    }
};

?>

<x-slot:user>mahasiswa</x-slot:user>
<div x-data="{
    isVisible: true,
    pollingInterval: null,
    chatChannel: null,
    kontrakMagangId: @js($kontrakMagangId),

    init() {
        this.startPolling();
        this.handleVisibilityChange();

        // Listen for new message events
        this.$wire.on('new-message-received', () => {
            this.scrollToBottom();
            this.showNotification();
        });

        // Listen for other events
        this.$wire.on('focus-input', () => {
            this.focusInput();
        });

        this.$wire.on('scroll-to-bottom', () => {
            this.scrollToBottom();
        });
    },

    startPolling() {
        this.subscribeToChat();
    },

    stopPolling() {
        if (this.chatChannel) {
            window.Echo.leave(`chat.${this.kontrakMagangId}`);
            this.chatChannel = null;
        }
        if (this.pollingInterval) {
            clearInterval(this.pollingInterval);
            this.pollingInterval = null;
        }
    },

    subscribeToChat() {
        if (!window.Echo || this.chatChannel) {
            return;
        }

        this.chatChannel = window.Echo.private(`chat.${this.kontrakMagangId}`)
            .listen('.ChatMessageSent', () => {
                this.$wire.loadMessages();
                this.$wire.$dispatch('new-message-received');
            });
    },

    handleVisibilityChange() {
        document.addEventListener('visibilitychange', () => {
            this.isVisible = !document.hidden;
            if (this.isVisible) {
                this.$wire.loadMessages();
                this.subscribeToChat();
            }
        });
    },

    handleKeydown(event) {
        if (event.key === 'Enter' && !event.shiftKey) {
            event.preventDefault();
            this.$wire.sendMessage();
        }
    },

    scrollToBottom() {
        const chatContainer = document.getElementById('chat-container');
        if (chatContainer) {
            setTimeout(() => {
                chatContainer.scrollTop = chatContainer.scrollHeight;
            }, 100);
        }
    },

    focusInput() {
        const messageInput = document.getElementById('message-input');
        if (messageInput) {
            messageInput.focus();
        }
    },

    showNotification() {
        // Only show notification if tab is not visible
        if (!this.isVisible && 'Notification' in window) {
            if (Notification.permission === 'granted') {
                new Notification('Pesan Baru', {
                    body: 'Anda menerima pesan baru dari dosen pembimbing',
                    icon: '/img/user/lecturer-man.png'
                });
            }
        }
    },

    requestNotificationPermission() {
        if ('Notification' in window && Notification.permission === 'default') {
            Notification.requestPermission();
        }
    }
}" x-init="init()" x-on:beforeunload="stopPolling()">
    {{-- Header --}}
    <div
        class="fixed top-14 left-0 right-4 lg:left-56 backdrop-blur-md bg-white/80 border-b border-white/20 shadow-md z-40">
        <div class="flex items-center gap-4 px-6 py-4">
            <div class="relative">
                <div class="w-12 h-12 rounded-full bg-gradient-to-br from-blue-500 to-purple-600 p-0.5">
                    <flux:avatar src="{{ asset('img/user/lecturer-man.png') }}"
                        class="w-full h-full rounded-full border-2 border-white" />
                </div>
                <div
                    class="absolute -bottom-1 -right-1 w-4 h-4 bg-gradient-to-r from-green-400 to-emerald-500 rounded-full border-2 border-white shadow-sm">
                </div>
            </div>
            <div class="flex-1 min-w-0">
                <flux:heading class="truncate font-semibold text-gray-800">
                    {{ $dosenPembimbing ? $dosenPembimbing->nama : 'Dosen Pembimbing' }}
                </flux:heading>
                <div class="flex items-center gap-2">
                    <div class="w-2 h-2 bg-green-500 rounded-full"></div>
                    <flux:subheading class="text-green-600 font-medium">
                        Dosen Pembimbing
                    </flux:subheading>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <div class="flex items-center gap-1">
                    <div class="w-2 h-2 bg-green-500 rounded-full animate-pulse"></div>
                    <span class="text-xs text-green-600 font-medium">Online</span>
                </div>
                <button @click="requestNotificationPermission()"
                    class="p-1 text-gray-400 hover:text-gray-600 transition-colors" title="Aktifkan notifikasi">
                    <flux:icon.bell class="w-4 h-4" />
                </button>
            </div>
        </div>
    </div>

    {{-- Chat Container --}}
    <div id="chat-container"
        class="fixed top-[140px] bottom-20 left-0 right-4 lg:left-56 overflow-y-auto z-30 scrollbar-thin scrollbar-thumb-gray-300 scrollbar-track-transparent">
        <div class="px-6 py-8 space-y-6 min-h-full">
            @if (!$isAuthorized)
                {{-- Unauthorized state --}}
                <div class="flex flex-col items-center justify-center h-full text-center py-12">
                    <div
                        class="w-16 h-16 bg-gradient-to-br from-red-100 to-red-200 rounded-full flex items-center justify-center mb-4">
                        <flux:icon.exclamation-triangle class="w-8 h-8 text-red-500" />
                    </div>
                    <flux:heading class="text-red-600 mb-2">Akses Ditolak</flux:heading>
                    <flux:subheading class="text-red-500">
                        Anda tidak memiliki akses ke chat ini
                    </flux:subheading>
                </div>
            @elseif (empty($messages))
                {{-- Empty state --}}
                <div class="flex flex-col items-center justify-center h-full text-center py-12">
                    <div
                        class="w-16 h-16 bg-gradient-to-br from-blue-100 to-purple-100 rounded-full flex items-center justify-center mb-4">
                        <flux:icon.chat-bubble-left-right class="w-8 h-8 text-blue-500" />
                    </div>
                    <flux:heading class="text-gray-600 mb-2">Belum ada percakapan</flux:heading>
                    <flux:subheading class="text-gray-500">
                        Mulai percakapan dengan dosen pembimbing Anda
                    </flux:subheading>
                </div>
            @else
                @php $currentDate = null; @endphp
                @foreach ($messages as $message)
                    {{-- Date Separator --}}
                    @if ($currentDate !== $message['created_date'])
                        @php $currentDate = $message['created_date']; @endphp
                        <div class="flex justify-center my-8">
                            <flux:badge variant="outline"
                                class="bg-white/90 backdrop-blur-sm border-white/20 shadow-md px-4 py-2 font-medium text-gray-700">
                                {{ \Carbon\Carbon::parse($message['created_date'])->format('d M Y') }}
                            </flux:badge>
                        </div>
                    @endif

                    {{-- Message --}}
                    <div class="message-item" x-data="{ isNew: false }" x-init="if ({{ $message['id'] }} > {{ $lastMessageId - 1 }} && !{{ $message['is_mine'] ? 'true' : 'false' }}) {
                        isNew = true;
                        setTimeout(() => isNew = false, 3000);
                    }">
                        @if ($message['is_mine'])
                            {{-- Outgoing Message (Mahasiswa) --}}
                            <div class="flex justify-end group">
                                <div class="max-w-[75%] sm:max-w-[65%]">
                                    <div class="bg-gradient-to-r from-blue-500 to-blue-600 text-white rounded-3xl rounded-tr-md p-4 shadow-md"
                                        :class="{ 'animate-pulse': isNew }">
                                        <p class="text-sm leading-relaxed">{{ $message['message'] }}</p>
                                    </div>
                                    <div class="flex justify-end items-center gap-2 mt-2 px-3">
                                        <span
                                            class="text-xs text-gray-500 font-medium">{{ $message['created_at'] }}</span>
                                    </div>
                                </div>
                            </div>
                        @else
                            {{-- Incoming Message (Dosen) --}}
                            <div class="flex justify-start group">
                                <div class="max-w-[75%] sm:max-w-[65%]">
                                    <div class="bg-white/90 backdrop-blur-sm border border-white/20 rounded-3xl rounded-tl-md p-4 shadow-md"
                                        :class="{ 'ring-2 ring-blue-400 ring-opacity-75': isNew }">
                                        <p class="text-sm text-gray-800 leading-relaxed">{{ $message['message'] }}</p>
                                    </div>
                                    <div class="flex justify-start items-center gap-2 mt-2 px-3">
                                        <span
                                            class="text-xs text-gray-500 font-medium">{{ $message['sender_name'] }}</span>
                                        <span
                                            class="text-xs text-gray-500 font-medium">{{ $message['created_at'] }}</span>
                                    </div>
                                </div>
                            </div>
                        @endif
                    </div>
                @endforeach
            @endif
        </div>
    </div>

    {{-- Input Area --}}
    @if ($isAuthorized)
        <div
            class="fixed bottom-0 left-0 right-4 lg:left-56 backdrop-blur-md bg-white/90 border-t border-white/20 shadow-md z-40">
            <div class="flex items-end gap-3 px-6 py-4">
                {{-- Message Input --}}
                <div class="flex-1 relative">
                    <flux:textarea wire:model="messageText" @keydown="handleKeydown($event)"
                        placeholder="Ketik pesan Anda..." rows="1"
                        class="w-full resize-none min-h-[48px] max-h-32 rounded-2xl border-0 bg-white/80 backdrop-blur-sm shadow-inner focus:bg-white/90 focus:ring-2 focus:ring-blue-500/50 focus:border-transparent transition-all duration-200 px-4 py-3 text-sm placeholder-gray-500"
                        id="message-input" x-ref="messageInput" x-init="$el.addEventListener('input', function() {
                            this.style.height = 'auto';
                            this.style.height = Math.min(this.scrollHeight, 128) + 'px';
                        });" />
                </div>

                {{-- Send Button --}}
                <div class="flex-shrink-0">
                    <flux:button wire:click="sendMessage" variant="primary" icon="paper-airplane"
                        wire:loading.attr="disabled"
                        class="w-12 h-12 rounded-full !bg-gradient-to-r !from-blue-500 !to-blue-600 hover:!from-blue-600 hover:!to-blue-700 !text-white !shadow-md hover:!shadow-md !transition-all !duration-200" />
                </div>
            </div>
        </div>
    @endif

    {{-- Loading Indicator --}}
    <div wire:loading wire:target="sendMessage" class="fixed bottom-24 left-1/2 transform -translate-x-1/2 z-50">
        <div class="bg-blue-500 text-white px-4 py-2 rounded-full shadow-md flex items-center gap-2">
            <div class="animate-spin rounded-full h-4 w-4 border-2 border-white border-t-transparent"></div>
            <span class="text-sm">Mengirim...</span>
        </div>
    </div>

    {{-- Success Message --}}
    @if (session('success'))
        <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 3000)"
            x-transition:leave="transition ease-in duration-300" x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            class="fixed top-20 right-4 z-50 bg-green-500 text-white px-4 py-2 rounded-md shadow-md">
            {{ session('success') }}
        </div>
    @endif

    {{-- Error Message --}}
    @if (session('error'))
        <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 5000)"
            x-transition:leave="transition ease-in duration-300" x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            class="fixed top-20 right-4 z-50 bg-red-500 text-white px-4 py-2 rounded-md shadow-md">
            {{ session('error') }}
        </div>
    @endif
</div>
