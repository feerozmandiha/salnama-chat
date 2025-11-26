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
            
            // فقط ویجت را باز کن
            if ($('#chat-messages').children().length === 0) {
                this.addWelcomeMessage();
            }
            
            // polling را شروع کن (اگر مکالمه داریم)
            if (this.currentConversation) {
                this.startPolling();
            }
        }

        closeChat() {
            this.isOpen = false;
            $('.chat-window').removeClass('active');
            
            // توقف polling
            this.stopPolling();
        }

        startNewConversation() {
            // اگر در حال حاضر مکالمه‌ای در حال ایجاد است، صبر کن
            if (this.creatingConversation) {
                return;
            }
            
            this.creatingConversation = true;

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
                    this.creatingConversation = false;
                    
                    if (response.success) {
                        this.currentConversation = response.data.conversation.conversation_id;
                        this.lastMessageId = 0;
                        
                        // فقط پیام خوشامدگویی نمایش بده، نه پیام تکراری
                        this.addWelcomeMessage();
                        
                        // شروع polling برای پیام‌های جدید
                        this.startPolling();
                    } else {
                        this.showError(response.data.message || 'خطا در شروع مکالمه');
                    }
                },
                error: () => {
                    this.creatingConversation = false;
                    this.showError('خطا در ارتباط با سرور');
                }
            });
        }

        addWelcomeMessage() {
            const welcomeHtml = `
                <div class="message message-operator">
                    <div class="message-content">
                        <div class="message-text">سلام! به چت سالنمای نو خوش آمدید. چطور می‌تونم کمکتون کنم؟</div>
                        <div class="message-time">${this.formatTime(new Date().toISOString())}</div>
                    </div>
                </div>
            `;
            
            $('#chat-messages').html(welcomeHtml);
            this.scrollToBottom();
        }

        sendMessage() {
            const messageContent = $('#chat-input').val().trim();
            
            if (!messageContent) {
                return;
            }

            // نمایش پیام کاربر بلافاصله (تجربه کاربری بهتر)
            this.addMessage({
                sender_type: 'customer',
                message_content: messageContent,
                sent_at: new Date().toISOString()
            });

            // پاک کردن input
            $('#chat-input').val('');
            this.resizeTextarea();

            // اگر مکالمه نداریم، اول ایجاد کن سپس پیام را ارسال کن
            if (!this.currentConversation) {
                this.createConversationAndSendMessage(messageContent);
            } else {
                // اگر مکالمه داریم، مستقیماً پیام را ارسال کن
                this.sendMessageToServer(messageContent);
            }
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

        loadConversationMessages() {
    if (!this.currentConversation) return;
    
    $.ajax({
        url: salnamaChat.ajax_url,
        type: 'POST',
        data: {
            action: 'salnama_chat_get_messages',
            conversation_id: this.currentConversation,
            nonce: salnamaChat.nonce
        },
        success: (response) => {
            if (response.success) {
                $('#chat-messages').empty();
                
                if (response.data.messages.length > 0) {
                    response.data.messages.forEach(message => {
                        this.addMessage(message);
                        this.lastMessageId = Math.max(this.lastMessageId, message.message_id);
                    });
                } else {
                    this.addWelcomeMessage();
                }
            }
        }
    });
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

        createConversationAndSendMessage(messageContent) {
            $.ajax({
                url: salnamaChat.ajax_url,
                type: 'POST',
                data: {
                    action: 'salnama_chat_start_conversation',
                    subject: 'مکالمه جدید از وبسایت',
                    message: messageContent,
                    nonce: salnamaChat.nonce
                },
                success: (response) => {
                    if (response.success) {
                        this.currentConversation = response.data.conversation.conversation_id;
                        this.lastMessageId = 0;
                        this.startPolling();
                        console.log('مکالمه جدید ایجاد شد:', this.currentConversation);
                    } else {
                        this.showError('خطا در ایجاد مکالمه: ' + (response.data.message || 'خطای ناشناخته'));
                        // پیام کاربر را حذف کن چون ارسال نشد
                        this.removeLastMessage();
                    }
                },
                error: (xhr, status, error) => {
                    this.showError('خطا در ارتباط با سرور');
                    this.removeLastMessage();
                    console.error('AJAX Error:', error);
                }
            });
        }

        sendMessageToServer(messageContent) {
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
                        this.showError('خطا در ارسال پیام: ' + response.data.message);
                        this.removeLastMessage();
                    } else {
                        console.log('پیام با موفقیت ارسال شد');
                    }
                },
                error: (xhr, status, error) => {
                    this.showError('خطا در ارسال پیام به سرور');
                    this.removeLastMessage();
                    console.error('AJAX Error:', error);
                }
            });
        }

        removeLastMessage() {
            const messages = $('.message-customer');
            if (messages.length > 0) {
                messages.last().remove();
            }
        }
    }


    // راه‌اندازی وقتی DOM آماده است
    $(document).ready(() => {
        if ($('#salnama-chat-widget').length) {
            window.salnamaChatWidget = new SalnamaChatWidget();
        }
    });

})(jQuery);