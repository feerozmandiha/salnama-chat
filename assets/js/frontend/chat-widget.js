(function($) {
    'use strict';

    class SalnamaChatWidget {
        constructor() {
            this.isOpen = false;
            this.currentConversation = null;
            this.lastMessageId = 0;
            this.pollInterval = null;
            
            this.init();
        }

        init() {
            this.bindEvents();
            this.renderWidget();
        }

        bindEvents() {
            // باز و بستن چت
            $(document).on('click', '.chat-toggle-button', this.toggleChat.bind(this));
            $(document).on('click', '.chat-close', this.closeChat.bind(this));
            
            // ارسال پیام
            $(document).on('click', '#chat-send', this.sendMessage.bind(this));
            $(document).on('keypress', '#chat-input', this.handleInputKeypress.bind(this));
            
            // تغییر سایز textarea
            $(document).on('input', '#chat-input', this.resizeTextarea.bind(this));
        }

        renderWidget() {
            // ویجت از قبل در HTML وجود دارد، فقط رویدادها را وصل می‌کنیم
            console.log('Salmama Chat Widget initialized');
        }

        toggleChat() {
            if (this.isOpen) {
                this.closeChat();
            } else {
                this.openChat();
            }
        }

        openChat() {
            this.isOpen = true;
            $('.chat-window').addClass('active');
            $('#chat-input').focus();
            
            // شروع polling برای پیام‌های جدید
            this.startPolling();
            
            // اگر مکالمه‌ای نداریم، ایجاد کن
            if (!this.currentConversation) {
                this.startNewConversation();
            }
        }

        closeChat() {
            this.isOpen = false;
            $('.chat-window').removeClass('active');
            
            // توقف polling
            this.stopPolling();
        }

        startNewConversation() {
            $.ajax({
                url: salnamaChat.ajax_url,
                type: 'POST',
                data: {
                    action: 'salnama_chat_start_conversation',
                    subject: 'مکالمه جدید',
                    message: 'سلام! می‌خواهم اطلاعات بیشتری دریافت کنم.',
                    nonce: salnamaChat.nonce
                },
                success: (response) => {
                    if (response.success) {
                        this.currentConversation = response.data.conversation.conversation_id;
                        this.addMessage({
                            sender_type: 'customer',
                            message_content: 'سلام! می‌خواهم اطلاعات بیشتری دریافت کنم.',
                            sent_at: new Date().toISOString()
                        });
                    } else {
                        this.showError('خطا در شروع مکالمه');
                    }
                },
                error: () => {
                    this.showError('خطا در ارتباط با سرور');
                }
            });
        }

        sendMessage() {
            const messageContent = $('#chat-input').val().trim();
            
            if (!messageContent) {
                return;
            }

            if (!this.currentConversation) {
                this.showError('لطفاً کمی صبر کنید...');
                return;
            }

            // نمایش پیام کاربر بلافاصله
            this.addMessage({
                sender_type: 'customer',
                message_content: messageContent,
                sent_at: new Date().toISOString()
            });

            // پاک کردن input
            $('#chat-input').val('');
            this.resizeTextarea();

            // ارسال به سرور
            $.ajax({
                url: salnamaChat.ajax_url,
                type: 'POST',
                data: {
                    action: 'salnama_chat_send_message',
                    conversation_id: this.currentConversation,
                    message: messageContent,
                    nonce: salnamaChat.nonce
                },
                success: (response) => {
                    if (!response.success) {
                        this.showError(response.data.message);
                    }
                },
                error: () => {
                    this.showError('خطا در ارسال پیام');
                }
            });
        }

        handleInputKeypress(e) {
            if (e.which === 13 && !e.shiftKey) {
                e.preventDefault();
                this.sendMessage();
            }
        }

        resizeTextarea() {
            const textarea = $('#chat-input')[0];
            textarea.style.height = 'auto';
            textarea.style.height = Math.min(textarea.scrollHeight, 120) + 'px';
        }

        startPolling() {
            // هر 3 ثانیه چک کن برای پیام‌های جدید
            this.pollInterval = setInterval(() => {
                this.checkNewMessages();
            }, 3000);
        }

        stopPolling() {
            if (this.pollInterval) {
                clearInterval(this.pollInterval);
                this.pollInterval = null;
            }
        }

        checkNewMessages() {
            if (!this.currentConversation) return;

            $.ajax({
                url: salnamaChat.ajax_url,
                type: 'POST',
                data: {
                    action: 'salnama_chat_get_messages',
                    conversation_id: this.currentConversation,
                    last_message_id: this.lastMessageId,
                    nonce: salnamaChat.nonce
                },
                success: (response) => {
                    if (response.success && response.data.messages.length > 0) {
                        response.data.messages.forEach(message => {
                            this.addMessage(message);
                            this.lastMessageId = Math.max(this.lastMessageId, message.message_id);
                        });
                    }
                }
            });
        }

        addMessage(message) {
            const isCustomer = message.sender_type === 'customer';
            const messageTime = this.formatTime(message.sent_at);
            
            const messageHtml = `
                <div class="message ${isCustomer ? 'message-customer' : 'message-operator'}">
                    <div class="message-content">
                        <div class="message-text">${this.escapeHtml(message.message_content)}</div>
                        <div class="message-time">${messageTime}</div>
                    </div>
                </div>
            `;
            
            $('#chat-messages').append(messageHtml);
            this.scrollToBottom();
        }

        formatTime(timestamp) {
            const date = new Date(timestamp);
            return date.toLocaleTimeString('fa-IR', {
                hour: '2-digit',
                minute: '2-digit'
            });
        }

        escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        scrollToBottom() {
            const chatBody = $('.chat-body');
            chatBody.scrollTop(chatBody[0].scrollHeight);
        }

        showError(message) {
            // نمایش خطا به کاربر
            console.error('Chat Error:', message);
        }
    }

    // راه‌اندازی وقتی DOM آماده است
    $(document).ready(() => {
        if ($('#salnama-chat-widget').length) {
            window.salnamaChatWidget = new SalnamaChatWidget();
        }
    });

})(jQuery);