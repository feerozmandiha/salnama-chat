(function($) {
    'use strict';

    class SalnamaChatAdmin {
        constructor() {
            this.currentConversation = null;
            this.isPolling = false;
            this.pollInterval = null;
            this.lastMessageId = 0;
            
            this.init();
        }

        init() {
            this.bindEvents();
            console.log('✅ Salnama Chat Admin initialized (Polling Mode)');
        }

        bindEvents() {
            // مدیریت مودال چت
            $(document).on('click', '.start-chat, .view-conversation', this.openChatModal.bind(this));
            $(document).on('click', '.close-chat-modal', this.closeChatModal.bind(this));
            
            // ارسال پیام
            $(document).on('click', '#send-chat-message', this.sendMessage.bind(this));
            $(document).on('keypress', '#chat-message-input', this.handleMessageKeypress.bind(this));
            
            // مدیریت مکالمات
            $(document).on('click', '.assign-conversation', this.assignConversation.bind(this));
            $(document).on('click', '.close-conversation', this.closeConversation.bind(this));
            
            // رفریش آمار
            $(document).on('click', '.refresh-stats', this.refreshStats.bind(this));
            
            // جلوگیری از بسته شدن مودال با کلیک خارج
            $(document).on('click', '.chat-modal-overlay', this.closeChatModal.bind(this));
        }

        openChatModal(e) {
            e.preventDefault();
            
            const conversationId = $(e.target).closest('[data-conversation-id]').data('conversation-id') || $(e.target).data('conversation-id');
            this.currentConversation = conversationId;
            
            this.showChatModal();
            this.loadConversationMessages(conversationId);
            
            // شروع polling برای این مکالمه
            this.startPolling(conversationId);
        }

        showChatModal() {
            $('body').append('<div class="chat-modal-overlay"></div>');
            $('#chat-modal').show();
            
            // تنظیم focus روی input
            setTimeout(() => {
                $('#chat-message-input').focus();
            }, 100);
        }

        closeChatModal() {
            // توقف polling
            this.stopPolling();
            
            $('#chat-modal').hide();
            $('.chat-modal-overlay').remove();
            this.currentConversation = null;
        }

        loadConversationMessages(conversationId) {
            $.ajax({
                url: salnamaChatAdmin.ajax_url,
                type: 'POST',
                data: {
                    action: 'salnama_chat_admin_get_messages',
                    conversation_id: conversationId,
                    nonce: salnamaChatAdmin.nonce
                },
                success: (response) => {
                    if (response.success) {
                        this.renderMessages(response.data.messages);
                        this.updateChatModalTitle(response.data.conversation);
                        
                        // آخرین messageId را ذخیره کن
                        if (response.data.messages.length > 0) {
                            this.lastMessageId = Math.max(...response.data.messages.map(msg => msg.message_id));
                        }
                    } else {
                        this.showError(response.data.message);
                    }
                },
                error: (xhr, status, error) => {
                    this.showError('خطا در بارگذاری پیام‌ها');
                }
            });
        }

        renderMessages(messages) {
            const $chatWindow = $('#chat-window');
            $chatWindow.empty();
            
            messages.forEach(message => {
                const messageHtml = this.getMessageHtml(message);
                $chatWindow.append(messageHtml);
            });
            
            // اسکرول به پایین
            $chatWindow.scrollTop($chatWindow[0].scrollHeight);
        }

        getMessageHtml(message) {
            const isOperator = message.sender_type === 'operator';
            const senderName = isOperator ? 
                salnamaChatAdmin.current_user.name : 
                (message.customer_name || 'مشتری');
            
            const time = this.formatTime(message.sent_at);
            
            return `
                <div class="message ${isOperator ? 'message-outgoing' : 'message-incoming'}">
                    <div class="message-avatar">
                        ${isOperator ? 
                            `<img src="${salmamaChatAdmin.current_user.avatar}" alt="${senderName}" width="32" height="32">` :
                            `<div class="customer-avatar">${senderName.charAt(0)}</div>`
                        }
                    </div>
                    <div class="message-content">
                        <div class="message-header">
                            <strong>${senderName}</strong>
                            <span class="message-time">${time}</span>
                        </div>
                        <div class="message-body">
                            ${this.formatMessageContent(message)}
                        </div>
                    </div>
                </div>
            `;
        }

        formatMessageContent(message) {
            if (message.message_type === 'image') {
                return `<img src="${message.attachment_url}" alt="تصویر پیوست" class="chat-attachment-image">`;
            } else if (message.message_type === 'file') {
                return `
                    <div class="chat-attachment-file">
                        <a href="${message.attachment_url}" target="_blank" download>
                            <span class="dashicons dashicons-media-document"></span>
                            ${message.attachment_name}
                        </a>
                    </div>
                `;
            } else {
                return (message.message_content || '').replace(/\n/g, '<br>');
            }
        }

        formatTime(timestamp) {
            if (!timestamp) return '';
            const date = new Date(timestamp * 1000 || timestamp);
            return date.toLocaleTimeString('fa-IR', {
                hour: '2-digit',
                minute: '2-digit'
            });
        }

        sendMessage() {
            const messageContent = $('#chat-message-input').val().trim();
            
            if (!messageContent || !this.currentConversation) {
                return;
            }

            $.ajax({
                url: salnamaChatAdmin.ajax_url,
                type: 'POST',
                data: {
                    action: 'salnama_chat_admin_send_message',
                    conversation_id: this.currentConversation,
                    message: messageContent,
                    nonce: salnamaChatAdmin.nonce
                },
                success: (response) => {
                    if (response.success) {
                        $('#chat-message-input').val('');
                        this.handleNewMessage(response.data.message);
                    } else {
                        this.showError(response.data.message);
                    }
                },
                error: (xhr, status, error) => {
                    this.showError('خطا در ارسال پیام');
                }
            });
        }

        handleNewMessage(message) {
            const messageHtml = this.getMessageHtml(message);
            $('#chat-window').append(messageHtml);
            
            // اسکرول به پایین
            $('#chat-window').scrollTop($('#chat-window')[0].scrollHeight);
            
            // به روزرسانی lastMessageId
            this.lastMessageId = Math.max(this.lastMessageId, message.message_id);
        }

        handleMessageKeypress(e) {
            if (e.which === 13 && !e.shiftKey) {
                e.preventDefault();
                this.sendMessage();
            }
        }

        // Polling Methods
        startPolling(conversationId) {
            this.stopPolling();
            
            this.pollInterval = setInterval(() => {
                this.checkNewMessages(conversationId);
            }, 3000); // هر 3 ثانیه
            
            this.isPolling = true;
            console.log('📡 Admin polling started for conversation:', conversationId);
        }

        stopPolling() {
            if (this.pollInterval) {
                clearInterval(this.pollInterval);
                this.pollInterval = null;
                this.isPolling = false;
                console.log('🛑 Admin polling stopped');
            }
        }

        checkNewMessages(conversationId) {
            if (!conversationId || !this.isPolling) return;

            $.ajax({
                url: salnamaChatAdmin.ajax_url,
                type: 'POST',
                data: {
                    action: 'salnama_chat_poll_messages',
                    conversation_id: conversationId,
                    last_message_id: this.lastMessageId,
                    nonce: salnamaChatAdmin.nonce
                },
                success: (response) => {
                    if (response.success && response.data.messages.length > 0) {
                        console.log('📨 New messages found:', response.data.messages.length);
                        
                        response.data.messages.forEach(message => {
                            // فقط پیام‌های مشتری را نمایش بده (پیام‌های اپراتور را خودمان فرستادیم)
                            if (message.sender_type === 'customer') {
                                this.handleNewMessage(message);
                            }
                            this.lastMessageId = Math.max(this.lastMessageId, message.message_id);
                        });
                    }
                },
                error: (xhr, status, error) => {
                    console.error('❌ Admin polling error:', error);
                }
            });
        }

        assignConversation(e) {
            e.preventDefault();
            
            const $button = $(e.target);
            const conversationId = $button.data('conversation-id');
            
            $.ajax({
                url: salnamaChatAdmin.ajax_url,
                type: 'POST',
                data: {
                    action: 'salnama_chat_admin_assign_conversation',
                    conversation_id: conversationId,
                    nonce: salnamaChatAdmin.nonce
                },
                beforeSend: () => {
                    $button.prop('disabled', true).text('در حال اختصاص...');
                },
                success: (response) => {
                    if (response.success) {
                        this.showSuccess('مکالمه با موفقیت به شما اختصاص داده شد');
                        location.reload(); // رفریش صفحه
                    } else {
                        this.showError(response.data.message);
                    }
                },
                error: (xhr, status, error) => {
                    this.showError('خطا در اختصاص مکالمه');
                },
                complete: () => {
                    $button.prop('disabled', false).text('اختصاص به من');
                }
            });
        }

        closeConversation(e) {
            e.preventDefault();
            
            if (!confirm('آیا از بستن این مکالمه اطمینان دارید؟')) {
                return;
            }
            
            const $button = $(e.target);
            const conversationId = $button.data('conversation-id');
            const resolutionNotes = prompt('لطفاً یادداشت حل مسئله را وارد کنید (اختیاری):');
            
            $.ajax({
                url: salnamaChatAdmin.ajax_url,
                type: 'POST',
                data: {
                    action: 'salnama_chat_admin_close_conversation',
                    conversation_id: conversationId,
                    resolution_notes: resolutionNotes || '',
                    nonce: salnamaChatAdmin.nonce
                },
                beforeSend: () => {
                    $button.prop('disabled', true).text('در حال بستن...');
                },
                success: (response) => {
                    if (response.success) {
                        this.showSuccess('مکالمه با موفقیت بسته شد');
                        location.reload();
                    } else {
                        this.showError(response.data.message);
                    }
                },
                error: (xhr, status, error) => {
                    this.showError('خطا در بستن مکالمه');
                },
                complete: () => {
                    $button.prop('disabled', false).text('بستن');
                }
            });
        }

        refreshStats() {
            $.ajax({
                url: salnamaChatAdmin.ajax_url,
                type: 'POST',
                data: {
                    action: 'salnama_chat_admin_get_stats',
                    nonce: salnamaChatAdmin.nonce
                },
                success: (response) => {
                    if (response.success) {
                        this.showSuccess('آمار به روز شد');
                        location.reload();
                    }
                }
            });
        }

        updateChatModalTitle(conversation) {
            const customerName = conversation.customer_name || 'مشتری ناشناس';
            $('#chat-modal-title').text(`چت با ${customerName}`);
        }

        showSuccess(message) {
            this.showNotice(message, 'success');
        }

        showError(message) {
            this.showNotice(message, 'error');
        }

        showNotice(message, type = 'info') {
            const notice = $(`
                <div class="notice notice-${type} is-dismissible">
                    <p>${message}</p>
                </div>
            `);
            
            $('.wrap').first().prepend(notice);
            
            setTimeout(() => {
                notice.fadeOut(() => notice.remove());
            }, 5000);
        }
    }

    // راه‌اندازی زمانی که DOM آماده است
    $(document).ready(() => {
        window.salnamaChatAdminApp = new SalnamaChatAdmin();
    });

})(jQuery);