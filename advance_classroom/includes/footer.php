    <!-- AI Assistant Widget -->
    <?php if (isLoggedIn()): ?>
    <div class="ai-assistant" id="aiAssistant">
        <button class="ai-toggle" id="aiToggle" title="Academic Helper">
            <span class="ai-icon">🤖</span>
            <span class="ai-pulse"></span>
        </button>
        <div class="ai-panel" id="aiPanel">
            <div class="ai-header">
                <span>🎓 Academic Helper</span>
                <button onclick="document.getElementById('aiPanel').classList.remove('active')" class="ai-close">×</button>
            </div>
            <div class="ai-body" id="aiBody">
                <div class="ai-message ai-bot">
                    <p>Hello! I'm your Academic Helper. Here are some things I can help with:</p>
                </div>
            </div>
            <div class="ai-actions" id="aiActions">
                <button onclick="aiTip('deadlines')">📅 Check Deadlines</button>
                <button onclick="aiTip('missing')">⚠️ Missing Work</button>
                <button onclick="aiTip('tips')">💡 Study Tips</button>
                <button onclick="aiTip('progress')">📊 My Progress</button>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Main JavaScript -->
    <script src="<?php echo BASE_URL; ?>/assets/js/app.js"></script>
</body>
</html>
