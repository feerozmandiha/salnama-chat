(function($) {
    'use strict';

    class SalnamaChatAdmin {
        constructor() {
            this.socket = null;
            this.currentConversation = null;
            this.isConnected = false;
            
            this.init();
        }

        init() {
            this.bindEvents();
            this.connectWebSocket();
            this.loadOnlineOperators();
            this.loadRecentActivity();
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

        connectWebSocket() {
            if (!salnamaChatAdmin.websocket.enabled) {
                console.log('WebSocket is disabled');
                return;
            }

            try {
                this.socket = new WebSocket(salnamaChatAdmin.websocket.url);
                
                this.socket.onopen = () => {
                    console.log('WebSocket connected');
                    this.isConnected = true;
                    this.authenticate();
                };
                
                this.socket.onmessage = (event) => {
                    this.handleWebSocketMessage(event);
                };
                
                this.socket.onclose = () => {
                    console.log('WebSocket disconnected');
                    this.isConnected = false;
                    // تلاش برای اتصال مجدد پس از 5 ثانیه
                    setTimeout(() => this.connectWebSocket(), 5000);
                };
                
                this.socket.onerror = (error) => {
                    console.error('WebSocket error:', error);
                };
                
            } catch (error) {
                console.error('WebSocket connection failed:', error);
            }
        }

        authenticate() {
            if (!this.isConnected) return;

            const message = {
                action: 'authenticate',
                payload: {
                    type: 'operator',
                    token: salnamaChatAdmin.current_user.id.toString(),
                    conversation_id: this.currentConversation || 0
                }
            };
            
            this.socket.send(JSON.stringify(message));
        }

        handleWebSocketMessage(event) {
            try {
                const data = JSON.parse(event.data);
                
                switch (data.action) {
                    case 'new_message':
                        this.handleNewMessage(data.payload);
                        break;
                    case 'user_typing_start':
                        this.showTypingIndicator(data.payload);
                        break;
                    case 'user_typing_stop':
                        this.hideTypingIndicator(data.payload);
                        break;
                    case 'user_joined':
                        this.handleUserJoined(data.payload);
                        break;
                    case 'user_left':
                        this.handleUserLeft(data.payload);
                        break;
                    case 'user_disconnected':
                        this.handleUserDisconnected(data.payload);
                        break;
                    default:
                        console.log('Unknown WebSocket action:', data.action);
                }
            } catch (error) {
                console.error('Error parsing WebSocket message:', error);
            }
        }

        openChatModal(e) {
            e.preventDefault();
            
            const conversationId = $(e.target).data('conversation-id');
            this.currentConversation = conversationId;
            
            this.showChatModal();
            this.loadConversationMessages(conversationId);
            
            // عضویت در room مکالمه
            this.joinConversation(conversationId);
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
            if (this.currentConversation) {
                this.leaveConversation(this.currentConversation);
            }
            
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
                            `<img src="${salnamaChatAdmin.current_user.avatar}" alt="${senderName}">` :
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
                return message.message_content.replace(/\n/g, '<br>');
            }
        }

        formatTime(timestamp) {
            const date = new Date(timestamp * 1000);
            return date.toLocaleTimeString('fa-IR', {
                hour: '2-digit',
                minute: '2-digit'
            });
        }

        sendMessage() {
            const messageContent = $('#chat-message-input').val().trim();
            
            if (!messageContent) {
                return;
            }

            if (this.isConnected) {
                // ارسال از طریق WebSocket
                const message = {
                    action: 'send_message',
                    payload: {
                        message: messageContent
                    }
                };
                this.socket.send(JSON.stringify(message));
            } else {
                // ارسال از طریق AJAX
                this.sendMessageAjax(messageContent);
            }
            
            // پاک کردن input
            $('#chat-message-input').val('');
        }

        sendMessageAjax(messageContent) {
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
            // فقط اگر در مودال مربوطه هستیم، پیام را نمایش دهیم
            if (this.currentConversation && message.conversation_id == this.currentConversation) {
                const messageHtml = this.getMessageHtml(message);
                $('#chat-window').append(messageHtml);
                
                // اسکرول به پایین
                $('#chat-window').scrollTop($('#chat-window')[0].scrollHeight);
                
                // پخش صدا
                this.playNotificationSound();
            }
            
            // به روزرسانی لیست مکالمات
            this.refreshConversationsList();
        }

        handleMessageKeypress(e) {
            if (e.which === 13 && !e.shiftKey) {
                e.preventDefault();
                this.sendMessage();
            }
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
                        this.refreshConversationsList();
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
            
            if (!confirm(salnamaChatAdmin.i18n.confirm_close)) {
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
                        this.refreshConversationsList();
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

        joinConversation(conversationId) {
            if (!this.isConnected) return;
            
            const message = {
                action: 'join_conversation',
                payload: {
                    conversation_id: conversationId
                }
            };
            
            this.socket.send(JSON.stringify(message));
        }

        leaveConversation(conversationId) {
            if (!this.isConnected) return;
            
            const message = {
                action: 'leave_conversation',
                payload: {
                    conversation_id: conversationId
                }
            };
            
            this.socket.send(JSON.stringify(message));
        }

        loadOnlineOperators() {
            // این تابع می‌تواند از WebSocket یا AJAX برای دریافت لیست اپراتورها استفاده کند
            $('#online-operators-list').html(`
                <div class="operator-item">
                    <span class="operator-status"></span>
                    <div class="operator-avatar">
                        <img src="${salnamaChatAdmin.current_user.avatar}" alt="${salnamaChatAdmin.current_user.name}" width="32" height="32">
                    </div>
                    <div class="operator-info">
                        <strong>${salnamaChatAdmin.current_user.name}</strong>
                        <span class="operator-role">اپراتور</span>
                    </div>
                </div>
            `);
        }

        loadRecentActivity() {
            // بارگذاری فعالیت‌های اخیر
            $('#recent-activity-list').html(`
                <div class="activity-item">
                    <span>شما به سیستم وارد شدید</span>
                    <span class="activity-time">همین الان</span>
                </div>
            `);
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
                        this.updateStatsCards(response.data);
                    }
                }
            });
        }

        refreshConversationsList() {
            // رفریش لیست مکالمات
            window.location.reload();
        }

        showTypingIndicator(user) {
            // نمایش نشانگر تایپینگ
            const $typingIndicator = $('#typing-indicator');
            if (!$typingIndicator.length) {
                $('#chat-window').append(`
                    <div id="typing-indicator" class="typing-indicator">
                        <div class="typing-dots">
                            <span></span>
                            <span></span>
                            <span></span>
                        </div>
                        <span>${user.user_type === 'customer' ? 'مشتری' : 'اپراتور'} در حال نوشتن...</span>
                    </div>
                `);
            }
            
            $('#chat-window').scrollTop($('#chat-window')[0].scrollHeight);
        }

        hideTypingIndicator(user) {
            $('#typing-indicator').remove();
        }

        playNotificationSound() {
            // پخش صدای نوتیفیکیشن
            const audio = new Audio(salnamaChatAdmin.notification_sound);
            audio.play().catch(e => console.log('Audio play failed:', e));
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
            
            $('.wrap').prepend(notice);
            
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