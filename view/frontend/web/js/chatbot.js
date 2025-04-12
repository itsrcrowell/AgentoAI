define([
    'jquery',
    'ko',
    'uiComponent',
    'mage/storage',
    'mage/translate',
    'mage/url'
], function ($, ko, Component, storage, $t, urlBuilder) {
    'use strict';

    return Component.extend({
        defaults: {
            template: 'Genaker_MagentoMcpAi/chatbot',
            logoUrl: '',
            title: 'Virtual Assistant',
            welcomeMessage: 'Hey there 👋 I\'m here to help you find what you need.',
            buttonText: 'Try our virtual assistant',
            suggestedQueries: [],
            storeContext: {},
            apiUrl: ''
        },

        // Observables
        isChatOpen: ko.observable(false),
        isLoading: ko.observable(false),
        messages: ko.observableArray([]),
        userInput: ko.observable(''),
        showSuggestions: ko.observable(true),
        chatHistory: ko.observableArray([]),

        // Initialize
        initialize: function () {
            this._super();
            
            // Load chat history from localStorage if available
            var savedHistory = localStorage.getItem('chatbot_history');
            if (savedHistory) {
                try {
                    this.chatHistory(JSON.parse(savedHistory));
                } catch (e) {
                    console.error('Error loading chat history:', e);
                    this.chatHistory([]);
                }
            }

            // Check if chatbot should auto-open based on URL parameters
            var urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get('open_chat') === 'true') {
                this.toggleChat();
            }

            return this;
        },

        // Get template to use
        getTemplate: function () {
            return this.template;
        },

        // Toggle chat window
        toggleChat: function () {
            this.isChatOpen(!this.isChatOpen());
            if (this.isChatOpen() && this.messages().length === 0) {
                // Show welcome message when first opening
                this.showSuggestions(true);
            }

            // Scroll to bottom when opening
            if (this.isChatOpen()) {
                setTimeout(function () {
                    this.scrollToBottom();
                }.bind(this), 100);
            }
        },

        // Send message
        sendMessage: function () {
            if (!this.userInput().trim() || this.isLoading()) {
                return;
            }

            var userMessage = this.userInput().trim();
            this.addMessage(userMessage, true);
            this.userInput('');
            this.showSuggestions(false);
            this.fetchResponse(userMessage);
        },

        // Send a suggested query
        sendSuggestedQuery: function (query) {
            this.userInput(query);
            this.sendMessage();
        },

        // Add message to chat
        addMessage: function (text, isUser) {
            this.messages.push({
                text: text,
                isUser: isUser
            });

            this.chatHistory.push({
                text: text,
                isUser: isUser,
                timestamp: new Date().toISOString()
            });

            // Save to localStorage (keep last 50 messages)
            localStorage.setItem('chatbot_history', JSON.stringify(this.chatHistory.slice(-50)));

            // Scroll to bottom
            setTimeout(function () {
                this.scrollToBottom();
            }.bind(this), 100);
        },

        // Scroll chat to bottom
        scrollToBottom: function () {
            var chatMessages = document.getElementById('chat-messages');
            if (chatMessages) {
                chatMessages.scrollTop = chatMessages.scrollHeight;
            }
        },

        // Format message (convert URLs to links)
        formatMessage: function (text) {
            var urlRegex = /(https?:\/\/[^\s]+)/g;
            return text.replace(urlRegex, function (url) {
                return '<a href="' + url + '" target="_blank" class="chatbot-link">' + url + '</a>';
            });
        },

        // Fetch AI response
        fetchResponse: function (query) {
            this.isLoading(true);

            // Get store context
            var storeContext = this.storeContext || {};
            var apiUrl = this.apiUrl || urlBuilder.build('magentomcpai/chat/query');

            storage.post(
                apiUrl,
                JSON.stringify({
                    query: query,
                    context: storeContext
                }),
                true,
                'application/json'
            ).done(function (response) {
                this.isLoading(false);
                if (response.success) {
                    this.addMessage(response.message);
                } else {
                    this.addMessage($t('Sorry, I encountered an error. Please try again later.'));
                }
            }.bind(this)).fail(function () {
                this.isLoading(false);
                this.addMessage($t('Sorry, I encountered an error. Please try again later.'));
            }.bind(this));
        }
    });
});
