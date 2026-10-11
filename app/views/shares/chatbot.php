<div id="chatbot-wrapper">
    <button id="chatbot-toggle"
        class="btn shadow-lg rounded-circle d-flex align-items-center justify-content-center"
        aria-label="Mở AI tư vấn">
        <i class="fas fa-robot text-white"></i>
        <span class="chatbot-toggle-label">AI</span>
    </button>

    <div id="chatbot-box" class="card d-none shadow-lg">
        <div class="card-header text-white d-flex justify-content-between align-items-center gap-2">
            <strong class="small d-flex align-items-center gap-2">
                <i class="fas fa-robot"></i>
                AI đa ngôn ngữ Aethera
            </strong>

            <div class="d-flex align-items-center gap-2">
                <select id="chatbot-language" class="chatbot-language" aria-label="Ngôn ngữ hỗ trợ">
                    <option value="vi">Việt Nam</option>
                    <option value="en">Hoa Kỳ</option>
                    <option value="ja">Nhật Bản</option>
                    <option value="ko">Hàn Quốc</option>
                    <option value="zh">Trung Quốc</option>
                    <option value="de">Đức</option>
                    <option value="fr">Pháp</option>
                    <option value="th">Thái Lan</option>
                    <option value="ar">Ả Rập</option>
                </select>

                <button class="btn-close btn-close-white" id="chatbot-close" aria-label="Đóng trợ lý"></button>
            </div>
        </div>

        <div id="chatbot-content" class="card-body">
            <div class="mb-2 text-start">
                <span class="bot-message" id="chatbot-welcome">
                    Xin chào! Tôi có thể giúp bạn tra cứu thông tin sản phẩm, giá bán, số lượng tồn kho, nguồn gốc hàng hóa, đơn hàng, chương trình khuyến mãi, mã giảm giá, Flash Sale và các sản phẩm nổi bật hiện có trên hệ thống. Bạn muốn tìm hiểu thông tin nào?
                </span>
            </div>
        </div>

        <div class="card-footer bg-white border-0">
            <div class="input-group">
                <input
                    type="text"
                    id="chatbot-input"
                    class="form-control border-0"
                    placeholder="Nhập tin nhắn..."
                >
                <button id="chatbot-send" class="btn" aria-label="Gửi tin nhắn">
                    <i class="fas fa-paper-plane text-white"></i>
                </button>
            </div>
        </div>

        <button id="chatbot-resize" type="button" aria-label="Kéo để đổi kích thước hộp hỗ trợ"></button>
    </div>
</div>

<style>
#chatbot-wrapper{
    position:fixed;
    right:20px;
    bottom:20px;
    z-index:9999;
}

#chatbot-toggle{
    width:60px;
    height:60px;
    position:relative;
    background:linear-gradient(135deg,#047857,#22c55e);
    border:2px solid #bbf7d0;
    color:#ffffff;
    transition:.25s;
    box-shadow:0 18px 40px rgba(16, 185, 129, .18);
    display:flex;
    align-items:center;
    justify-content:center;
}

#chatbot-toggle:hover{
    background:linear-gradient(135deg,#065f46,#16a34a);
    transform:scale(1.06);
    border-color:#16a34a;
}

#chatbot-toggle i,
#chatbot-send i,
#chatbot-box .card-header i{
    width:22px;
    height:22px;
    display:inline-flex;
    align-items:center;
    justify-content:center;
    line-height:1;
}

#chatbot-toggle i{
    font-size:24px;
    color:#ffffff;
}

.chatbot-toggle-label{
    position:absolute;
    right:-8px;
    top:-8px;
    min-width:28px;
    height:28px;
    display:inline-grid;
    place-items:center;
    padding:0 7px;
    border-radius:999px;
    background:#fbbf24;
    border:2px solid #ffffff;
    color:#064e3b;
    font-size:.72rem;
    font-weight:950;
    letter-spacing:0;
}

#chatbot-box{
    --chatbot-width:380px;
    --chatbot-height:470px;
    width:var(--chatbot-width);
    height:var(--chatbot-height);
    max-width:calc(100vw - 20px);
    max-height:calc(100vh - 110px);
    position:absolute;
    right:0;
    bottom:80px;
    border:1px solid #d9f5e4;
    border-radius:20px;
    overflow:hidden;
    background:#ffffff;
    flex-direction:column;
}

#chatbot-box:not(.d-none){
    display:flex;
}

#chatbot-box .card-header{
    background:linear-gradient(135deg,#22c55e,#86efac) !important;
    color:#064e3b;
    padding:13px 14px;
    border:none;
}

.chatbot-language{
    min-height:30px;
    border:1px solid rgba(255,255,255,.35);
    border-radius:999px;
    padding:0 10px;
    background:rgba(255,255,255,.14);
    color:#fff;
    font-size:.78rem;
    font-weight:900;
    outline:none;
}

.chatbot-language option{
    color:#064e3b;
    background:#fff;
}

#chatbot-content{
    background:
        radial-gradient(circle at top left, rgba(16,185,129,.16), transparent 34%),
        #f5fdf7;
    flex:1 1 auto;
    min-height:170px;
    overflow-y:auto;
    scroll-behavior:smooth;
}

#chatbot-box .card-footer{
    flex:0 0 auto;
}

#chatbot-content::-webkit-scrollbar{
    width:6px;
}

#chatbot-content::-webkit-scrollbar-thumb{
    background:#86efac;
    border-radius:20px;
}

.bot-message{
    background:white;
    border:1px solid #d1fae5;
    color:#064e3b;
    padding:10px 14px;
    border-radius:14px;
    display:inline-block;
    max-width:90%;
    line-height:1.5;
    word-break:break-word;
    white-space:pre-line;
    font-size:.92rem;
    box-shadow:0 8px 20px rgba(6,78,59,.05);
}

.user-message{
    background:linear-gradient(135deg,#008765,#14b981);
    color:#fff;
    border:0;
}

.chatbot-link{
    display:inline-flex;
    align-items:center;
    margin-top:4px;
    color:#047857;
    font-weight:800;
    text-decoration:none;
}

.chatbot-link:hover{
    color:#065f46;
    text-decoration:underline;
}

#chatbot-resize{
    position:absolute;
    top:0;
    left:0;
    width:22px;
    height:22px;
    border:0;
    padding:0;
    cursor:nwse-resize;
    background:
        linear-gradient(135deg, rgba(6,95,70,.65) 0 2px, transparent 2px 100%),
        linear-gradient(135deg, transparent 0 7px, rgba(6,95,70,.45) 7px 9px, transparent 9px 100%),
        linear-gradient(135deg, transparent 0 13px, rgba(6,95,70,.28) 13px 15px, transparent 15px 100%);
    opacity:.75;
    z-index:2;
}

#chatbot-resize:hover{
    opacity:1;
}

#chatbot-input{
    background:#effaf5;
    border-radius:12px;
    font-size:.95rem;
    min-height:45px;
    color:#0f172a;
}

#chatbot-input:focus{
    box-shadow:none;
}

#chatbot-send{
    background:linear-gradient(135deg,#22c55e,#4ade80);
    border:none;
    border-radius:12px;
    width:52px;
    display:inline-grid;
    place-items:center;
}

#chatbot-send:hover{
    background:linear-gradient(135deg,#15803d,#22c55e);
}

.typing-dots span{
    width:4px;
    height:4px;
    background:#065f46;
    display:inline-block;
    border-radius:50%;
    margin-right:2px;
    animation:typing 1s infinite;
}

@keyframes typing{
    0%,100%{ opacity:.3; }
    50%{ opacity:1; }
}

@media(max-width:576px){
    #chatbot-wrapper{
        right:10px;
        bottom:10px;
    }

    #chatbot-toggle{
        width:55px;
        height:55px;
    }

    #chatbot-box{
        width:calc(100vw - 20px);
        height:min(var(--chatbot-height), calc(100vh - 100px));
        bottom:70px;
    }
}
</style>

<script>
(() => {
    const chatEndpoint = <?= json_encode(rtrim(dirname($_SERVER['SCRIPT_NAME']), '/') . '/chat/sendMessage') ?>;
    const chatbotBox = document.getElementById('chatbot-box');
    const chatbotToggle = document.getElementById('chatbot-toggle');
    const chatbotClose = document.getElementById('chatbot-close');
    const chatbotContent = document.getElementById('chatbot-content');
    const chatbotInput = document.getElementById('chatbot-input');
    const chatbotSend = document.getElementById('chatbot-send');
    const chatbotLanguage = document.getElementById('chatbot-language');
    const chatbotWelcome = document.getElementById('chatbot-welcome');
    const chatbotResize = document.getElementById('chatbot-resize');
    const sizeStorageKey = 'aetheraChatbotSize';
    const chatbotText = {
        vi: {
            welcome: 'Xin chào! Tôi có thể giúp bạn tra cứu thông tin sản phẩm, giá bán, số lượng tồn kho, nguồn gốc hàng hóa, đơn hàng, chương trình khuyến mãi, mã giảm giá, Flash Sale và các sản phẩm nổi bật hiện có trên hệ thống. Bạn muốn tìm hiểu thông tin nào?',
            placeholder: 'Nhập tin nhắn...',
            loading: 'Đang đọc dữ liệu cửa hàng',
            error: 'Xin lỗi, tôi gặp lỗi khi xử lý dữ liệu.',
            server: 'Không kết nối được máy chủ hỗ trợ.'
        },
        en: {
            welcome: 'Hello, I read store data: products, prices, inventory, origins, orders, vouchers, flash sales, and featured products.',
            placeholder: 'Type a message...',
            loading: 'Reading store data',
            error: 'Sorry, I hit an error while processing data.',
            server: 'Cannot connect to the support server.'
        },
        ja: {
            welcome: 'こんにちは。商品、価格、在庫、産地、注文など店舗データを優先します。',
            placeholder: 'メッセージを入力...',
            loading: '店舗データを確認しています',
            error: 'データ処理中にエラーが発生しました。',
            server: 'サポートサーバーに接続できません。'
        },
        ko: {
            welcome: '안녕하세요. 상품, 가격, 재고, 원산지, 주문 등 매장 데이터를 우선 사용합니다.',
            placeholder: '메시지 입력...',
            loading: '매장 데이터를 확인하고 있습니다',
            error: '데이터 처리 중 오류가 발생했습니다.',
            server: '지원 서버에 연결할 수 없습니다.'
        },
        zh: {
            welcome: '你好，我会优先使用店铺数据：商品、价格、库存、产地和订单。',
            placeholder: '输入消息...',
            loading: '正在读取店铺数据',
            error: '处理数据时出错。',
            server: '无法连接支持服务器。'
        },
        de: {
            welcome: 'Hallo, ich nutze zuerst die Shopdaten: Produkte, Preise, Bestand, Herkunft und Bestellungen.',
            placeholder: 'Nachricht eingeben...',
            loading: 'Shopdaten werden gelesen',
            error: 'Beim Verarbeiten der Daten ist ein Fehler aufgetreten.',
            server: 'Der Support-Server ist nicht erreichbar.'
        },
        fr: {
            welcome: 'Bonjour, je consulte d’abord les données du magasin : produits, prix, stock, origine et commandes.',
            placeholder: 'Saisir un message...',
            loading: 'Lecture des données du magasin',
            error: 'Une erreur est survenue pendant le traitement des données.',
            server: 'Impossible de joindre le serveur d’assistance.'
        },
        th: {
            welcome: 'สวัสดี ฉันจะใช้ข้อมูลร้านก่อน: สินค้า ราคา สต็อก แหล่งที่มา และคำสั่งซื้อ',
            placeholder: 'พิมพ์ข้อความ...',
            loading: 'กำลังอ่านข้อมูลร้านค้า',
            error: 'เกิดข้อผิดพลาดระหว่างประมวลผลข้อมูล',
            server: 'ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์ช่วยเหลือได้'
        },
        ar: {
            welcome: 'مرحباً، أستخدم بيانات المتجر أولاً: المنتجات والأسعار والمخزون والمنشأ والطلبات.',
            placeholder: 'اكتب رسالة...',
            loading: 'جاري قراءة بيانات المتجر',
            error: 'حدث خطأ أثناء معالجة البيانات.',
            server: 'تعذر الاتصال بخادم الدعم.'
        }
    };

    function currentText(){
        return chatbotText[chatbotLanguage.value] || chatbotText.vi;
    }

    function applyLanguageText(){
        const text = currentText();
        chatbotInput.placeholder = text.placeholder;
        if(chatbotWelcome && chatbotContent.children.length === 1){
            chatbotWelcome.textContent = text.welcome;
        }
    }

    function escapeHtml(value){
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function linkifyInternalMessage(value){
        return escapeHtml(value)
            .replace(
                /(^|\s)(\/webbanhang\/[^\s<]+)/g,
                (match, prefix, url) => `${prefix}<a class="chatbot-link" href="${url}">${url}</a>`
            )
            .replace(
                /(^|\s)(https?:\/\/[^\s<]+)/g,
                (match, prefix, url) => `${prefix}<a class="chatbot-link" href="${url}" target="_blank" rel="noopener noreferrer">${url}</a>`
            );
    }

    function clamp(value, min, max){
        return Math.max(min, Math.min(max, value));
    }

    function applyChatbotSize(width, height){
        const maxWidth = Math.max(320, window.innerWidth - 20);
        const maxHeight = Math.max(360, window.innerHeight - 110);
        const nextWidth = clamp(width, 320, maxWidth);
        const nextHeight = clamp(height, 390, maxHeight);
        chatbotBox.style.setProperty('--chatbot-width', `${nextWidth}px`);
        chatbotBox.style.setProperty('--chatbot-height', `${nextHeight}px`);
        return { width: nextWidth, height: nextHeight };
    }

    function loadChatbotSize(){
        try {
            const saved = JSON.parse(localStorage.getItem(sizeStorageKey) || '{}');
            if(saved.width && saved.height){
                applyChatbotSize(Number(saved.width), Number(saved.height));
            }
        } catch (e) {
            localStorage.removeItem(sizeStorageKey);
        }
    }

    function saveChatbotSize(width, height){
        localStorage.setItem(sizeStorageKey, JSON.stringify({ width, height }));
    }

    function updateScroll(){
        chatbotContent.scrollTop = chatbotContent.scrollHeight;
    }

    function appendMessage(message, align = 'start', extraClass = ''){
        const wrapper = document.createElement('div');
        wrapper.className = `mb-2 text-${align}`;
        const content = extraClass.includes('user-message') ? escapeHtml(message) : linkifyInternalMessage(message);
        wrapper.innerHTML = `<span class="bot-message ${extraClass}">${content}</span>`;
        chatbotContent.appendChild(wrapper);
        updateScroll();
        return wrapper;
    }

    function toggleChatbox(){
        chatbotBox.classList.toggle('d-none');
        if(!chatbotBox.classList.contains('d-none')){
            updateScroll();
            chatbotInput.focus();
        }
    }

    chatbotToggle.addEventListener('click', toggleChatbox);
    chatbotClose.addEventListener('click', toggleChatbox);
    loadChatbotSize();

    chatbotResize.addEventListener('pointerdown', function(e){
        e.preventDefault();
        chatbotResize.setPointerCapture(e.pointerId);
        const startX = e.clientX;
        const startY = e.clientY;
        const startRect = chatbotBox.getBoundingClientRect();

        function onMove(moveEvent){
            const next = applyChatbotSize(
                startRect.width - (moveEvent.clientX - startX),
                startRect.height - (moveEvent.clientY - startY)
            );
            updateScroll();
            return next;
        }

        function onUp(upEvent){
            const finalSize = onMove(upEvent);
            saveChatbotSize(finalSize.width, finalSize.height);
            chatbotResize.releasePointerCapture(upEvent.pointerId);
            chatbotResize.removeEventListener('pointermove', onMove);
            chatbotResize.removeEventListener('pointerup', onUp);
            chatbotResize.removeEventListener('pointercancel', onUp);
        }

        chatbotResize.addEventListener('pointermove', onMove);
        chatbotResize.addEventListener('pointerup', onUp);
        chatbotResize.addEventListener('pointercancel', onUp);
    });

    window.addEventListener('resize', function(){
        const rect = chatbotBox.getBoundingClientRect();
        const next = applyChatbotSize(rect.width, rect.height);
        saveChatbotSize(next.width, next.height);
    });

    function sendMessage(){
        const userMsg = chatbotInput.value.trim();
        const language = chatbotLanguage.value || 'vi';

        if(userMsg === '') return;

        appendMessage(userMsg, 'end', 'user-message');
        chatbotInput.value = '';

        const loading = document.createElement('div');
        loading.className = 'mb-2 text-start';
        loading.innerHTML = `
                <span class="bot-message text-muted small">
                    ${escapeHtml(currentText().loading)}
                    <span class="typing-dots">
                        <span></span><span></span><span></span>
                    </span>
                </span>
        `;
        chatbotContent.appendChild(loading);

        updateScroll();

        const formData = new URLSearchParams();
        formData.set('message', userMsg);
        formData.set('language', language);

        fetch(chatEndpoint, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
            body: formData.toString()
        })
        .then((response) => response.ok ? response.json() : Promise.reject(response))
        .then((response) => {
            loading.remove();
            appendMessage(response.reply || currentText().error);
        })
        .catch(() => {
            loading.remove();
            appendMessage(currentText().server, 'start', 'text-danger');
        });
    }

    chatbotSend.addEventListener('click', sendMessage);
    chatbotLanguage.addEventListener('change', applyLanguageText);
    applyLanguageText();

    chatbotInput.addEventListener('keydown', function(e){
        if(e.key === 'Enter'){
            e.preventDefault();
            sendMessage();
        }
    });
})();
</script>
