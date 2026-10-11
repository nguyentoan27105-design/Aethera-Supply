<?php

class OnlineSearchModel
{
    private PDO $conn;
    private int $ttlSeconds = 21600;
    private string $cacheVersion = 'v3';

    public function __construct(PDO $db)
    {
        $this->conn = $db;
        $this->ensureSchema();
    }
public function search(
    string $query,
    string $language = 'vi'
): array
{
    try {

        $query = $this->cleanQuery($query);

        $language = $this->normalizeLanguage(
            $language
        );

        if (
            $query === ''
            || $this->textLength($query) < 3
        ) {
            return [
                'query' => $query,
                'results' => [],
                'cached' => false,
            ];
        }

        $cached = $this->readCache(
            $query,
            $language
        );

        if ($cached !== null) {
            $cached['cached'] = true;
            return $cached;
        }

        $results = array_merge(
            $this->searchDuckDuckGo(
                $query,
                $language
            ),
            $this->searchBing(
                $query,
                $language
            ),
            $this->searchWikipedia(
                $query,
                $language
            )
        );

        $results = $this->dedupeResults(
            $results
        );

        $payload = [
            'query' => $query,
            'language' => $language,
            'results' => array_slice(
                $results,
                0,
                5
            ),
            'cached' => false,
            'searched_at' => date(
                'Y-m-d H:i:s'
            ),
        ];

        $this->writeCache(
            $query,
            $language,
            $payload
        );

        return $payload;

    } catch (Throwable $e) {

        error_log(
            'OnlineSearchModel error: '
            . $e->getMessage()
        );

        return [
            'query' => $query,
            'language' => $language,
            'results' => [],
            'cached' => false,
            'error' => true,
        ];
    }
}

    private function ensureSchema(): void
    {
        try {
            $this->conn->exec("
                CREATE TABLE IF NOT EXISTS chatbot_web_cache (
                    id INT NOT NULL AUTO_INCREMENT,
                    query_hash CHAR(64) NOT NULL,
                    query_text VARCHAR(255) NOT NULL,
                    language VARCHAR(10) DEFAULT 'vi',
                    source VARCHAR(40) DEFAULT 'online_search',
                    result_json MEDIUMTEXT NULL,
                    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                    expires_at TIMESTAMP NULL DEFAULT NULL,
                    PRIMARY KEY (id),
                    UNIQUE KEY uniq_chatbot_web_cache_query (query_hash, language),
                    KEY idx_chatbot_web_cache_expires (expires_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        } catch (Throwable $e) {
            error_log('Cannot create chatbot_web_cache: ' . $e->getMessage());
        }
    }

    private function readCache(string $query, string $language): ?array
    {
        try {
            $stmt = $this->conn->prepare("
                SELECT result_json
                FROM chatbot_web_cache
                WHERE query_hash = ?
                  AND language = ?
                  AND (expires_at IS NULL OR expires_at > NOW())
                LIMIT 1
            ");
            $stmt->execute([$this->cacheHash($query, $language), $language]);
            $json = $stmt->fetchColumn();
            if (!$json) {
                return null;
            }
            $payload = json_decode((string)$json, true);
            return is_array($payload) ? $payload : null;
        } catch (Throwable $e) {
            error_log('Cannot read chatbot web cache: ' . $e->getMessage());
            return null;
        }
    }

    private function writeCache(string $query, string $language, array $payload): void
    {
        try {
            $stmt = $this->conn->prepare("
                INSERT INTO chatbot_web_cache (query_hash, query_text, language, result_json, expires_at)
                VALUES (?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL ? SECOND))
                ON DUPLICATE KEY UPDATE
                    query_text = VALUES(query_text),
                    result_json = VALUES(result_json),
                    created_at = CURRENT_TIMESTAMP,
                    expires_at = VALUES(expires_at)
            ");
            $stmt->execute([
                $this->cacheHash($query, $language),
                $this->limitText($query, 255),
                $language,
                json_encode($payload, JSON_UNESCAPED_UNICODE),
                $this->ttlSeconds,
            ]);
        } catch (Throwable $e) {
            error_log('Cannot write chatbot web cache: ' . $e->getMessage());
        }
    }

    private function searchWikipedia(string $query, string $language): array
    {
        $wikiLanguage = [
            'vi' => 'vi',
            'en' => 'en',
            'ja' => 'ja',
            'ko' => 'ko',
            'zh' => 'zh',
        ][$language] ?? 'vi';

        $url = 'https://' . $wikiLanguage . '.wikipedia.org/w/api.php?' . http_build_query([
            'action' => 'query',
            'format' => 'json',
            'list' => 'search',
            'utf8' => 1,
            'srlimit' => 3,
            'srsearch' => $query,
        ]);

        $body = $this->fetchUrl($url, $language);
        if ($body === '') {
            return [];
        }

        $data = json_decode($body, true);
        $rows = $data['query']['search'] ?? [];
        if (!is_array($rows)) {
            return [];
        }

        $results = [];
        foreach ($rows as $row) {
            $title = $this->cleanHtml((string)($row['title'] ?? ''));
            if ($title === '') {
                continue;
            }
            $results[] = [
                'title' => $title,
                'snippet' => $this->limitText($this->cleanHtml((string)($row['snippet'] ?? '')), 260),
                'url' => 'https://' . $wikiLanguage . '.wikipedia.org/wiki/' . rawurlencode(str_replace(' ', '_', $title)),
                'source' => 'Wikipedia',
            ];
        }

        return $results;
    }

    private function searchDuckDuckGo(string $query, string $language): array
    {
        $region = [
            'vi' => 'vn-vi',
            'en' => 'us-en',
            'ja' => 'jp-ja',
            'ko' => 'kr-ko',
            'zh' => 'cn-zh',
        ][$language] ?? 'wt-wt';

        $url = 'https://duckduckgo.com/html/?' . http_build_query([
            'q' => $query,
            'kl' => $region,
        ]);

        $html = $this->fetchUrl($url, $language);
        if ($html === '') {
            return [];
        }

        return $this->parseDuckDuckGoHtml($html);
    }

    private function parseDuckDuckGoHtml(string $html): array
    {
        $results = [];
        preg_match_all('/<a[^>]+class="[^"]*result__a[^"]*"[^>]+href="([^"]+)"[^>]*>(.*?)<\/a>/is', $html, $links, PREG_SET_ORDER);
        preg_match_all('/<a[^>]+class="[^"]*result__snippet[^"]*"[^>]*>(.*?)<\/a>/is', $html, $snippets);

        foreach ($links as $index => $link) {
            $title = $this->cleanHtml($link[2] ?? '');
            $url = $this->decodeDuckDuckGoUrl(html_entity_decode((string)($link[1] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            if ($title === '' || $url === '') {
                continue;
            }

            $snippet = $this->cleanHtml($snippets[1][$index] ?? '');
            $results[] = [
                'title' => $this->limitText($title, 140),
                'snippet' => $this->limitText($snippet, 260),
                'url' => $url,
                'source' => parse_url($url, PHP_URL_HOST) ?: 'Web',
            ];

            if (count($results) >= 5) {
                break;
            }
        }

        return $results;
    }

    private function searchBing(string $query, string $language): array
    {
        $market = [
            'vi' => 'vi-VN',
            'en' => 'en-US',
            'ja' => 'ja-JP',
            'ko' => 'ko-KR',
            'zh' => 'zh-CN',
        ][$language] ?? 'vi-VN';

        $url = 'https://www.bing.com/search?' . http_build_query([
            'q' => $query,
            'setlang' => $market,
            'cc' => strtoupper(substr($market, -2)) ?: 'VN',
        ]);

        $html = $this->fetchUrl($url, $language);
        if ($html === '') {
            return [];
        }

        return $this->parseBingHtml($html);
    }

    private function parseBingHtml(string $html): array
    {
        $results = [];
        preg_match_all('/<li class="b_algo".*?<h2[^>]*>\s*<a[^>]+href="([^"]+)"[^>]*>(.*?)<\/a>.*?(?:<p[^>]*>(.*?)<\/p>)?/is', $html, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $title = $this->cleanHtml($match[2] ?? '');
            $url = $this->decodeBingUrl(html_entity_decode((string)($match[1] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            if ($title === '' || $url === '') {
                continue;
            }

            $snippet = $this->cleanHtml($match[3] ?? '');
            $results[] = [
                'title' => $this->limitText($title, 140),
                'snippet' => $this->limitText($snippet, 260),
                'url' => $url,
                'source' => parse_url($url, PHP_URL_HOST) ?: 'Bing',
            ];

            if (count($results) >= 5) {
                break;
            }
        }

        return $results;
    }

private function fetchUrl(string $url, string $language): string
{
    // nếu hosting chặn curl
    if (!function_exists('curl_init')) {
        return $this->fetchUrlFallback($url);
    }

    try {
        $ch = curl_init($url);

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,

            // giảm timeout tránh treo chatbot
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_TIMEOUT => 5,

            // hosting local thường lỗi ssl
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,

            CURLOPT_USERAGENT =>
                'Mozilla/5.0 AetheraAgricultureBot/1.0',

            CURLOPT_HTTPHEADER => [
                'Accept: text/html,application/xhtml+xml,application/json;q=0.9,*/*;q=0.8',
                'Accept-Language: ' . $this->acceptLanguage($language),
            ],
        ]);

        $body = curl_exec($ch);

        $status = (int)curl_getinfo(
            $ch,
            CURLINFO_RESPONSE_CODE
        );

        $error = curl_error($ch);

        curl_close($ch);

        if ($body === false || $status >= 400) {

            if ($error !== '') {
                error_log(
                    'OnlineSearch error: ' . $error
                );
            }

            return '';
        }

        return is_string($body)
            ? $body
            : '';

    } catch (Throwable $e) {

        error_log(
            'fetchUrl exception: '
            . $e->getMessage()
        );

        return '';
    }
}


private function fetchUrlFallback(string $url): string
{
    try {

        $context = stream_context_create([
            'http' => [
                'timeout' => 5,
                'ignore_errors' => true,
                'header' =>
                    "User-Agent: Mozilla/5.0\r\n",
            ],

            // local xampp thường lỗi ssl
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
            ],
        ]);

        $body = @file_get_contents(
            $url,
            false,
            $context
        );

        return is_string($body)
            ? $body
            : '';

    } catch (Throwable $e) {

        error_log(
            'Fallback fetch error: '
            . $e->getMessage()
        );

        return '';
    }
}


    private function decodeBingUrl(string $url): string
    {
        if (str_starts_with($url, '//')) {
            $url = 'https:' . $url;
        }

        $parts = parse_url($url);
        if (!empty($parts['query'])) {
            parse_str($parts['query'], $query);
            if (!empty($query['u'])) {
                $encoded = (string)$query['u'];
                if (str_starts_with($encoded, 'a1')) {
                    $encoded = substr($encoded, 2);
                }
                $encoded = strtr($encoded, '-_', '+/');
                $padding = strlen($encoded) % 4;
                if ($padding > 0) {
                    $encoded .= str_repeat('=', 4 - $padding);
                }
                $decoded = base64_decode($encoded, true);
                if (is_string($decoded) && str_starts_with($decoded, 'http')) {
                    return $decoded;
                }
            }
        }

        return str_starts_with($url, 'http') ? $url : '';
    }

    private function dedupeResults(array $results): array
    {
        $seen = [];
        $clean = [];

        foreach ($results as $result) {
            $url = trim((string)($result['url'] ?? ''));
            $title = trim((string)($result['title'] ?? ''));
            if ($url === '' || $title === '') {
                continue;
            }

            $host = strtolower((string)(parse_url($url, PHP_URL_HOST) ?: ''));
            if (str_contains($host, 'google.')) {
                continue;
            }

            $key = $host . '|' . strtok($url, '?');
            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $clean[] = [
                'title' => $title,
                'snippet' => trim((string)($result['snippet'] ?? '')),
                'url' => $url,
                'source' => trim((string)($result['source'] ?? 'Web')) ?: 'Web',
            ];
        }

        return $clean;
    }

    private function cleanQuery(string $query): string
    {
        $query = strip_tags($query);
        $query = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $query) ?? $query;
        $query = trim(preg_replace('/\s+/u', ' ', $query) ?? $query);
        return $this->limitText($query, 180);
    }

    private function cleanHtml(string $value): string
    {
        $value = html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;
        return trim($value);
    }

    private function acceptLanguage(string $language): string
    {
        return [
            'vi' => 'vi-VN,vi;q=0.9,en;q=0.7',
            'en' => 'en-US,en;q=0.9',
            'ja' => 'ja-JP,ja;q=0.9,en;q=0.6',
            'ko' => 'ko-KR,ko;q=0.9,en;q=0.6',
            'zh' => 'zh-CN,zh;q=0.9,en;q=0.6',
        ][$language] ?? 'vi-VN,vi;q=0.9,en;q=0.7';
    }

    private function normalizeLanguage(string $language): string
    {
        $language = strtolower(trim($language));
        return in_array($language, ['vi', 'en', 'ja', 'ko', 'zh'], true) ? $language : 'vi';
    }

    private function cacheHash(string $query, string $language): string
    {
        $normalized = function_exists('mb_strtolower') ? mb_strtolower($query, 'UTF-8') : strtolower($query);
        return hash('sha256', $this->cacheVersion . '|' . $language . '|' . $normalized);
    }

    private function textLength(string $value): int
    {
        return function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
    }

    private function limitText(string $value, int $limit): string
    {
        if (function_exists('mb_strlen') && function_exists('mb_substr')) {
            return mb_strlen($value, 'UTF-8') > $limit ? mb_substr($value, 0, $limit, 'UTF-8') : $value;
        }
        return strlen($value) > $limit ? substr($value, 0, $limit) : $value;
    }
}
