
<?php
ini_set('display_errors', 0);
error_reporting(E_ALL);
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../models/ProductModel.php';
require_once __DIR__ . '/../models/ChatbotModel.php';
require_once __DIR__ . '/../models/OnlineSearchModel.php';

class ChatController
{
    private PDO $conn;
    private ProductModel $productModel;
    private ChatbotModel $chatbotModel;
    private OnlineSearchModel $onlineSearchModel;

    private array $languages = [
        'vi' => 'Tiếng Việt',
        'en' => 'English',
        'ja' => '日本語',
        'ko' => '한국어',
        'zh' => '中文',
        'de' => 'Deutsch',
        'fr' => 'Français',
        'th' => 'ไทย',
        'ar' => 'العربية',
    ];
    private array $languageCurrencies = [
        'vi' => 'VND',
        'en' => 'USD',
        'ja' => 'JPY',
        'ko' => 'KRW',
        'zh' => 'CNY',
        'de' => 'EUR',
        'fr' => 'EUR',
        'th' => 'THB',
        'ar' => 'USD',
    ];
    private array $currencyRates = [
        'VND' => 1,
        'USD' => 25000,
        'EUR' => 27000,
        'JPY' => 170,
        'KRW' => 18,
        'CNY' => 3500,
        'THB' => 700,
    ];
    public function __construct()
    {
        $database = new Database();
        $this->conn = $database->getConnection();
        $this->productModel = new ProductModel($this->conn);
        $this->chatbotModel = new ChatbotModel($this->conn);
        $this->onlineSearchModel = new OnlineSearchModel($this->conn);
    }

   public function sendMessage(): void
{
    header('Content-Type: application/json; charset=utf-8');

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode([
            'reply' => 'Method không hợp lệ',
            'language' => 'vi'
        ], JSON_UNESCAPED_UNICODE);
        return;
    }

    $language = $this->normalizeLanguage($_POST['language'] ?? 'vi');
    $userMessage = trim((string)($_POST['message'] ?? ''));

    if ($userMessage === '') {
        echo json_encode([
            'reply' => $this->emptyMessage($language),
            'language' => $language
        ], JSON_UNESCAPED_UNICODE);
        return;
    }
   

    try {

        if (!method_exists($this, 'hybridAnswer')) {
            throw new Exception('Thiếu method hybridAnswer()');
        }

        if (!$this->chatbotModel) {
            throw new Exception('ChatbotModel chưa khởi tạo');
        }

        $result = $this->hybridAnswer($userMessage, $language);

        if (!is_array($result)) {
            throw new Exception('hybridAnswer không trả array');
        }

        $reply = $this->localizeReply(
            (string)($result['reply'] ?? 'Không có phản hồi'),
            $language
        );

        $this->chatbotModel->logInteraction(
            $userMessage,
            $language,
            $result['matched_entry_id'] ?? null,
            (int)($result['score'] ?? 0),
            $reply,
            (bool)($result['needs_training'] ?? false)
        );

        echo json_encode([
            'reply' => $reply,
            'language' => $language,
            'source' => $result['source'] ?? 'hybrid',
            'debug' => 'ok'
        ], JSON_UNESCAPED_UNICODE);

    } catch (Throwable $e) {

        error_log($e->getMessage());

        echo json_encode([
            'reply' => $this->systemError($language),
            'language' => $language,
            'source' => 'system_error'
        ], JSON_UNESCAPED_UNICODE);
    }
}

   private function hybridAnswer(string $message, string $language): array
    {
    $normalized = $this->chatbotModel->normalizeText($message);

    /*
    |--------------------------------------------------------------------------
    | GREETING
    |--------------------------------------------------------------------------
    */
    if ($this->isGreetingOnly($normalized)) {
        return [
            'reply' => $this->greetingReply($language),
            'source' => 'built_in_greeting',
            'score' => 100,
            'needs_training' => false,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | PROMOTION / FLASH SALE / FEATURED PRODUCTS
    |--------------------------------------------------------------------------
    */
    if ($this->looksLikePromotionQuestion($normalized)) {
        return [
            'reply' => $this->promotionReply($language),
            'source' => 'promotion_system',
            'score' => 96,
            'needs_training' => false,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | HEALTHY / EAT CLEAN
    |--------------------------------------------------------------------------
    */
    if ($this->hasAny($normalized, [
        'healthy',
        'eat clean',
        'giam can',
        'giảm cân',
        'it calo',
        'ít calo'
    ])) {

        $products = $this->findProductsByTags([
            'healthy',
            'salad',
            'organic',
            'eat clean'
        ]);

        if (!$products) {
            $products = $this->getPopularVegetables();
        }

        $reply = "🥗 Gợi ý thực phẩm healthy hôm nay:\n\n";

        foreach (array_slice($products, 0, 5) as $product) {
            $reply .= "- {$product->name}: "
                . number_format($product->price, 0, ',', '.')
                . "đ/kg\n";
        }

        if (!$products) {
            $reply .= "- Hiện chưa có sản phẩm phù hợp đang còn hàng.\n";
        }

        $reply .= "\n💚 Phù hợp cho chế độ ăn lành mạnh và nhiều chất xơ.";

        return [
            'reply' => $reply,
            'source' => 'healthy_recommendation',
            'score' => 95,
            'needs_training' => false,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | COOKING / MEAL SUGGESTION
    |--------------------------------------------------------------------------
    */
    if ($this->hasAny($normalized, [
        'lau',
        'lẩu',
        'canh',
        'xao',
        'xào',
        'nau gi',
        'nấu gì'
    ])) {

        $reply = "🍲 Một số nguyên liệu phù hợp:\n\n";

        $products = $this->findProducts($message);

        if (!$products) {
            $products = $this->getPopularVegetables();
        }

        foreach (array_slice($products, 0, 5) as $product) {
            $reply .= "- {$product->name}\n";
        }

        $reply .= "\n🚚 Rau được giao nhanh trong ngày để đảm bảo độ tươi.";

        return [
            'reply' => $reply,
            'source' => 'meal_recommendation',
            'score' => 90,
            'needs_training' => false,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | PRODUCT QUERY
    |--------------------------------------------------------------------------
    */
    if (
        $this->looksLikeProductQuestion($message, $normalized)
        || $this->findProducts($message)
    ) {

        return [
            'reply' => $this->productReply($message, $language),
            'source' => 'product_system',
            'score' => 88,
            'needs_training' => false,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | SHIPPING
    |--------------------------------------------------------------------------
    */
   if ($this->hasAny($normalized, [
    'ship',
    'shipping',
    'giao hang',
    'giao hàng',
    'van chuyen',
    'vận chuyển',
    'delivery'
])) {

        return [
            'reply' =>
                "🚚 Hệ thống hỗ trợ giao hàng nhanh nội thành từ 1-2 giờ.\n"
                . "Đơn rau củ sẽ được đóng gói giữ tươi trước khi giao.\n"
                . "Miễn phí ship với đơn hàng đủ điều kiện.",
            'source' => 'shipping_system',
            'score' => 85,
            'needs_training' => false,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | PAYMENT
    |--------------------------------------------------------------------------
    */
    if ($this->hasAny($normalized, [
        'thanh toan',
        'thanh toán',
        'visa',
        'momo',
        'cod'
    ])) {

        return [
            'reply' =>
                "💳 Hiện hỗ trợ:\n"
                . "- COD cho đơn nội địa\n"
                . "- Chuyển khoản ngân hàng nội địa\n"
                . "- VISA/MasterCard\n"
                . "- SWIFT/TT cho đơn xuất khẩu",
            'source' => 'payment_system',
            'score' => 80,
            'needs_training' => false,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | TRAINING DATA
    |--------------------------------------------------------------------------
    */
    $match = $this->chatbotModel->matchEntry($message, $language);

    if ($match) {
        return [
            'reply' => $this->renderTrainingAnswer(
                (string)$match['entry']['answer']
            ),
            'source' => 'training_data',
            'matched_entry_id' => (int)$match['entry']['id'],
            'score' => (int)$match['score'],
            'needs_training' => false,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | DEFAULT FALLBACK
    |--------------------------------------------------------------------------
    */
    return [
        'reply' => $this->defaultReply($language),
        'source' => 'fallback',
        'score' => 0,
        'needs_training' => true,
    ];
    }
    private function findProductsByTags(array $tags): array
    {
    try {


        $conditions = [];
        $params = [];

        foreach ($tags as $index => $tag) {
            $conditions[] = "tags LIKE :tag{$index}";
            $params["tag{$index}"] = '%' . $tag . '%';
        }

        $sql = "
            SELECT *
            FROM product
            WHERE stock > 0
            AND (" . implode(' OR ', $conditions) . ")
            LIMIT 10
        ";

        $stmt = $this->conn->prepare($sql);

        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value);
        }

        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_OBJ);

    } catch (Throwable $e) {

        error_log($e->getMessage());

        return [];
    }

    }

    private function getPopularVegetables(): array
    {
        $products = $this->productModel->getAvailableProducts();
        $healthyTerms = [
            'rau',
            'cai',
            'xa lach',
            'dua leo',
            'ca chua',
            'trai cay',
            'cam',
            'tao',
            'chanh',
            'dua hau',
            'thanh long',
            'mang cut',
            'dua xiem',
        ];

        $filtered = [];

        foreach ($products as $product) {
            $searchText = $this->productSearchText(
                (string)($product->name ?? '')
                . ' '
                . (string)($product->category_name ?? '')
            );

            foreach ($healthyTerms as $term) {
                if (str_contains($searchText, $term)) {
                    $filtered[] = $product;
                    break;
                }
            }
        }

        return array_slice($filtered ?: $products, 0, 5);

    }
private function formatPriceByLanguage(float $priceVnd, string $language = 'vi', string $unit = 'kg'): string
{
    if ($priceVnd <= 0) {
        return 'giá đang cập nhật';
    }

    $vndText = number_format($priceVnd, 0, ',', '.') . 'đ/' . $unit;

    $currency = $this->languageCurrencies[$language] ?? 'VND';

    if ($currency === 'VND') {
        return $vndText;
    }

    if (!isset($this->currencyRates[$currency]) || $this->currencyRates[$currency] <= 0) {
        return $vndText;
    }

    $converted = $priceVnd / $this->currencyRates[$currency];

    $symbols = [
        'USD' => '$',
        'EUR' => '€',
        'JPY' => '¥',
        'KRW' => '₩',
        'CNY' => '¥',
        'THB' => '฿',
        'VND' => 'đ',
    ];

    $symbol = $symbols[$currency] ?? '';

    if (in_array($currency, ['JPY', 'KRW'], true)) {
        $convertedText = number_format($converted, 0, ',', '.');
    } else {
        $convertedText = number_format($converted, 2, ',', '.');
    }

    return $vndText . ' ≈ ' . $symbol . $convertedText . ' ' . $currency . '/' . $unit;
}

private function currencyNoteByLanguage(string $language = 'vi'): string
{
    $currency = $this->languageCurrencies[$language] ?? 'VND';

    if ($currency === 'VND') {
        return '';
    }

    return match ($language) {
        'en' => "\n  💱 Exchange rate is for reference only.",
        'ja' => "\n  💱 為替レートは参考値です。",
        'ko' => "\n  💱 환율은 참고용입니다.",
        'zh' => "\n  💱 汇率仅供参考。",
        'de' => "\n  💱 Der Wechselkurs dient nur als Referenz.",
        'fr' => "\n  💱 Le taux de change est seulement indicatif.",
        'th' => "\n  💱 อัตราแลกเปลี่ยนเป็นเพียงค่าประมาณ.",
        'ar' => "\n  💱 سعر الصرف تقريبي فقط.",
        default => "\n  💱 Tỷ giá quy đổi chỉ mang tính tham khảo.",
    };
}

 private function productReply(string $message, string $language = 'vi'): string
{
    $matches = $this->findProducts($message);

    if ($matches) {

        $lines = [
            count($matches) === 1
                ? '🥬 Tôi tìm thấy sản phẩm phù hợp:'
                : '🥦 Tôi tìm thấy các sản phẩm phù hợp nhất:'
        ];

        foreach (array_slice($matches, 0, 10) as $product) {

            $productName = $product->name ?? 'Sản phẩm';

            $basePrice = (float)($product->price ?? 0);
            $price = $this->formatPriceByLanguage($basePrice, $language, 'kg');
            $flashDiscount = (int)($product->flash_discount ?? 0);
            $isFlashSale = (int)($product->is_flash_sale ?? 0) === 1 && $flashDiscount > 0;
            $isFeatured = (int)($product->is_featured ?? 0) === 1;

            $stockValue = (float)($product->stock ?? 0);

            $stock = number_format($stockValue, 0, ',', '.') . ' kg';

            if ($isFlashSale && $basePrice > 0) {
            $salePrice = max(0, $basePrice * (100 - min(90, $flashDiscount)) / 100);
            $price .= ' (Flash Sale còn ' . $this->formatPriceByLanguage($salePrice, $language, 'kg') . ')';
            }

            $badges = [];
            if ($isFlashSale) {
                $badges[] = 'Flash Sale -' . $flashDiscount . '%';
            }
            if ($isFeatured) {
                $badges[] = 'Sản phẩm nổi bật';
            }

            $line = '- ' . $productName . ': ' . $price . ', tồn ' . $stock;
            if ($badges) {
                $line = '- ' . $productName . ' [' . implode(' | ', $badges) . ']: ' . $price . ', tồn ' . $stock;
            }

            // nguồn gốc
            $origin = trim((string)($product->origin ?? ''));

            if ($origin !== '') {
                $line .= ', nguồn gốc ' . $origin;
            }

            // vận chuyển phù hợp
            $shipping = '🚚 Giao tiêu chuẩn';

            $normalizedName = $this->productSearchText($productName);

            if (
                str_contains($normalizedName, 'rau')
                || str_contains($normalizedName, 'xa lach')
                || str_contains($normalizedName, 'cai')
                || str_contains($normalizedName, 'dua leo')
            ) {
                $shipping = '⚡ Khuyến nghị giao hỏa tốc để giữ độ tươi';
            }

            if ($stockValue >= 50) {
                $shipping = '🚀 Có thể giao nhanh trong ngày';
            }

            $line .= "\n  " . $shipping;

            // Sản phẩm mua kèm
            $related = [];

            if (str_contains($normalizedName, 'ca chua')) {
                $related[] = 'xà lách';
                $related[] = 'dưa leo';
            }

            if (str_contains($normalizedName, 'khoai')) {
                $related[] = 'cà rốt';
                $related[] = 'hành tây';
            }

            if (str_contains($normalizedName, 'rau')) {
                $related[] = 'nấm';
                $related[] = 'đậu hũ';
            }

            if (str_contains($normalizedName, 'dua hau')) {
                $related[] = 'thanh long';
                $related[] = 'xoài';
            }

            if (!empty($related)) {
                $line .= "\n  🥗 Gợi ý mua kèm: " . implode(', ', $related);
            }

            // cảnh báo tồn kho
            if ($stockValue <= 10) {
                $line .= "\n  ⚠️ Sản phẩm sắp hết hàng";
            }

            // link sản phẩm
            if (!empty($product->id)) {
                $line .= "\n  🔗 Xem: /webbanhang/product/detail/" . (int)$product->id;
            }

            // ghi chú tỷ giá nếu đang dùng ngôn ngữ quy đổi tiền
            $line .= $this->currencyNoteByLanguage($language);

            $lines[] = $line;
        }
        // giá thị trường online
        if ($this->wantsOnlineMarketPrice($message)) {

            $online = $this->onlineMarketReply($message, $language, false);

            if ($online !== null) {
                $lines[] = '';
                $lines[] = $online;
            }
        }

        return implode("\n", $lines);
    }

        // không tìm thấy sản phẩm
        if ($this->productQueryTokens($message)) {

            $online = $this->onlineMarketReply($message, $language, true);

            if ($online !== null) {
                return $this->productNotFoundReply($message, $language) . "\n\n" . $online;
            }

            return $this->productNotFoundReply($message, $language);
        }

        // thống kê tổng quát
        $productCount = (int)$this->fetchColumnSafe(
            'SELECT COUNT(*) FROM product'
        );

        $stock = (float)$this->fetchColumnSafe(
            'SELECT COALESCE(SUM(stock), 0) FROM product'
        );

        $lowStock = $this->fetchAllSafe("
            SELECT name, stock
            FROM product
            WHERE COALESCE(stock, 0) <= 10
            ORDER BY stock ASC, name ASC
            LIMIT 5
        ");

        $reply =
            '🥬 Hiện cửa hàng có '
            . number_format($productCount, 0, ',', '.')
            . ' sản phẩm và '
            . number_format($stock, 0, ',', '.')
            . " kg tồn kho.\n"
            . 'Bạn có thể hỏi như:' . "\n"
            . '- giá trái cây' . "\n"
            . '- tồn kho rau cải' . "\n"
            . '- sản phẩm còn hàng' . "\n"
            . '- giá thị trường cà chua hôm nay';

        if ($lowStock) {

            $reply .= "\n\n⚠️ Sản phẩm tồn kho thấp:";

            foreach ($lowStock as $row) {

                $reply .= "\n- "
                    . $row['name']
                    . ': '
                    . number_format((float)$row['stock'], 0, ',', '.')
                    . ' kg';
            }
        }

        return $reply;
    }

private function looksLikePromotionQuestion(string $normalized): bool
{
    return $this->hasAny($normalized, [
        'khuyen mai',
        'khuyến mãi',
        'uu dai',
        'ưu đãi',
        'ma giam gia',
        'mã giảm giá',
        'voucher',
        'coupon',
        'flash sale',
        'sale',
        'giam gia',
        'giảm giá',
        'san pham noi bat',
        'sản phẩm nổi bật',
        'noi bat',
        'nổi bật',
        'hot',
    ]);
}

private function promotionReply(string $language = 'vi'): string
{
    $promotions = $this->fetchAllSafe("
        SELECT code, discount_percent, expiry_date
        FROM promotions
        WHERE status = 1
          AND expiry_date >= CURDATE()
          AND discount_percent > 0
        ORDER BY discount_percent DESC, expiry_date ASC, id DESC
        LIMIT 5
    ");

    $availableProducts = $this->productModel->getAvailableProducts();

    $flashProducts = array_values(array_filter($availableProducts, static function ($product) {
        return (int)($product->is_flash_sale ?? 0) === 1
            && (int)($product->flash_discount ?? 0) > 0;
    }));

    usort($flashProducts, static function ($a, $b) {
        return ((int)($b->flash_discount ?? 0) <=> (int)($a->flash_discount ?? 0))
            ?: ((int)($b->id ?? 0) <=> (int)($a->id ?? 0));
    });

    $featuredProducts = array_values(array_filter($availableProducts, static function ($product) {
        return (int)($product->is_featured ?? 0) === 1;
    }));

    $lines = [
        'Tôi đang đọc dữ liệu từ mục Khuyến mãi trong admin. Hôm nay có:'
    ];

    if ($promotions) {
        $lines[] = '';
        $lines[] = 'Mã giảm giá đang bật:';
        foreach ($promotions as $promotion) {
            $lines[] = '- ' . strtoupper((string)($promotion['code'] ?? ''))
                . ': giảm ' . number_format((float)($promotion['discount_percent'] ?? 0), 0, ',', '.')
                . '%, hết hạn ' . $this->formatPromotionDate((string)($promotion['expiry_date'] ?? ''));
        }
    }

    if ($flashProducts) {
        $lines[] = '';
        $lines[] = 'Flash Sale theo admin:';
        foreach (array_slice($flashProducts, 0, 6) as $product) {
            $discount = min(90, max(1, (int)($product->flash_discount ?? 0)));
            $basePrice = (float)($product->price ?? 0);
            $salePrice = max(0, $basePrice * (100 - $discount) / 100);
            $lines[] = '- ' . ($product->name ?? 'Sản phẩm')
                . ': giảm ' . $discount . '%, còn '
                . number_format($salePrice, 0, ',', '.') . 'đ/kg'
                . ', tồn ' . number_format((float)($product->stock ?? 0), 0, ',', '.') . ' kg'
                . (!empty($product->id) ? ' - /webbanhang/product/detail/' . (int)$product->id : '');
        }
    }

    if ($featuredProducts) {
        $lines[] = '';
        $lines[] = 'Sản phẩm nổi bật:';
        foreach (array_slice($featuredProducts, 0, 6) as $product) {
            $lines[] = '- ' . ($product->name ?? 'Sản phẩm')
                . ': ' . number_format((float)($product->price ?? 0), 0, ',', '.') . 'đ/kg'
                . ', tồn ' . number_format((float)($product->stock ?? 0), 0, ',', '.') . ' kg'
                . (!empty($product->id) ? ' - /webbanhang/product/detail/' . (int)$product->id : '');
        }
    }

    if (!$promotions && !$flashProducts && !$featuredProducts) {
        $lines[] = '';
        $lines[] = 'Hiện chưa có mã giảm giá, flash sale hoặc sản phẩm nổi bật nào đang bật trong admin.';
        $lines[] = 'Bạn có thể vào Admin > Khuyến mãi để bật mã giảm giá, Flash Sale hoặc Sản phẩm nổi bật.';
    }

    return implode("\n", $lines);
}

private function formatPromotionDate(string $value): string
{
    $timestamp = strtotime($value);
    return $timestamp ? date('d/m/Y', $timestamp) : 'không rõ';
}

    private function onlineMarketReply(
    string $message,
    string $language = 'vi',
    bool $includeIntro = true
): ?string {

    if (
        !$this->wantsOnlineMarketPrice($message)
        || !$this->isAgricultureOnlineQuery($message)
    ) {
        return null;
    }

    $payload = $this->onlineSearchModel->search(
        $this->marketSearchQuery($message),
        $language
    );

    $results = $this->filterAgricultureResults(
        $payload['results'] ?? [],
        $message
    );

    $results = $this->mergeMarketResults(
        $results,
        $this->curatedAgricultureMarketResults($message)
    );

    if (!$results) {
        return null;
    }

    $lines = [
        $includeIntro
            ? "🌎 Tham khảo giá thị trường nông sản từ web:"
            : "📈 Giá thị trường nông sản tham khảo:"
    ];

    foreach (array_slice($results, 0, 3) as $result) {

        $title = trim((string)($result['title'] ?? 'Nguồn tham khảo'));

        $source = trim((string)($result['source'] ?? 'Web')) ?: 'Web';

        $snippet = trim((string)($result['snippet'] ?? ''));

        $url = trim((string)($result['url'] ?? ''));

        $line = "━━━━━━━━━━━━━━";
        $line .= "\n🌿 " . $title;

        $line .= "\n🏢 Nguồn: " . $source;

        if ($snippet !== '') {

            $snippet = mb_substr($snippet, 0, 180);

            $line .= "\n📌 " . $snippet;
        }

        if ($url !== '') {
            $line .= "\n🔗 " . $url;
        }

        $lines[] = $line;
    }

        // phân tích xu hướng giá đơn giản
        $normalized = $this->chatbotModel->normalizeText($message);

        if (
            $this->hasAny($normalized, [
                'ca chua',
                'rau',
                'cai',
                'dua leo',
                'xa lach'
            ])
        ) {

            $lines[] =
                "\n🥬 Rau củ tươi nên ưu tiên giao nhanh trong ngày để giữ độ tươi.";
        }

        if (
            $this->hasAny($normalized, [
                'xoai',
                'sau rieng',
                'thanh long',
                'dua hau'
            ])
        ) {

            $lines[] =
                "\n🍉 Trái cây theo mùa thường có giá tốt hơn khi mua số lượng lớn.";
        }

        if (
            $this->hasAny($normalized, [
                'khoai',
                'hanh',
                'toi',
                'ca rot'
            ])
        ) {

            $lines[] =
                "\n📦 Các loại củ có thể bảo quản lâu và phù hợp vận chuyển tiêu chuẩn.";
        }

        $lines[] =
            "\n⚠️ Lưu ý: Giá trên web chỉ mang tính tham khảo thị trường. "
            . "Giá bán chính thức vẫn là giá trong cửa hàng.";

        return implode("\n", $lines);
    }
    private function isAgricultureOnlineQuery(string $message): bool
{
    $raw = function_exists('mb_strtolower')
        ? mb_strtolower($message, 'UTF-8')
        : strtolower($message);

    /*
    |--------------------------------------------------------------------------
    | Chặn các từ không liên quan
    |--------------------------------------------------------------------------
    */

    $blockedWords = [
        'xăng',
        'dầu',
        'bitcoin',
        'crypto',
        'chứng khoán',
        'forex',
        'casino',
        'cá cược',
        'vàng',
        'usd',
        'đô la',
    ];

    foreach ($blockedWords as $word) {

        // cho phép dâu / đậu
        if (
            $word === 'dầu'
            && (
                str_contains($raw, 'dâu')
                || str_contains($raw, 'đậu')
            )
        ) {
            continue;
        }

        if (str_contains($raw, $word)) {
            return false;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Chuẩn hóa text
    |--------------------------------------------------------------------------
    */

    $normalized = $this->chatbotModel->normalizeText($message);

    /*
    |--------------------------------------------------------------------------
    | Kiểm tra từ khóa nông sản
    |--------------------------------------------------------------------------
    */

    $agricultureWords = [

        'rau',
        'củ',
        'quả',
        'trái cây',
        'nông sản',

        'xoài',
        'cam',
        'bưởi',
        'chuối',
        'ổi',

        'cà chua',
        'xà lách',
        'rau muống',
        'bắp cải',
        'khoai',

        'đậu',
        'gạo',
        'lúa',
        'ớt',
        'hành',
    ];

    foreach ($agricultureWords as $word) {

        if (
            str_contains($normalized, $this->chatbotModel->normalizeText($word))
        ) {
            return true;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Nếu tìm được sản phẩm trong kho
    |--------------------------------------------------------------------------
    */

    if ($this->findProducts($message)) {
        return true;
    }

    return false;
}
    private function marketSearchQuery(string $message): string
    {
        $query = $this->productSearchText($message);

        // bỏ từ dư
        $query = str_replace([
            'gia',
            'thi truong',
            'hom nay',
            'online',
            'tren mang',
            'gia web',
        ], '', $query);

        $query = trim(preg_replace('/\s+/', ' ', $query));

        return 'giá nông sản Việt Nam ' . $query;
    }

    private function mergeMarketResults(array $primary, array $fallback): array
{
    $seen = [];
    $merged = [];

    foreach (array_merge($primary, $fallback) as $result) {

        $url = trim((string)($result['url'] ?? ''));
        $title = trim((string)($result['title'] ?? ''));

        if ($url === '' || $title === '') {
            continue;
        }

        $cleanUrl = explode('?', $url)[0];

        $key = strtolower(
            (string)(parse_url($cleanUrl, PHP_URL_HOST) ?: 'web')
        ) . '|' . $cleanUrl;

        if (isset($seen[$key])) {
            continue;
        }

        $seen[$key] = true;
        $merged[] = $result;
    }

    return $merged;
}
    private function curatedAgricultureMarketResults(string $message): array
    {
        if (!$this->isAgricultureOnlineQuery($message)) {
            return [];
        }

        $normalized = $this->chatbotModel->normalizeText($message);
        $results = [];
        $add = static function (string $title, string $snippet, string $url, string $source) use (&$results): void {
            $results[] = [
                'title' => $title,
                'snippet' => $snippet,
                'url' => $url,
                'source' => $source,
            ];
        };

        if ($this->hasAny($normalized, ['ca phe', 'cà phê', 'coffee'])) {
            $add(
                'Giá cà phê và hàng hóa nông sản',
                'Nguồn tham khảo bảng giá cà phê và diễn biến thị trường hàng hóa nông sản.',
                'https://www.thitruonghanghoa.com/gia-ca-phe-truc-tuyen/',
                'thitruonghanghoa.com'
            );
            $add(
                'Giá cà phê nội địa',
                'Nguồn chuyên theo dõi giá cà phê trong nước và tin thị trường cà phê.',
                'https://giacaphe.com/gia-ca-phe-noi-dia/',
                'giacaphe.com'
            );
        }

        if ($this->hasAny($normalized, ['ho tieu', 'hồ tiêu', 'tieu den', 'tiêu đen', 'gia tieu', 'giá tiêu'])) {
            $add(
                'Giá hồ tiêu hôm nay',
                'Nguồn chuyên về giá tiêu và tin thị trường hồ tiêu.',
                'https://giatieu.com/',
                'giatieu.com'
            );
        }

        if ($this->hasAny($normalized, ['gao', 'gạo', 'lua', 'lúa'])) {
            $add(
                'Giá lúa gạo và nông sản',
                'Nguồn tham khảo tin giá lúa gạo, nông sản và xuất khẩu.',
                'https://vietnambiz.vn/gia-lua-gao.htm',
                'vietnambiz.vn'
            );
        }

        if ($this->hasAny($normalized, ['cao su'])) {
            $add(
                'Giá cao su',
                'Nguồn tham khảo diễn biến giá cao su và hàng hóa nông sản.',
                'https://vietnambiz.vn/gia-cao-su.htm',
                'vietnambiz.vn'
            );
        }

        if (!$results) {
            $add(
                'Tin giá nông sản',
                'Nguồn tổng hợp tin giá nông sản; chỉ dùng để tham khảo thị trường.',
                'https://vietnambiz.vn/nong-san.htm',
                'vietnambiz.vn'
            );
            $add(
                'Giá hàng hóa nông sản',
                'Nguồn tham khảo diễn biến giá hàng hóa, ưu tiên các nhóm nông sản.',
                'https://www.thitruonghanghoa.com/',
                'thitruonghanghoa.com'
            );
        }

        return $results;
    }

   private function resultLooksAgricultural(
    array $result,
    string $originalMessage
): bool
{
    $haystack = implode(' ', [
        (string)($result['title'] ?? ''),
        (string)($result['snippet'] ?? ''),
        (string)($result['source'] ?? ''),
        (string)($result['url'] ?? ''),
    ]);

    $normalized = $this->chatbotModel->normalizeText($haystack);

    // chặn nội dung rác
    if ($this->hasAny($normalized, $this->junkOnlineTerms())) {
        return false;
    }

    $queryTerms = $this->agricultureQueryTerms($originalMessage);

    $hasAgricultureText = $this->hasAny(
        $normalized,
        $this->agricultureTerms()
    );

    $hasQueryCommodity =
        !empty($queryTerms)
        && $this->hasAny($normalized, $queryTerms);

    // nội dung nông sản + đúng commodity
    if (
        $hasAgricultureText
        && (
            empty($queryTerms)
            || $hasQueryCommodity
        )
    ) {
        return true;
    }

    // domain uy tín
    if (
        $this->sourceLooksAgricultural($result)
        && (
            $hasAgricultureText
            || $hasQueryCommodity
        )
    ) {
        return true;
    }

    return false;
}
    private function sourceLooksAgricultural(array $result): bool
{
    $url = strtolower(trim((string)($result['url'] ?? '')));
    $source = strtolower(trim((string)($result['source'] ?? '')));

    $host = parse_url($url, PHP_URL_HOST);

    if (!$host && $source !== '') {
        $host = parse_url(
            'https://' . ltrim($source, '/'),
            PHP_URL_HOST
        );
    }

    $host = strtolower((string)$host);

    if ($host === '') {
        return false;
    }

    foreach ($this->trustedAgricultureDomains() as $domain) {

        $domain = strtolower(trim($domain));

        // exact domain
        if ($host === $domain) {
            return true;
        }

        // subdomain
        if (str_ends_with($host, '.' . $domain)) {
            return true;
        }
    }

    return false;
}
    private function agricultureQueryTerms(string $message): array
{
    $normalized = $this->chatbotModel
        ->normalizeText($message);

    $terms = [];

    // match commodity chuẩn
    foreach ($this->agricultureTerms() as $term) {

        $normalizedTerm = $this->chatbotModel
            ->normalizeText($term);

        if (
            preg_match(
                '/\b' . preg_quote($normalizedTerm, '/') . '\b/u',
                $normalized
            )
        ) {
            $terms[] = $normalizedTerm;
        }
    }

    // thêm token sản phẩm đặc biệt
    foreach ($this->productQueryTokens($message) as $token) {

        $token = $this->chatbotModel
            ->normalizeText($token);

        // bỏ token rác
        if (
            strlen($token) < 3
            || in_array($token, [
                'thi',
                'truong',
                'web',
                'mang',
                'online',
                'gia',
                'hom',
                'nay',
            ], true)
        ) {
            continue;
        }

        $terms[] = $token;
    }

    return array_values(array_unique($terms));
}
    private function agricultureTerms(): array
{
    return [

        // nhóm tổng quát
        'nong san',
        'rau cu',
        'trai cay',

        // cây lương thực
        'lua',
        'gao',
        'ngo',
        'khoai',

        // cây công nghiệp
        'ca phe',
        'ho tieu',
        'dieu',
        'cao su',
        'tra',

        // trái cây
        'xoai',
        'sau rieng',
        'thanh long',
        'chuoi',
        'buoi',
        'nho',
        'tao',
        'cam',

        // rau củ
        'ca chua',
        'xa lach',
        'hanh',
        'ot',
        'dua',
        'dau xanh',
        'dau phong',

        // khác
        'dau tay',
    ];
}
    private function trustedAgricultureDomains(): array
{
    return [
        'giacaphe.com',
        'giatieu.com',
        'thitruonghanghoa.com',
        'vietnambiz.vn',
        'vinanet.vn',
        'mard.gov.vn',
        'nongnghiep.vn',
        'nongnghiepmoitruong.vn',

        'cafef.vn',
        'baochinhphu.vn',
        'vnanet.vn',
        'tapchicongthuong.vn',
        'mekongasean.vn',
        'danviet.vn',
    ];
}
private function junkOnlineTerms(): array
{
    return [
        // cờ bạc
        'casino', 'ca cuoc', 'cá cược',
        'xoso', 'xo so', 'xổ số',
        'lo de', 'lô đề',

        // tài chính / đầu tư
        'bitcoin', 'crypto',
        'chung khoan', 'chứng khoán',
        'forex',
        'coin', 'usdt',

        // nội dung giải trí
        'game', 'phim', 'anime',
        'sex', '18+', 'clip nong',

        // vay tiền / tài chính
        'vay tien', 'vay tiền',
        'tin dung', 'tín dụng',
        'ngan hang', 'ngân hàng',

        // bất động sản
        'bat dong san', 'bất động sản',
        'nha dat', 'nhà đất',

        // vàng bạc
        'gia vang', 'giá vàng',
        'vang sjc', 'vàng sjc',
        'bac mieng', 'bạc miếng',

        // xăng dầu / năng lượng
        'xang dau', 'xăng dầu',
        'dau tho', 'dầu thô',
        'petrol', 'oil price',

        // xe cộ
        'oto', 'ô tô',
        'xe may', 'xe máy',

        // điện tử
        'iphone', 'samsung',
        'laptop', 'gpu',

        // tuyển dụng
        'viec lam', 'việc làm',
        'tuyen dung', 'tuyển dụng',
    ];
}

private function orderReply(string $message): string
{
    $orderId = $this->extractOrderId($message);

    // tra đơn cụ thể
    if ($orderId > 0) {
        $order = $this->findVisibleOrder($orderId);

        if (!$order) {
            return 'Tôi chưa tìm thấy đơn #'
                . $orderId
                . ' trong phạm vi tài khoản hiện tại.';
        }

        $status = $this->orderStatusLabel(
            (string)($order['status'] ?? 'pending')
        );

        $payment = $this->paymentStatusLabel(
            (string)($order['payment_status'] ?? 'pending')
        );

        $total = number_format(
            (float)($order['total'] ?? 0),
            0,
            ',',
            '.'
        ) . 'đ';

        $created = !empty($order['created_at'])
            ? date('d/m/Y H:i', strtotime($order['created_at']))
            : 'Không rõ';

        return "Đơn #{$orderId}: {$status}.
        Thanh toán: {$payment}.
        Tổng đơn: {$total}.
        Ngày đặt: {$created}.
        Bạn có thể xem chi tiết trong Hồ sơ > Đơn hàng.";
            }

    // tổng quan user
    if (!empty($_SESSION['user_id'])) {
        try {
            $stmt = $this->conn->prepare("
                SELECT status, COUNT(*) AS total
                FROM orders
                WHERE user_id = ?
                GROUP BY status
                ORDER BY total DESC
            ");

            $stmt->execute([(int)$_SESSION['user_id']]);

            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if ($rows) {
                $parts = [];
                $totalOrders = 0;

                foreach ($rows as $row) {
                    $count = (int)$row['total'];
                    $totalOrders += $count;

                    $parts[] =
                        $this->orderStatusLabel((string)$row['status'])
                        . ': '
                        . $count;
                }

                return "Bạn hiện có {$totalOrders} đơn hàng:
- "
                    . implode("\n- ", $parts)
                    . "\nNhập mã đơn, ví dụ: đơn #54, để tôi tra nhanh hơn.";
            }
        } catch (Throwable $e) {
            return 'Tôi chưa đọc được dữ liệu đơn hàng lúc này.';
        }
    }

    return 'Bạn có thể đăng nhập rồi vào Hồ sơ > Đơn hàng để theo dõi trạng thái xử lý, xuất kho và vận chuyển.';
}
private function findProducts(string $message): array
{
    $normalizedMessage = $this->productSearchText($message);
    $messageTokens = $this->productQueryTokens($message);

    // Nhận diện nhóm danh mục từ tin nhắn người dùng
    $isFruitQuery = (str_contains($normalizedMessage, 'trai cay') || str_contains($normalizedMessage, 'hoa qua'));
    $isVegetableQuery = (str_contains($normalizedMessage, 'rau cu') || $normalizedMessage === 'rau' || $normalizedMessage === 'cu');

    // Nếu không khớp danh mục tổng quát và không có từ khóa tìm kiếm cụ thể thì dừng
    if (!$isFruitQuery && !$isVegetableQuery && ($normalizedMessage === '' || empty($messageTokens))) {
        return [];
    }

    $products = $this->productModel->getAvailableProducts();
    if (empty($products)) {
        return [];
    }

    $ranked = [];
    // Chuyển tin nhắn gốc về chữ thường để so sánh an toàn
    $lowerMessage = $this->lowerUtf8($message);
    $accentNeedle = (!$isFruitQuery && !$isVegetableQuery)
        ? $this->specificVietnameseProductNeedle($lowerMessage)
        : null;

    foreach ($products as $product) {
        $productNameOriginal = (string)($product->name ?? '');
        $name = $this->productSearchText($productNameOriginal);
        
        if ($name === '') {
            continue;
        }

        $categoryId = isset($product->category_id) ? (int)$product->category_id : 0;
        $score = 0;

        // 1. Nếu tìm TRÁI CÂY -> Chỉ lấy category_id = 2
        if ($isFruitQuery) {
            if ($categoryId === 2) {
                $score += 100;
            } else {
                continue;
            }
        }

        // 2. Nếu tìm RAU CỦ -> Chỉ lấy category_id = 3
        if ($isVegetableQuery) {
            if ($categoryId === 3) {
                $score += 100;
            } else {
                continue;
            }
        }

        // 3. Khớp từ khóa tự do (Tìm đích danh tên sản phẩm ví dụ: "dừa", "thanh long")
        if (!$isFruitQuery && !$isVegetableQuery) {
            $lowerProductName = $this->lowerUtf8($productNameOriginal);
            $categoryName = $this->productSearchText((string)($product->category_name ?? ''));

            if ($accentNeedle !== null && !str_contains($lowerProductName, $accentNeedle)) {
                continue;
            }

            // ƯU TIÊN 1: Khớp chính xác cả dấu tiếng Việt (Ví dụ: "dừa" nằm trong "Dừa xiêm xanh")
            if (str_contains($lowerProductName, $lowerMessage)) {
                $score += 200; // Cộng điểm cực cao để đẩy lên đầu
            } 
            // ƯU TIÊN 2: Khớp không dấu (Phòng trường hợp khách gõ "dua xiem")
            elseif (str_contains($name, $normalizedMessage)) {
                $score += 50;
            } elseif (!empty($messageTokens)) {
                $matchedTokens = 0;
                $requiredTokens = count($messageTokens) >= 2 ? count($messageTokens) : 1;

                foreach ($messageTokens as $token) {
                    if (
                        str_contains($name, $token)
                        || ($categoryName !== '' && str_contains($categoryName, $token))
                    ) {
                        $matchedTokens++;
                    }
                }

                if ($matchedTokens >= $requiredTokens) {
                    $score += 30 * $matchedTokens;
                } else {
                    continue;
                }
            } else {
                continue; // Không khớp từ khóa nào thì loại luôn
            }
        }

        if ($score > 0 && (float)($product->price ?? 0) <= 0) {
            $score -= 12;
        }

        if ($score > 0) {
            $product->search_score = $score; 
            $ranked[] = $product;
        }
    }

    // Sắp xếp sản phẩm điểm cao nhất lên đầu
    usort($ranked, static function ($a, $b) {
        return ($b->search_score ?? 0) <=> ($a->search_score ?? 0);
    });

    return $ranked;
}

private function lowerUtf8(string $value): string
{
    $value = trim($value);
    return function_exists('mb_strtolower')
        ? mb_strtolower($value, 'UTF-8')
        : strtolower($value);
}

private function specificVietnameseProductNeedle(string $lowerMessage): ?string
{
    foreach (['dừa', 'dưa'] as $needle) {
        $pattern = '/(^|[^\p{L}\p{N}])' . preg_quote($needle, '/') . '($|[^\p{L}\p{N}])/u';
        if (preg_match($pattern, $lowerMessage)) {
            return $needle;
        }
    }

    return null;
}

private function productQueryTokens(string $message): array
{
    $ignored = array_flip([

        // greeting
        'hi', 'hello', 'xin', 'chao',

        // đại từ
        'toi', 'minh', 'ban', 'oi',

        // giá
        'gia', 'bao', 'nhieu',
        'hom', 'nay',
        'price', 'prices', 'cost', 'costs', 'prix', 'preis',

        // tồn kho
        'ton', 'kho',
        'con', 'hang',
        'stock', 'inventory', 'available', 'availability', 'quantity',

        // sản phẩm
        'san', 'pham',
        'nong',
        'product', 'products', 'item', 'items',

        // thao tác
        'can', 'mua',
        'xem', 'tim', 'kiem',
        'show', 'find', 'search', 'buy', 'need',

        // nguồn gốc
        'nguon', 'goc',
        'xuat', 'xu',
        'origin', 'source',

        // địa lý
        'dia', 'ly',
        'diem',

        // online
        'thi', 'truong',
        'web', 'mang',
        'online',

        // cập nhật
        'cap', 'nhat',
        'moi',

        // tham khảo
        'tham', 'khao',

        // đơn vị
        'kg', 'gram', 'g',
    ]);

    $tokens = [];

    foreach ($this->productSearchTokens($message) as $token) {

        // bỏ stopwords
        if (isset($ignored[$token])) {
            continue;
        }

        // bỏ token 1 ký tự
        if (strlen($token) < 2) {
            continue;
        }

        $tokens[$token] = true;
    }

    return array_keys($tokens);
}


    private function productSearchTokens(string $value): array
    {
        $normalized = $this->productSearchText($value);
        if ($normalized === '') {
            return [];
        }

        preg_match_all('/[a-z0-9]+/', $normalized, $matches);
        $tokens = [];
        foreach ($matches[0] ?? [] as $token) {
            if (strlen($token) >= 2) {
                $tokens[$token] = true;
            }
        }
        return array_keys($tokens);
    }

    private function productSearchText(string $value): string
{
    $value = function_exists('mb_strtolower')
        ? mb_strtolower(trim($value), 'UTF-8')
        : strtolower(trim($value));

    if ($value === '') {
        return '';
    }

    $value = strtr($value, $this->productSearchAliases());

    $value = strtr($value, [
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
    ]);

    // bỏ ký tự đặc biệt
    $value = preg_replace('/[^a-z0-9\s]+/u', ' ', $value) ?? $value;

    // chuẩn hóa khoảng trắng
    $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

    return trim($value);
}

private function productSearchAliases(): array
{
    return [
        // English / international common names
        'prices' => ' ',
        'price' => ' ',
        'costs' => ' ',
        'cost' => ' ',
        'stock' => ' ',
        'inventory' => ' ',
        'available' => ' ',
        'availability' => ' ',
        'quantity' => ' ',
        'prix' => ' ',
        'preis' => ' ',
        'orange' => ' cam ',
        'mandarin' => ' cam ',
        'mango' => ' xoai ',
        'banana' => ' chuoi ',
        'apple' => ' tao ',
        'pomelo' => ' buoi ',
        'grapefruit' => ' buoi ',
        'grape' => ' nho ',
        'cashew nut' => ' hat dieu ',
        'cashew' => ' hat dieu ',
        'sunflower seed' => ' hat huong duong ',
        'sunflower seeds' => ' hat huong duong ',
        'peanut' => ' dau phong ',
        'peanuts' => ' dau phong ',
        'rice' => ' gao ',
        'coffee' => ' ca phe ',
        'black pepper' => ' tieu den ',
        'pepper' => ' tieu den ',
        'mangosteen' => ' mang cut ',
        'avocado' => ' bo sap ',
        'strawberry' => ' dau tay ',
        'guava' => ' oi ',
        'rambutan' => ' chom chom ',
        'ginger' => ' gung ',
        'tamarind' => ' me thai ',
        'durian' => ' sau rieng ',
        'dragon fruit' => ' thanh long ',
        'jackfruit' => ' mit ',
        'coconut' => ' dua xiem ',
        'lemon' => ' chanh ',
        'lime' => ' chanh ',
        'watermelon' => ' dua hau ',
        'chili' => ' ot ',
        'chilli' => ' ot ',

        // Japanese
        'オレンジ' => ' cam ',
        'みかん' => ' cam ',
        'ミカン' => ' cam ',
        'マンゴー' => ' xoai ',
        'バナナ' => ' chuoi ',
        'りんご' => ' tao ',
        'リンゴ' => ' tao ',
        'グレープフルーツ' => ' buoi ',
        'ザボン' => ' buoi ',
        'ぶどう' => ' nho ',
        'ブドウ' => ' nho ',
        'カシューナッツ' => ' hat dieu ',
        'ひまわりの種' => ' hat huong duong ',
        'ピーナッツ' => ' dau phong ',
        'お米' => ' gao ',
        '米' => ' gao ',
        'コーヒー' => ' ca phe ',
        '黒胡椒' => ' tieu den ',
        '胡椒' => ' tieu den ',
        'マンゴスチン' => ' mang cut ',
        'アボカド' => ' bo sap ',
        'いちご' => ' dau tay ',
        'イチゴ' => ' dau tay ',
        'グアバ' => ' oi ',
        'ランブータン' => ' chom chom ',
        'しょうが' => ' gung ',
        '生姜' => ' gung ',
        'タマリンド' => ' me thai ',
        'ドリアン' => ' sau rieng ',
        'ドラゴンフルーツ' => ' thanh long ',
        'ジャックフルーツ' => ' mit ',
        'ココナッツ' => ' dua xiem ',
        'レモン' => ' chanh ',
        'ライム' => ' chanh ',
        'スイカ' => ' dua hau ',
        '唐辛子' => ' ot ',

        // Chinese
        '橙子' => ' cam ',
        '橘子' => ' cam ',
        '芒果' => ' xoai ',
        '香蕉' => ' chuoi ',
        '苹果' => ' tao ',
        '柚子' => ' buoi ',
        '葡萄柚' => ' buoi ',
        '葡萄' => ' nho ',
        '腰果' => ' hat dieu ',
        '葵花籽' => ' hat huong duong ',
        '花生' => ' dau phong ',
        '大米' => ' gao ',
        '米' => ' gao ',
        '咖啡' => ' ca phe ',
        '黑胡椒' => ' tieu den ',
        '胡椒' => ' tieu den ',
        '山竹' => ' mang cut ',
        '牛油果' => ' bo sap ',
        '草莓' => ' dau tay ',
        '番石榴' => ' oi ',
        '红毛丹' => ' chom chom ',
        '姜' => ' gung ',
        '罗望子' => ' me thai ',
        '榴莲' => ' sau rieng ',
        '火龙果' => ' thanh long ',
        '菠萝蜜' => ' mit ',
        '椰子' => ' dua xiem ',
        '柠檬' => ' chanh ',
        '西瓜' => ' dua hau ',
        '辣椒' => ' ot ',

        // Korean
        '오렌지' => ' cam ',
        '망고' => ' xoai ',
        '바나나' => ' chuoi ',
        '사과' => ' tao ',
        '자몽' => ' buoi ',
        '포멜로' => ' buoi ',
        '포도' => ' nho ',
        '캐슈' => ' hat dieu ',
        '해바라기씨' => ' hat huong duong ',
        '땅콩' => ' dau phong ',
        '쌀' => ' gao ',
        '커피' => ' ca phe ',
        '후추' => ' tieu den ',
        '망고스틴' => ' mang cut ',
        '아보카도' => ' bo sap ',
        '딸기' => ' dau tay ',
        '구아바' => ' oi ',
        '람부탄' => ' chom chom ',
        '생강' => ' gung ',
        '타마린드' => ' me thai ',
        '두리안' => ' sau rieng ',
        '용과' => ' thanh long ',
        '잭프루트' => ' mit ',
        '코코넛' => ' dua xiem ',
        '레몬' => ' chanh ',
        '수박' => ' dua hau ',
        '고추' => ' ot ',

        // Thai
        'ส้ม' => ' cam ',
        'มะม่วง' => ' xoai ',
        'กล้วย' => ' chuoi ',
        'แอปเปิล' => ' tao ',
        'ส้มโอ' => ' buoi ',
        'องุ่น' => ' nho ',
        'เม็ดมะม่วงหิมพานต์' => ' hat dieu ',
        'เมล็ดทานตะวัน' => ' hat huong duong ',
        'ถั่วลิสง' => ' dau phong ',
        'ข้าว' => ' gao ',
        'กาแฟ' => ' ca phe ',
        'พริกไทยดำ' => ' tieu den ',
        'มังคุด' => ' mang cut ',
        'อะโวคาโด' => ' bo sap ',
        'สตรอว์เบอร์รี' => ' dau tay ',
        'ฝรั่ง' => ' oi ',
        'เงาะ' => ' chom chom ',
        'ขิง' => ' gung ',
        'มะขาม' => ' me thai ',
        'ทุเรียน' => ' sau rieng ',
        'แก้วมังกร' => ' thanh long ',
        'ขนุน' => ' mit ',
        'มะพร้าว' => ' dua xiem ',
        'มะนาว' => ' chanh ',
        'แตงโม' => ' dua hau ',
        'พริก' => ' ot ',

        // Arabic
        'برتقال' => ' cam ',
        'مانجو' => ' xoai ',
        'موز' => ' chuoi ',
        'تفاح' => ' tao ',
        'جريب فروت' => ' buoi ',
        'بوملي' => ' buoi ',
        'عنب' => ' nho ',
        'كاجو' => ' hat dieu ',
        'بذور دوار الشمس' => ' hat huong duong ',
        'فول سوداني' => ' dau phong ',
        'أرز' => ' gao ',
        'ارز' => ' gao ',
        'قهوة' => ' ca phe ',
        'فلفل أسود' => ' tieu den ',
        'فلفل اسود' => ' tieu den ',
        'مانجوستين' => ' mang cut ',
        'أفوكادو' => ' bo sap ',
        'افوكادو' => ' bo sap ',
        'فراولة' => ' dau tay ',
        'جوافة' => ' oi ',
        'رامبوتان' => ' chom chom ',
        'زنجبيل' => ' gung ',
        'تمر هندي' => ' me thai ',
        'دوريان' => ' sau rieng ',
        'فاكهة التنين' => ' thanh long ',
        'جاك فروت' => ' mit ',
        'جوز الهند' => ' dua xiem ',
        'ليمون' => ' chanh ',
        'بطيخ' => ' dua hau ',
        'فلفل' => ' ot ',

        // French / German search words
        'mangue' => ' xoai ',
        'banane' => ' chuoi ',
        'pomme' => ' tao ',
        'pamplemousse' => ' buoi ',
        'raisin' => ' nho ',
        'noix de cajou' => ' hat dieu ',
        'graines de tournesol' => ' hat huong duong ',
        'cacahuete' => ' dau phong ',
        'cacahuète' => ' dau phong ',
        'riz' => ' gao ',
        'cafe' => ' ca phe ',
        'café' => ' ca phe ',
        'poivre noir' => ' tieu den ',
        'mangoustan' => ' mang cut ',
        'avocat' => ' bo sap ',
        'fraise' => ' dau tay ',
        'goyave' => ' oi ',
        'gingembre' => ' gung ',
        'tamarin' => ' me thai ',
        'fruit du dragon' => ' thanh long ',
        'jacquier' => ' mit ',
        'noix de coco' => ' dua xiem ',
        'citron' => ' chanh ',
        'pasteque' => ' dua hau ',
        'pastèque' => ' dua hau ',
        'piment' => ' ot ',
        'apfel' => ' tao ',
        'traube' => ' nho ',
        'sonnenblumenkerne' => ' hat huong duong ',
        'erdnuss' => ' dau phong ',
        'reis' => ' gao ',
        'kaffee' => ' ca phe ',
        'schwarzer pfeffer' => ' tieu den ',
        'mangostan' => ' mang cut ',
        'erdbeere' => ' dau tay ',
        'guave' => ' oi ',
        'ingwer' => ' gung ',
        'tamarinde' => ' me thai ',
        'drachenfrucht' => ' thanh long ',
        'jackfrucht' => ' mit ',
        'kokosnuss' => ' dua xiem ',
        'zitrone' => ' chanh ',
        'limette' => ' chanh ',
        'wassermelone' => ' dua hau ',
    ];
}

    private function looksLikeProductQuestion(
    string $message,
    string $normalized
): bool
{
    $searchText = $this->productSearchText($message . ' ' . $normalized);

    return $this->hasAny($normalized . ' ' . $searchText, [
        'gia', 'giá', 'bao nhieu', 'bao nhiêu',
        'price', 'precio', 'prix', 'preis', '価格', '値段', 'ราคา', 'السعر',
        'ton kho', 'tồn kho',
        'stock', 'inventory', '在庫', '库存', '재고', 'สต็อก', 'المخزون',
        'con hang', 'còn hàng',
        'san pham', 'sản phẩm',
        'product', 'item', '商品', '产品', '상품', 'สินค้า', 'منتج',
        'nong san', 'nông sản',
        'nguon goc', 'nguồn gốc',
        'origin', '産地', '来源', '원산지', 'แหล่งที่มา', 'المنشأ',
        'xuat xu', 'xuất xứ',
        'dia ly', 'địa lý',
        'dia diem', 'địa điểm',
        'trai cay', 'trái cây',   
        'fruit', '果物', '水果', '과일', 'ผลไม้', 'فاكهة',
        'hoa qua', 'hoa quả',     
        'rau cu', 'rau củ',
        'vegetable', '野菜', '蔬菜', '채소', 'ผัก', 'خضار',
        'kg',
    ]);
}

private function productNotFoundReply(string $message, string $language = 'vi'): string
{
    $message = trim($message);

    $quoted = '"' . $message . '"';

    return [
        'vi' => 'Tôi chưa tìm thấy sản phẩm ' . $quoted . ' trong dữ liệu cửa hàng.'
            . "\nTôi ưu tiên dữ liệu nội bộ: giá bán, tồn kho và nguồn gốc sản phẩm."
            . "\nNếu bạn hỏi giá thị trường nông sản, tôi sẽ lọc các nguồn web liên quan đến nông nghiệp.",
        'en' => 'I could not find ' . $quoted . ' in the store data.'
            . "\nYou can ask about product names, prices, stock, origin, orders, and shipping.",
        'ja' => $quoted . ' は店舗データの商品として見つかりませんでした。'
            . "\n商品名、価格、在庫、産地、注文、配送について質問できます。",
        'ko' => $quoted . ' 상품을 매장 데이터에서 찾지 못했습니다.'
            . "\n상품명, 가격, 재고, 원산지, 주문 및 배송을 질문할 수 있습니다.",
        'zh' => '我没有在店铺数据中找到 ' . $quoted . '。'
            . "\n你可以询问商品名称、价格、库存、产地、订单和配送。",
        'de' => 'Ich konnte ' . $quoted . ' nicht in den Shopdaten finden.'
            . "\nSie können nach Produktnamen, Preisen, Bestand, Herkunft, Bestellungen und Versand fragen.",
        'fr' => 'Je n’ai pas trouvé ' . $quoted . ' dans les données du magasin.'
            . "\nVous pouvez demander les produits, prix, stocks, origines, commandes et livraisons.",
        'th' => 'ไม่พบสินค้า ' . $quoted . ' ในข้อมูลร้านค้า'
            . "\nคุณสามารถถามชื่อสินค้า ราคา สต็อก แหล่งที่มา คำสั่งซื้อ และการจัดส่งได้",
        'ar' => 'لم أجد ' . $quoted . ' في بيانات المتجر.'
            . "\nيمكنك السؤال عن المنتجات والأسعار والمخزون والمنشأ والطلبات والشحن.",
    ][$language] ?? 'I could not find ' . $quoted . ' in the store data.';
}

private function isGreetingOnly(string $normalized): bool
{
    $normalized = $this->chatbotModel->normalizeText($normalized);

    $greetings = ['chao', 'xin chao', 'hello', 'hi', 'hey'];

    foreach ($greetings as $greet) {
        if (str_contains($normalized, $greet)) {
            return true;
        }
    }

    return false;
}

private function renderTrainingAnswer(string $answer): string
{
    $replacements = [
        '{product_count}' => number_format(
            (int)$this->fetchColumnSafe(
                'SELECT COUNT(*) FROM product'
            ),
            0,
            ',',
            '.'
        ),

        '{total_stock}' => number_format(
            (float)$this->fetchColumnSafe(
                'SELECT COALESCE(SUM(quantity), 0) FROM inventory'
            ),
            0,
            ',',
            '.'
        ),

        '{pending_orders}' => number_format(
            (int)$this->fetchColumnSafe(
                "SELECT COUNT(*) FROM orders WHERE status = 'pending'"
            ),
            0,
            ',',
            '.'
        ),

        '{payment_methods}' => 'COD, chuyển khoản ngân hàng, Visa/MasterCard hoặc SWIFT/TT',
    ];

    return strtr($answer, $replacements);
}

private function greetingReply(string $language): string
{
    return [
        'vi' =>
            'Xin chào. Tôi hỗ trợ tra cứu sản phẩm, giá, tồn kho, nguồn gốc và đơn hàng. '
            . 'Nếu cần giá thị trường, tôi chỉ lọc nguồn web liên quan đến nông sản.',

        'en' =>
            'Hello. I can help with products, prices, inventory, origins, and orders.',

        'ja' =>
            'こんにちは。商品、価格、在庫、産地、注文などをサポートします。',

        'ko' =>
            '안녕하세요. 상품, 가격, 재고, 원산지 및 주문 조회를 도와드립니다.',

        'zh' =>
            '你好，我可以帮助查询商品、价格、库存、产地和订单。',

        'de' =>
            'Hallo. Ich helfe bei Produkten, Preisen, Bestand, Herkunft und Bestellungen.',

        'fr' =>
            'Bonjour. Je peux aider avec les produits, les prix, le stock, l’origine et les commandes.',

        'th' =>
            'สวัสดี ฉันช่วยตรวจสอบสินค้า ราคา สต็อก แหล่งที่มา และคำสั่งซื้อได้',

        'ar' =>
            'مرحباً. يمكنني المساعدة في المنتجات والأسعار والمخزون والمنشأ والطلبات.',
    ][$language] ?? 'Xin chào.';
}

private function defaultReply(string $language = 'vi'): string
{
    return [
        'vi' =>
            "🥬 Xin lỗi, tôi chưa hiểu rõ yêu cầu.\n"
            . "Bạn có thể hỏi về:\n"
            . "- giá xoài hôm nay\n"
            . "- tồn kho dưa hấu\n"
            . "- giá thị trường cà phê\n"
            . "- đơn #54\n"
            . "- vận chuyển",
        'en' =>
            "🥬 Sorry, I did not understand the request clearly.\n"
            . "You can ask about:\n"
            . "- mango price\n"
            . "- watermelon stock\n"
            . "- coffee market price\n"
            . "- order #54\n"
            . "- shipping",
        'ja' =>
            "🥬 すみません、内容を十分に理解できませんでした。\n"
            . "次のように質問できます:\n"
            . "- オレンジの価格\n"
            . "- マンゴーの在庫\n"
            . "- コーヒーの市場価格\n"
            . "- 注文 #54\n"
            . "- 配送",
        'ko' =>
            "🥬 죄송합니다. 요청을 정확히 이해하지 못했습니다.\n"
            . "다음처럼 질문할 수 있습니다:\n"
            . "- 망고 가격\n"
            . "- 수박 재고\n"
            . "- 커피 시장 가격\n"
            . "- 주문 #54\n"
            . "- 배송",
        'zh' =>
            "🥬 抱歉，我没有完全理解你的请求。\n"
            . "你可以这样问:\n"
            . "- 橙子的价格\n"
            . "- 芒果库存\n"
            . "- 咖啡市场价格\n"
            . "- 订单 #54\n"
            . "- 配送",
        'de' =>
            "🥬 Entschuldigung, ich habe die Anfrage nicht klar verstanden.\n"
            . "Sie können zum Beispiel fragen:\n"
            . "- Preis für Mango\n"
            . "- Bestand von Wassermelone\n"
            . "- Marktpreis für Kaffee\n"
            . "- Bestellung #54\n"
            . "- Versand",
        'fr' =>
            "🥬 Désolé, je n’ai pas bien compris la demande.\n"
            . "Vous pouvez demander par exemple:\n"
            . "- prix de la mangue\n"
            . "- stock de pastèque\n"
            . "- prix du café sur le marché\n"
            . "- commande #54\n"
            . "- livraison",
        'th' =>
            "🥬 ขออภัย ฉันยังไม่เข้าใจคำขออย่างชัดเจน\n"
            . "คุณสามารถถามเช่น:\n"
            . "- ราคามะม่วง\n"
            . "- สต็อกแตงโม\n"
            . "- ราคาตลาดกาแฟ\n"
            . "- คำสั่งซื้อ #54\n"
            . "- การจัดส่ง",
        'ar' =>
            "🥬 عذراً، لم أفهم الطلب بوضوح.\n"
            . "يمكنك السؤال عن:\n"
            . "- سعر المانجو\n"
            . "- مخزون البطيخ\n"
            . "- سعر القهوة في السوق\n"
            . "- الطلب #54\n"
            . "- الشحن",
    ][$language] ?? "Sorry, I did not understand the request clearly.";
}

private function hasAny(
    string $normalizedMessage,
    array $terms
): bool
{
    if ($normalizedMessage === '') {
        return false;
    }

    $messageVariants = array_filter(array_unique([
        $normalizedMessage,
        $this->chatbotModel->normalizeText($normalizedMessage),
        $this->productSearchText($normalizedMessage),
    ]));

    foreach ($terms as $term) {
        $termVariants = array_filter(array_unique([
            trim((string)$term),
            $this->chatbotModel->normalizeText((string)$term),
            $this->productSearchText((string)$term),
        ]));

        foreach ($messageVariants as $messageVariant) {
            foreach ($termVariants as $termVariant) {
                if ($termVariant !== '' && str_contains($messageVariant, $termVariant)) {
                    return true;
                }
            }
        }
    }

    return false;
}

private function extractOrderId(string $message): int
{
    $normalized =
        $this->chatbotModel->normalizeText($message);

    if (
        preg_match(
            '/(?:don|order|hoa don|#)\s*#?(\d{1,8})/u',
            $normalized,
            $matches
        )
    ) {
        return (int)$matches[1];
    }

    return 0;
}

private function findVisibleOrder(int $orderId): ?array
{
    $params = [$orderId];

    $where = 'id = ?';

    $role =
        (string)($_SESSION['user_role'] ?? 'user');

    $isAdminLike = in_array(
        $role,
        [
            'admin',
            'manager',
            'staff',
            'warehouse_staff',
            'export_staff',
        ],
        true
    );

    if (!$isAdminLike) {
        if (empty($_SESSION['user_id'])) {
            return null;
        }

        $where .= ' AND user_id = ?';

        $params[] = (int)$_SESSION['user_id'];
    }

    $stmt = $this->conn->prepare(
        "SELECT * FROM orders
         WHERE {$where}
         LIMIT 1"
    );

    $stmt->execute($params);

    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    return $order ?: null;
}

private function orderStatusLabel(string $status): string
{
    return [
        'pending' => 'Chờ xử lý',
        'confirmed' => 'Đã xác nhận',
        'preparing' => 'Đang chuẩn bị hàng',
        'packed' => 'Đã đóng gói',
        'shipped' => 'Đang giao',
        'completed' => 'Hoàn tất',
        'cancelled' => 'Đã hủy',
    ][$status] ?? ucfirst($status);
}

private function paymentStatusLabel(string $status): string
{
    return [
        'paid' => 'Đã thanh toán',
        'partial' => 'Đã cọc',
        'pending' => 'Chưa thanh toán',
        'awaiting_confirmation' => 'Chờ xác nhận',
        'refunded' => 'Đã hoàn tiền',
        'cancelled' => 'Không thu tiền',
    ][$status] ?? ucfirst($status);
}

private function normalizeLanguage($language): string
{
    $language = strtolower(
        trim((string)$language)
    );

    return array_key_exists(
        $language,
        $this->languages
    )
        ? $language
        : 'vi';
}

private function localizeReply(string $reply, string $language): string
{
    if ($language === 'vi' || trim($reply) === '') {
        return $reply;
    }

    $maps = [
        'en' => [
            'Tôi tìm thấy sản phẩm phù hợp:' => 'I found a matching product:',
            'Tôi tìm thấy các sản phẩm phù hợp nhất:' => 'I found the best matching products:',
            'giá đang cập nhật' => 'price is being updated',
            'đ/kg' => ' VND/kg',
            'Sản phẩm nổi bật' => 'Featured product',
            ', tồn ' => ', stock ',
            ', nguồn gốc ' => ', origin ',
            'Giao tiêu chuẩn' => 'Standard delivery',
            'Có thể giao nhanh trong ngày' => 'Same-day delivery is available',
            'Sản phẩm sắp hết hàng' => 'Low stock',
            'Xem:' => 'View:',
            'Hiện cửa hàng có ' => 'The store currently has ',
            ' sản phẩm và ' => ' products and ',
            ' kg tồn kho.' => ' kg in stock.',
            'Bạn có thể hỏi như:' => 'You can ask:',
            'Sản phẩm tồn kho thấp:' => 'Low-stock products:',
            'Xin lỗi, tôi chưa hiểu rõ yêu cầu.' => 'Sorry, I did not understand the request clearly.',
            'Bạn có thể hỏi về:' => 'You can ask about:',
            'trái cây' => 'fruit',
            'khuyến mãi, flash sale, mã giảm giá' => 'promotions, flash sales, discount codes',
            'vận chuyển' => 'shipping',
        ],
        'ja' => [
            'Tôi tìm thấy sản phẩm phù hợp:' => '該当する商品が見つかりました:',
            'Tôi tìm thấy các sản phẩm phù hợp nhất:' => '関連性の高い商品が見つかりました:',
            'giá đang cập nhật' => '価格更新中',
            'đ/kg' => ' VND/kg',
            'Sản phẩm nổi bật' => 'おすすめ商品',
            ', tồn ' => ', 在庫 ',
            ', nguồn gốc ' => ', 産地 ',
            'Giao tiêu chuẩn' => '通常配送',
            'Có thể giao nhanh trong ngày' => '当日配送可能',
            'Sản phẩm sắp hết hàng' => '在庫わずか',
            'Xem:' => '表示:',
            'Hiện cửa hàng có ' => '現在、店舗には ',
            ' sản phẩm và ' => ' 商品、在庫 ',
            ' kg tồn kho.' => ' kg があります。',
            'Bạn có thể hỏi như:' => '例:',
            'Sản phẩm tồn kho thấp:' => '在庫が少ない商品:',
        ],
        'ko' => [
            'Tôi tìm thấy sản phẩm phù hợp:' => '일치하는 상품을 찾았습니다:',
            'Tôi tìm thấy các sản phẩm phù hợp nhất:' => '가장 적합한 상품을 찾았습니다:',
            'giá đang cập nhật' => '가격 업데이트 중',
            'đ/kg' => ' VND/kg',
            'Sản phẩm nổi bật' => '추천 상품',
            ', tồn ' => ', 재고 ',
            ', nguồn gốc ' => ', 원산지 ',
            'Giao tiêu chuẩn' => '일반 배송',
            'Có thể giao nhanh trong ngày' => '당일 배송 가능',
            'Sản phẩm sắp hết hàng' => '재고 부족',
            'Xem:' => '보기:',
            'Hiện cửa hàng có ' => '현재 매장에는 ',
            ' sản phẩm và ' => '개 상품과 ',
            ' kg tồn kho.' => ' kg 재고가 있습니다.',
            'Bạn có thể hỏi như:' => '예시 질문:',
            'Sản phẩm tồn kho thấp:' => '재고가 낮은 상품:',
        ],
        'zh' => [
            'Tôi tìm thấy sản phẩm phù hợp:' => '找到匹配商品:',
            'Tôi tìm thấy các sản phẩm phù hợp nhất:' => '找到最匹配的商品:',
            'giá đang cập nhật' => '价格更新中',
            'đ/kg' => ' VND/kg',
            'Sản phẩm nổi bật' => '精选商品',
            ', tồn ' => ', 库存 ',
            ', nguồn gốc ' => ', 产地 ',
            'Giao tiêu chuẩn' => '标准配送',
            'Có thể giao nhanh trong ngày' => '可当日配送',
            'Sản phẩm sắp hết hàng' => '库存较低',
            'Xem:' => '查看:',
            'Hiện cửa hàng có ' => '当前店铺有 ',
            ' sản phẩm và ' => ' 个商品，库存 ',
            ' kg tồn kho.' => ' kg。',
            'Bạn có thể hỏi như:' => '你可以这样问:',
            'Sản phẩm tồn kho thấp:' => '低库存商品:',
        ],
        'de' => [
            'Tôi tìm thấy sản phẩm phù hợp:' => 'Ich habe ein passendes Produkt gefunden:',
            'Tôi tìm thấy các sản phẩm phù hợp nhất:' => 'Ich habe die passendsten Produkte gefunden:',
            'giá đang cập nhật' => 'Preis wird aktualisiert',
            'đ/kg' => ' VND/kg',
            'Sản phẩm nổi bật' => 'Empfohlenes Produkt',
            ', tồn ' => ', Bestand ',
            ', nguồn gốc ' => ', Herkunft ',
            'Giao tiêu chuẩn' => 'Standardversand',
            'Có thể giao nhanh trong ngày' => 'Lieferung am selben Tag möglich',
            'Sản phẩm sắp hết hàng' => 'Niedriger Bestand',
            'Xem:' => 'Ansehen:',
            'Hiện cửa hàng có ' => 'Der Shop hat derzeit ',
            ' sản phẩm và ' => ' Produkte und ',
            ' kg tồn kho.' => ' kg Bestand.',
            'Bạn có thể hỏi như:' => 'Sie können fragen:',
            'Sản phẩm tồn kho thấp:' => 'Produkte mit niedrigem Bestand:',
        ],
        'fr' => [
            'Tôi tìm thấy sản phẩm phù hợp:' => 'J’ai trouvé un produit correspondant :',
            'Tôi tìm thấy các sản phẩm phù hợp nhất:' => 'J’ai trouvé les produits les plus pertinents :',
            'giá đang cập nhật' => 'prix en cours de mise à jour',
            'đ/kg' => ' VND/kg',
            'Sản phẩm nổi bật' => 'Produit vedette',
            ', tồn ' => ', stock ',
            ', nguồn gốc ' => ', origine ',
            'Giao tiêu chuẩn' => 'Livraison standard',
            'Có thể giao nhanh trong ngày' => 'Livraison le jour même possible',
            'Sản phẩm sắp hết hàng' => 'Stock faible',
            'Xem:' => 'Voir :',
            'Hiện cửa hàng có ' => 'Le magasin compte actuellement ',
            ' sản phẩm và ' => ' produits et ',
            ' kg tồn kho.' => ' kg en stock.',
            'Bạn có thể hỏi như:' => 'Vous pouvez demander :',
            'Sản phẩm tồn kho thấp:' => 'Produits à faible stock :',
        ],
        'th' => [
            'Tôi tìm thấy sản phẩm phù hợp:' => 'พบสินค้าที่ตรงกับคำถาม:',
            'Tôi tìm thấy các sản phẩm phù hợp nhất:' => 'พบสินค้าที่เหมาะสมที่สุด:',
            'giá đang cập nhật' => 'กำลังอัปเดตราคา',
            'đ/kg' => ' VND/kg',
            'Sản phẩm nổi bật' => 'สินค้าแนะนำ',
            ', tồn ' => ', สต็อก ',
            ', nguồn gốc ' => ', แหล่งที่มา ',
            'Giao tiêu chuẩn' => 'จัดส่งมาตรฐาน',
            'Có thể giao nhanh trong ngày' => 'จัดส่งภายในวันเดียวได้',
            'Sản phẩm sắp hết hàng' => 'สินค้าใกล้หมด',
            'Xem:' => 'ดู:',
            'Hiện cửa hàng có ' => 'ตอนนี้ร้านมีสินค้า ',
            ' sản phẩm và ' => ' รายการ และมีสต็อก ',
            ' kg tồn kho.' => ' กก.',
            'Bạn có thể hỏi như:' => 'คุณสามารถถามเช่น:',
            'Sản phẩm tồn kho thấp:' => 'สินค้าสต็อกต่ำ:',
        ],
        'ar' => [
            'Tôi tìm thấy sản phẩm phù hợp:' => 'وجدت منتجاً مناسباً:',
            'Tôi tìm thấy các sản phẩm phù hợp nhất:' => 'وجدت أفضل المنتجات المطابقة:',
            'giá đang cập nhật' => 'يتم تحديث السعر',
            'đ/kg' => ' VND/kg',
            'Sản phẩm nổi bật' => 'منتج مميز',
            ', tồn ' => ', المخزون ',
            ', nguồn gốc ' => ', المنشأ ',
            'Giao tiêu chuẩn' => 'شحن قياسي',
            'Có thể giao nhanh trong ngày' => 'التوصيل في نفس اليوم متاح',
            'Sản phẩm sắp hết hàng' => 'المخزون منخفض',
            'Xem:' => 'عرض:',
            'Hiện cửa hàng có ' => 'يحتوي المتجر حالياً على ',
            ' sản phẩm và ' => ' منتجاً و ',
            ' kg tồn kho.' => ' كجم في المخزون.',
            'Bạn có thể hỏi như:' => 'يمكنك أن تسأل:',
            'Sản phẩm tồn kho thấp:' => 'منتجات منخفضة المخزون:',
        ],
    ];

    return strtr($reply, $maps[$language] ?? $maps['en']);
}

private function reply(
    string $text,
    string $language,
    string $source = 'hybrid'
): void
{
    echo json_encode([
        'reply' => $text,
        'language' => $language,
        'source' => $source,
        'hybrid' => true,
        'online' => $source === 'online_market',
    ], JSON_UNESCAPED_UNICODE);
}

private function emptyMessage(string $language): string
{
    return [
        'vi' =>
            'Bạn cần hỗ trợ gì về sản phẩm, tồn kho, đơn hàng hoặc giá nông sản?',

        'en' =>
            'Please enter a question about products, orders, or inventory.',

        'ja' =>
            '商品、注文、在庫について質問してください。',

        'ko' =>
            '상품, 주문 또는 재고에 대해 질문해주세요.',

        'zh' =>
            '请输入关于商品、订单或库存的问题。',

        'de' =>
            'Bitte geben Sie eine Frage zu Produkten, Bestellungen oder Bestand ein.',

        'fr' =>
            'Veuillez saisir une question sur les produits, les commandes ou le stock.',

        'th' =>
            'โปรดพิมพ์คำถามเกี่ยวกับสินค้า คำสั่งซื้อ หรือสต็อก',

        'ar' =>
            'يرجى إدخال سؤال عن المنتجات أو الطلبات أو المخزون.',
    ][$language] ?? 'Bạn cần hỗ trợ gì?';
}

private function systemError(string $language): string
{
    return [
        'vi' =>
            'Kênh hỗ trợ đang gặp lỗi khi đọc dữ liệu. Vui lòng thử lại sau.',

        'en' =>
            'The assistant cannot access data right now. Please try again later.',

        'ja' =>
            'データを読み取れません。後でもう一度お試しください。',

        'ko' =>
            '현재 데이터를 읽을 수 없습니다. 잠시 후 다시 시도하세요.',

        'zh' =>
            '暂时无法读取数据，请稍后再试。',

        'de' =>
            'Der Assistent kann derzeit nicht auf Daten zugreifen. Bitte versuchen Sie es später erneut.',

        'fr' =>
            'L’assistant ne peut pas accéder aux données pour le moment. Veuillez réessayer plus tard.',

        'th' =>
            'ตอนนี้ผู้ช่วยไม่สามารถเข้าถึงข้อมูลได้ กรุณาลองใหม่ภายหลัง',

        'ar' =>
            'لا يمكن للمساعد الوصول إلى البيانات الآن. يرجى المحاولة لاحقاً.',
    ][$language] ?? 'Hệ thống đang lỗi.';
}

private function fetchColumnSafe(string $sql)
{
    try {
        return $this->conn
            ->query($sql)
            ->fetchColumn() ?: 0;
    } catch (Throwable $e) {
        return 0;
    }
}

private function fetchAllSafe(string $sql): array
{
    try {
        return $this->conn
            ->query($sql)
            ->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        return [];
    }
}
private function wantsOnlineMarketPrice(string $message): bool
{
    $message = mb_strtolower($message, 'UTF-8');

    $hasPriceIntent = false;
    foreach (['giá', 'bao nhiêu', 'thị trường', 'hôm nay', 'market price'] as $intent) {
        if (str_contains($message, $intent)) {
            $hasPriceIntent = true;
            break;
        }
    }

    if (!$hasPriceIntent) {
        return false;
    }

    $keywords = [

        'giá',
        'bao nhiêu',
        'thị trường',
        'hôm nay',
        'nông sản',
        'trái cây',
        'rau củ',

        'cam',
        'quýt',
        'xoài',
        'dưa hấu',
        'dừa',
        'chuối',
        'sầu riêng',
        'mít',
        'chanh',
        'ổi',
        'bưởi',

        'orange',
        'fruit',
        'market price',
    ];

    foreach ($keywords as $keyword) {

        if (str_contains($message, $keyword)) {
            return true;
        }
    }

    return false;
}
private function filterAgricultureResults(
    array $results,
    string $originalMessage
): array {

    $filtered = [];

    foreach ($results as $result) {

        if (
            $this->resultLooksAgricultural(
                $result,
                $originalMessage
            )
        ) {
            $filtered[] = $result;
        }
    }

    return $filtered;
}
}
