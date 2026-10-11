<?php

class ChatbotModel
{
    private PDO $conn;

    private array $stopWords = [
        'toi', 'minh', 'ban', 'anh', 'chi', 'em',
        'can', 'muon', 'hoi', 've',

        'la', 'co', 'khong', 'gi', 'nao',
        'nhu', 'the', 'hay', 'vui', 'long',

        'cho', 'xin', 'chao', 'duoc',
        'nhe', 'nha', 'a', 'oi',

        'i', 'me', 'my', 'you', 'your',
        'please', 'help', 'about',
        'what', 'how', 'when',
        'where', 'is', 'are',
        'the', 'a', 'an',
        'to', 'for',

        'bonjour', 'salut', 'merci',
        'hallo', 'danke', 'bitte',
        'สวัสดี',
        'مرحبا', 'شكرا',

        'cua',
        'hang',
        'shop',
        'admin',
        'website',
        'web',
        'online',
        'thong',
        'tin',
    ];

    public function __construct(PDO $db)
    {
        $this->conn = $db;

        $this->ensureSchema();
    }

    public function ensureSchema(): void
    {
        if (!$this->tableExists('chatbot')) {
            $this->conn->exec("
                CREATE TABLE chatbot (
                    id INT NOT NULL AUTO_INCREMENT,
                    question VARCHAR(255) DEFAULT NULL,
                    answer TEXT,
                    language VARCHAR(10) DEFAULT 'vi',
                    keywords TEXT NULL,
                    category VARCHAR(80) DEFAULT NULL,
                    priority INT DEFAULT 0,
                    is_active TINYINT DEFAULT 1,
                    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP
                    ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (id)
                ) ENGINE=InnoDB
                DEFAULT CHARSET=utf8mb4
                COLLATE=utf8mb4_unicode_ci
            ");
        } else {
            $this->addColumn(
                'chatbot',
                'language',
                "VARCHAR(10) DEFAULT 'vi'"
            );

            $this->addColumn(
                'chatbot',
                'keywords',
                'TEXT NULL'
            );

            $this->addColumn(
                'chatbot',
                'category',
                'VARCHAR(80) DEFAULT NULL'
            );

            $this->addColumn(
                'chatbot',
                'priority',
                'INT DEFAULT 0'
            );

            $this->addColumn(
                'chatbot',
                'is_active',
                'TINYINT DEFAULT 1'
            );

            $this->addColumn(
                'chatbot',
                'created_at',
                'TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP'
            );

            $this->addColumn(
                'chatbot',
                'updated_at',
                'TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP
                ON UPDATE CURRENT_TIMESTAMP'
            );
        }

        if (!$this->tableExists('chatbot_training_log')) {
            $this->conn->exec("
                CREATE TABLE chatbot_training_log (
                    id INT NOT NULL AUTO_INCREMENT,
                    message TEXT NOT NULL,
                    language VARCHAR(10) DEFAULT 'vi',
                    matched_entry_id INT DEFAULT NULL,
                    score INT DEFAULT 0,
                    reply_preview TEXT NULL,
                    needs_training TINYINT DEFAULT 0,
                    created_at TIMESTAMP NULL
                    DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    KEY idx_chatbot_training_log_needs_training
                    (needs_training),
                    KEY idx_chatbot_training_log_created_at
                    (created_at)
                ) ENGINE=InnoDB
                DEFAULT CHARSET=utf8mb4
                COLLATE=utf8mb4_unicode_ci
            ");
        }

        // TỰ ĐỘNG KHỞI TẠO BẢNG LƯU THÔNG TIN ĐOẠN CHAT NẾU CHƯA CÓ
        if (!$this->tableExists('chatbot_messages')) {
            $this->conn->exec("
                CREATE TABLE chatbot_messages (
                    id INT NOT NULL AUTO_INCREMENT,
                    session_id VARCHAR(255) NOT NULL,
                    user_message TEXT NOT NULL,
                    bot_reply TEXT NOT NULL,
                    language VARCHAR(10) DEFAULT 'vi',
                    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    KEY idx_chatbot_messages_session_id (session_id),
                    KEY idx_chatbot_messages_created_at (created_at)
                ) ENGINE=InnoDB
                DEFAULT CHARSET=utf8mb4
                COLLATE=utf8mb4_unicode_ci
            ");
        }

        $this->seedDefaults();
    }

    public function getEntries(
        bool $activeOnly = false,
        ?string $language = null
    ): array {
        $where = [];
        $params = [];

        if ($activeOnly) {
            $where[] = 'COALESCE(is_active,1)=1';
        }

        if ($language !== null && $language !== '') {
            $where[] = "
                (
                    COALESCE(language,'vi') IN (?, 'all')
                    OR COALESCE(language,'vi')='vi'
                )
            ";
            $params[] = $this->normalizeLanguage($language);
        }

        $whereSql = $where
            ? 'WHERE ' . implode(' AND ', $where)
            : '';

        $stmt = $this->conn->prepare("
            SELECT *
            FROM chatbot
            $whereSql
            ORDER BY
                COALESCE(priority,0) DESC,
                id DESC
        ");

        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function saveEntry(array $data): bool
    {
        $id = (int)($data['id'] ?? 0);
        $question = trim((string)($data['question'] ?? ''));
        $answer = trim((string)($data['answer'] ?? ''));
        $language = $this->normalizeLanguage(
            (string)($data['language'] ?? 'vi'),
            true
        );
        $keywords = trim((string)($data['keywords'] ?? ''));
        $category = trim((string)($data['category'] ?? 'general'));
        $priority = (int)($data['priority'] ?? 0);
        $isActive = (int)($data['is_active'] ?? 1) === 1 ? 1 : 0;

        if ($question === '' || $answer === '') {
            return false;
        }

        if ($id > 0) {
            $stmt = $this->conn->prepare("
                UPDATE chatbot
                SET
                    question=?,
                    answer=?,
                    language=?,
                    keywords=?,
                    category=?,
                    priority=?,
                    is_active=?
                WHERE id=?
            ");

            return $stmt->execute([
                $question,
                $answer,
                $language,
                $keywords,
                $category,
                $priority,
                $isActive,
                $id
            ]);
        }

        $stmt = $this->conn->prepare("
            INSERT INTO chatbot (
                question,
                answer,
                language,
                keywords,
                category,
                priority,
                is_active
            )
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");

        return $stmt->execute([
            $question,
            $answer,
            $language,
            $keywords,
            $category,
            $priority,
            $isActive
        ]);
    }

    public function matchEntry(
        string $message,
        string $language = 'vi'
    ): ?array {
        $language = $this->normalizeLanguage($language);
        $messageTokens = $this->tokenize($message);
        $normalizedMessage = $this->normalizeText($message);

        if ($normalizedMessage === '') {
            return null;
        }

        $best = null;

        foreach ($this->getEntries(true, $language) as $entry) {
            $score = $this->scoreEntry(
                $normalizedMessage,
                $messageTokens,
                $entry
            );

            if (!$best || $score > $best['score']) {
                $best = [
                    'entry' => $entry,
                    'score' => $score
                ];
            }
        }

        return ($best && $best['score'] >= 8)
            ? $best
            : null;
    }

    private function scoreEntry(
        string $normalizedMessage,
        array $messageTokens,
        array $entry
    ): int {
        $question = $this->normalizeText(
            (string)($entry['question'] ?? '')
        );
        $keywords = $this->normalizeText(
            (string)($entry['keywords'] ?? '')
        );

        $score = 0;
        $priority = max(
            0,
            min(20, (int)($entry['priority'] ?? 0))
        );

        if (
            $question !== ''
            && $question === $normalizedMessage
        ) {
            $score += 120;
        } elseif (
            $question !== ''
            && (
                str_contains($normalizedMessage, $question)
                || str_contains($question, $normalizedMessage)
            )
        ) {
            $score += 45;
        }

        $questionTokens = $this->tokenize($question);
        $keywordTokens = $this->tokenize($keywords);
        $messageSet = array_flip($messageTokens);

        foreach ($questionTokens as $token) {
            if (isset($messageSet[$token])) {
                $score += strlen($token) >= 5 ? 5 : 3;
                continue;
            }

            foreach ($messageTokens as $msgToken) {
                similar_text($token, $msgToken, $percent);
                if ($percent >= 80) {
                    $score += 2;
                    break;
                }
            }
        }

        foreach ($keywordTokens as $token) {
            if (isset($messageSet[$token])) {
                $score += strlen($token) >= 5 ? 7 : 4;
            }
        }

        return $score >= 8
            ? $score + $priority
            : 0;
    }

    public function tokenize(string $value): array
    {
        $normalized = $this->normalizeText($value);

        if ($normalized === '') {
            return [];
        }

        preg_match_all(
            '/[\p{L}\p{N}#]+/u',
            $normalized,
            $matches
        );

        $parts = $matches[0] ?? [];
        $tokens = [];

        foreach ($parts as $part) {
            $part = trim($part);
            $length = function_exists('mb_strlen')
                ? mb_strlen($part, 'UTF-8')
                : strlen($part);

            if (
                $part === ''
                || $length < 2
                || in_array($part, $this->stopWords, true)
            ) {
                continue;
            }

            $tokens[$part] = true;
        }

        return array_keys($tokens);
    }

    public function normalizeText(string $value): string
    {
        $value = trim($value);

        if ($value === '') {
            return '';
        }

        $value = function_exists('mb_strtolower')
            ? mb_strtolower($value, 'UTF-8')
            : strtolower($value);

        $map = [
            'à'=>'a','á'=>'a','ạ'=>'a','ả'=>'a','ã'=>'a',
            'â'=>'a','ầ'=>'a','ấ'=>'a','ậ'=>'a','ẩ'=>'a','ẫ'=>'a',
            'ă'=>'a','ằ'=>'a','ắ'=>'a','ặ'=>'a','ẳ'=>'a','ẵ'=>'a',

            'è'=>'e','é'=>'e','ẹ'=>'e','ẻ'=>'e','ẽ'=>'e',
            'ê'=>'e','ề'=>'e','ế'=>'e','ệ'=>'e','ể'=>'e','ễ'=>'e',

            'ì'=>'i','í'=>'i','ị'=>'i','ỉ'=>'i','ĩ'=>'i',

            'ò'=>'o','ó'=>'o','ọ'=>'o','ỏ'=>'o','õ'=>'o',
            'ô'=>'o','ồ'=>'o','ố'=>'o','ộ'=>'o','ổ'=>'o','ỗ'=>'o',
            'ơ'=>'o','ờ'=>'o','ớ'=>'o','ợ'=>'o','ở'=>'o','ỡ'=>'o',

            'ù'=>'u','ú'=>'u','ụ'=>'u','ủ'=>'u','ũ'=>'u',
            'ư'=>'u','ừ'=>'u','ứ'=>'u','ự'=>'u','ử'=>'u','ữ'=>'u',

            'ỳ'=>'y','ý'=>'y','ỵ'=>'y','ỷ'=>'y','ỹ'=>'y',

            'đ'=>'d',
        ];

        $ascii = strtr($value, $map);
        $combined = $value . ' ' . $ascii;

        $combined = preg_replace(
            '/[^\p{L}\p{N}\s#]+/u',
            ' ',
            $combined
        ) ?? $combined;

        return trim(
            preg_replace('/\s+/u', ' ', $combined)
            ?? $combined
        );
    }

    public function fallbackReply(): string
    {
        return "Tôi chưa hiểu rõ câu hỏi.\n\nBạn có thể hỏi:\n- giá nông sản\n- tồn kho\n- nguồn gốc\n- đơn hàng\n- thanh toán\n- vận chuyển";
    }

    private function seedDefaults(): void
    {
        $defaults = [
            [
                'Thanh toán bằng gì?',
                'Checkout hỗ trợ VISA và tài khoản ngân hàng.',
                'vi',
                'thanh toán, visa, ngân hàng',
                'payment',
                20,
            ],
            [
                'Giá dưa hấu',
                'Bạn có thể hỏi giá dưa hấu hôm nay.',
                'vi',
                'dưa hấu, dua hau, watermelon',
                'inventory',
                15,
            ],
            [
                'Giá dừa',
                'Bạn có thể hỏi giá dừa tươi.',
                'vi',
                'dừa, dua, coconut',
                'inventory',
                15,
            ],
            [
                'What can I ask?',
                'You can ask about products, prices, inventory, origins, orders, payment, shipping, and export orders.',
                'en',
                'help, products, inventory, orders, export',
                'help',
                30,
            ],
            [
                '何を質問できますか？',
                '商品、価格、在庫、産地、注文、支払い、配送、輸出注文について質問できます。',
                'ja',
                'ヘルプ, 商品, 在庫, 注文, 輸出',
                'help',
                30,
            ],
            [
                '무엇을 물어볼 수 있나요?',
                '상품, 가격, 재고, 원산지, 주문, 결제, 배송 및 수출 주문에 대해 질문할 수 있습니다.',
                'ko',
                '도움말, 상품, 재고, 주문, 수출',
                'help',
                30,
            ],
            [
                '我可以问什么？',
                '你可以询问商品、价格、库存、产地、订单、付款、运输和出口订单。',
                'zh',
                '帮助, 商品, 库存, 订单, 出口',
                'help',
                30,
            ],
            [
                'Was kann ich fragen?',
                'Sie können nach Produkten, Preisen, Bestand, Herkunft, Bestellungen, Zahlung, Versand und Exportaufträgen fragen.',
                'de',
                'hilfe, produkte, bestand, bestellungen, export',
                'help',
                30,
            ],
            [
                'Que puis-je demander ?',
                'Vous pouvez demander des informations sur les produits, prix, stocks, origines, commandes, paiement, livraison et export.',
                'fr',
                'aide, produits, stock, commandes, export',
                'help',
                30,
            ],
            [
                'ถามอะไรได้บ้าง?',
                'คุณสามารถถามเรื่องสินค้า ราคา สต็อก แหล่งที่มา คำสั่งซื้อ การชำระเงิน การขนส่ง และออเดอร์ส่งออก',
                'th',
                'ช่วยเหลือ, สินค้า, สต็อก, คำสั่งซื้อ, ส่งออก',
                'help',
                30,
            ],
            [
                'ماذا يمكنني أن أسأل؟',
                'يمكنك السؤال عن المنتجات والأسعار والمخزون والمنشأ والطلبات والدفع والشحن وطلبات التصدير.',
                'ar',
                'مساعدة, منتجات, مخزون, طلبات, تصدير',
                'help',
                30,
            ],
        ];

        $stmt = $this->conn->prepare("
            INSERT INTO chatbot (
                question,
                answer,
                language,
                keywords,
                category,
                priority,
                is_active
            )
            VALUES (?, ?, ?, ?, ?, ?, 1)
        ");

        $exists = $this->conn->prepare("
            SELECT COUNT(*)
            FROM chatbot
            WHERE question=?
              AND COALESCE(language, 'vi')=?
        ");

        foreach ($defaults as $row) {
            $exists->execute([$row[0], $row[2]]);
            if ((int)$exists->fetchColumn() > 0) {
                continue;
            }
            $stmt->execute($row);
        }
    }

    private function normalizeLanguage(
        string $language,
        bool $allowAll = false
    ): string {
        $language = strtolower(trim($language));
        $allowed = $allowAll
            ? ['vi','en','ja','ko','zh','de','fr','th','ar','all']
            : ['vi','en','ja','ko','zh','de','fr','th','ar'];

        return in_array($language, $allowed, true) ? $language : 'vi';
    }

    private function tableExists(string $table): bool
    {
        try {
            $stmt = $this->conn->prepare("
                SELECT COUNT(*)
                FROM information_schema.TABLES
                WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = ?
            ");
            $stmt->execute([$table]);
            return (int)$stmt->fetchColumn() > 0;
        } catch (Throwable $e) {
            return false;
        }
    }

    private function addColumn(
        string $table,
        string $column,
        string $definition
    ): void {
        try {
            $stmt = $this->conn->prepare("
                SELECT COUNT(*)
                FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = ?
                AND COLUMN_NAME = ?
            ");

            $stmt->execute([$table, $column]);

            if ((int)$stmt->fetchColumn() === 0) {
                $this->conn->exec("
                    ALTER TABLE `$table`
                    ADD COLUMN `$column` $definition
                ");
            }
        } catch (Throwable $e) {
            error_log($e->getMessage());
        }
    }

    public function logInteraction(
        string $message,
        string $language,
        ?int $matchedEntryId,
        int $score,
        string $reply,
        bool $needsTraining
    ): void {
        try {
            $stmt = $this->conn->prepare("
                INSERT INTO chatbot_training_log (
                    message,
                    language,
                    matched_entry_id,
                    score,
                    reply_preview,
                    needs_training
                )
                VALUES (?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $this->limitText($message, 1200),
                $language,
                $matchedEntryId ?: null,
                $score,
                $this->limitText($reply, 1200),
                $needsTraining ? 1 : 0,
            ]);
        } catch (Throwable $e) {
            error_log('Cannot log chatbot interaction: ' . $e->getMessage());
        }
    }

    public function getRecentLogs(int $limit = 40): array
    {
        $limit = max(1, min(100, $limit));
        $stmt = $this->conn->query("
            SELECT *
            FROM chatbot_training_log
            ORDER BY created_at DESC, id DESC
            LIMIT {$limit}
        ");

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function deleteEntry(int $id): bool
    {
        if ($id <= 0) {
            return false;
        }

        $stmt = $this->conn->prepare("
            DELETE FROM chatbot
            WHERE id = ?
        ");

        return $stmt->execute([$id]);
    }

    private function limitText(
        string $value,
        int $limit
    ): string {
        if (
            function_exists('mb_substr')
            && function_exists('mb_strlen')
        ) {
            return mb_strlen($value, 'UTF-8') > $limit
                ? mb_substr($value, 0, $limit, 'UTF-8')
                : $value;
        }

        return strlen($value) > $limit
            ? substr($value, 0, $limit)
            : $value;
    }

    // HÀM LƯU THÔNG TIN ĐOẠN CHAT ĐÃ ĐƯỢC SỬA LẠI ĐỂ TỐI ƯU HÓA AN TOÀN DB
    public function saveChatMessage(
        string $sessionId,
        string $userMessage,
        string $botReply,
        string $language
    ): void {
        try {
            $stmt = $this->conn->prepare("
                INSERT INTO chatbot_messages (
                    session_id,
                    user_message,
                    bot_reply,
                    language
                ) VALUES (?, ?, ?, ?)
            ");

            $stmt->execute([
                trim($sessionId),
                $this->limitText($userMessage, 2000), // Giới hạn ký tự phòng spam dữ liệu dài gây lỗi DB
                $this->limitText($botReply, 2000),
                $this->normalizeLanguage($language)
            ]);
        } catch (Throwable $e) {
            error_log('Cannot save chat message: ' . $e->getMessage());
        }
    }
    
    // TÙY CHỌN THÊM: HÀM LẤY LỊCH SỬ ĐOẠN CHAT THEO PHIÊN (SESSION)
    public function getChatHistory(string $sessionId, int $limit = 20): array
    {
        $limit = max(1, min(100, $limit));
        try {
            $stmt = $this->conn->prepare("
                SELECT user_message, bot_reply, created_at
                FROM chatbot_messages
                WHERE session_id = ?
                ORDER BY created_at DESC, id DESC
                LIMIT ?
            ");
            $stmt->bindValue(1, $sessionId, PDO::PARAM_STR);
            $stmt->bindValue(2, $limit, PDO::PARAM_INT);
            $stmt->execute();

            return array_reverse($stmt->fetchAll(PDO::FETCH_ASSOC));
        } catch (Throwable $e) {
            error_log('Cannot get chat history: ' . $e->getMessage());
            return [];
        }
    }
}
