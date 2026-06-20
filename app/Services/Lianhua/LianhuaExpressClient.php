<?php

namespace App\Services\Lianhua;

use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use RuntimeException;

class LianhuaExpressClient
{
    /** @var Client */
    protected $client;

    /** @var CookieJar */
    protected $cookieJar;

    /** @var bool */
    protected $loggedIn = false;

    /** @var string|null */
    protected $detectedListUrl;

    /** @var array */
    protected $detectedFilters = [];

    public function __construct(array $config = null)
    {
        $config = $config ?: config('lianhua_express');
        $baseUrl = rtrim((string) data_get($config, 'base_url', 'https://www.lianhua-ex.com'), '/');

        $this->cookieJar = new CookieJar();
        $this->client = new Client([
            'base_uri' => $baseUrl . '/',
            'cookies' => $this->cookieJar,
            'timeout' => 30,
            'connect_timeout' => 15,
            'http_errors' => false,
            'headers' => [
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
                'Accept' => 'text/html,application/json,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language' => 'zh-CN,zh;q=0.9',
            ],
        ]);
    }

    public function login($account = null, $password = null)
    {
        $account = trim((string) ($account ?: config('lianhua_express.account')));
        $password = trim((string) ($password ?: config('lianhua_express.password')));

        if ($account === '' || $password === '') {
            throw new RuntimeException('请在 .env 中配置 LIANHUA_ACCOUNT 与 LIANHUA_PASSWORD。');
        }

        $response = $this->client->post('Home/UserLogin/', [
            'query' => ['rnd' => (string) mt_rand()],
            'form_params' => [
                'CustomerAccount' => $account,
                'CustomerPwd' => $password,
            ],
            'headers' => [
                'X-Requested-With' => 'XMLHttpRequest',
                'Referer' => $this->absoluteUrl('/Home/Login'),
            ],
        ]);

        $body = (string) $response->getBody();
        $payload = json_decode($body, true);

        if (!is_array($payload)) {
            throw new RuntimeException('联华登录响应无法解析为 JSON。');
        }

        if (empty($payload['ExeResult'])) {
            $message = trim((string) data_get($payload, 'ErrorMessage', '登录失败'));
            throw new RuntimeException('联华登录失败：' . ($message !== '' ? $message : '未知错误'));
        }

        $this->loggedIn = true;

        return true;
    }

    /**
     * @return array<int, array{recipient:string,phone:string,tracking:string,shipping_method:string,status:string,raw:array}>
     */
    public function fetchShippedRecords()
    {
        if (!$this->loggedIn) {
            $this->login();
        }

        $pageHtml = $this->fetchStoragePreSearchPage();
        $records = $this->fetchRecordsWithDiscovery($pageHtml);
        if (!empty($records)) {
            return $this->filterShippedRecords($records);
        }

        $records = $this->parseHtmlTable($pageHtml);
        if (!empty($records)) {
            return $this->filterShippedRecords($records);
        }

        throw new RuntimeException(
            '未能从联华预报查询页获取已发货列表。请运行 php artisan lianhua:probe 探测接口，'
            . '并在 .env 中设置 LIANHUA_LIST_URL / LIANHUA_SHIPPED_* 参数。'
        );
    }

    public function discoverListEndpoint($html = null, $quick = false)
    {
        if (!$this->loggedIn) {
            $this->login();
        }

        $html = $html ?: $this->fetchStoragePreSearchPage();
        $maxCandidates = $quick ? 6 : (int) config('lianhua_express.probe_max_candidates', 10);
        $maxAttempts = $quick ? 24 : (int) config('lianhua_express.probe_max_attempts', 80);
        $candidates = array_slice($this->buildCandidateListUrls($html), 0, max(1, $maxCandidates));
        $filterSets = $this->buildShippedFilterSets($html, $quick);

        $best = [
            'url' => '',
            'filters' => [],
            'score' => 0,
            'row_count' => 0,
            'tracking_count' => 0,
            'sample' => [],
        ];

        $attempts = 0;

        foreach ($candidates as $url) {
            foreach ($filterSets as $filters) {
                if ($attempts >= $maxAttempts) {
                    break 2;
                }

                $attempts++;
                $result = $this->scoreEndpointResponse($url, $filters, true);
                if ($result['score'] <= $best['score']) {
                    continue;
                }

                $best = [
                    'url' => $url,
                    'filters' => $filters,
                    'score' => $result['score'],
                    'row_count' => $result['row_count'],
                    'tracking_count' => $result['tracking_count'],
                    'sample' => $result['sample'],
                ];

                if ($result['tracking_count'] > 0 && $result['score'] >= 25) {
                    break 2;
                }
            }
        }

        if (!$quick && ($best['tracking_count'] === 0 || $best['score'] < 25)) {
            $extended = $this->discoverListEndpointExtended($html, $best, $attempts, $maxAttempts);
            if ((int) data_get($extended, 'score', 0) > (int) $best['score']) {
                $best = $extended;
            }
        }

        if ($best['score'] > 0) {
            $this->detectedListUrl = $best['url'];
            $this->detectedFilters = $best['filters'];
            $this->saveDiscoveryCache($best['url'], $best['filters']);
        }

        $best['attempts'] = $attempts;

        return $best;
    }

    protected function discoverListEndpointExtended($html, array $best, &$attempts, $maxAttempts)
    {
        $candidates = array_slice($this->buildCandidateListUrls($html), 0, 15);
        $filterSets = $this->buildShippedFilterSets($html, false, true);

        foreach ($candidates as $url) {
            foreach ($filterSets as $filters) {
                if ($attempts >= $maxAttempts) {
                    return $best;
                }

                $attempts++;
                $result = $this->scoreEndpointResponse($url, $filters, true);
                if ($result['score'] <= $best['score']) {
                    continue;
                }

                $best = [
                    'url' => $url,
                    'filters' => $filters,
                    'score' => $result['score'],
                    'row_count' => $result['row_count'],
                    'tracking_count' => $result['tracking_count'],
                    'sample' => $result['sample'],
                ];

                if ($result['tracking_count'] > 0 && $result['score'] >= 25) {
                    return $best;
                }
            }
        }

        return $best;
    }

    protected function fetchRecordsWithDiscovery($pageHtml)
    {
        $configuredUrl = trim((string) config('lianhua_express.list_url'));
        if ($configuredUrl !== '') {
            $records = $this->fetchRecordsFromApi($configuredUrl);
            if (!empty($records)) {
                return $records;
            }
        }

        $cache = $this->loadDiscoveryCache();
        if (!empty($cache['url'])) {
            $records = $this->fetchRecordsFromApi($cache['url'], (array) data_get($cache, 'filters', []));
            if (!empty($records)) {
                $this->detectedListUrl = $cache['url'];
                $this->detectedFilters = (array) data_get($cache, 'filters', []);
                return $records;
            }
        }

        $htmlUrl = $this->resolveListUrlFromHtml($pageHtml);
        if ($htmlUrl !== '') {
            $records = $this->fetchRecordsFromApi($htmlUrl);
            if (!empty($records)) {
                $this->saveDiscoveryCache($htmlUrl, (array) config('lianhua_express.shipped_filter'));
                return $records;
            }
        }

        $discovery = $this->discoverListEndpoint($pageHtml);
        if (!empty($discovery['url']) && $discovery['score'] > 0) {
            return $this->fetchRecordsFromApi($discovery['url'], (array) $discovery['filters']);
        }

        return [];
    }

    public function fetchStoragePreSearchPage()
    {
        $response = $this->client->get('Member/StoragePreSearch', [
            'headers' => [
                'Referer' => $this->absoluteUrl('/Member/CustomerCenter'),
            ],
        ]);

        $html = (string) $response->getBody();

        if ($this->looksLikeLoginPage($html)) {
            throw new RuntimeException('联华会话已失效或未登录，无法访问预报查询页。');
        }

        return $html;
    }

    public function saveProbeArtifacts($html = null, $quick = false)
    {
        $html = $html ?: $this->fetchStoragePreSearchPage();
        $path = config('lianhua_express.probe_html_path');

        file_put_contents($path, $html);

        $discovery = $this->discoverListEndpoint($html, $quick);

        return [
            'html_path' => $path,
            'detected_list_url' => $discovery['url'] ?: $this->resolveListUrlFromHtml($html),
            'detected_filters' => !empty($discovery['filters']) ? $discovery['filters'] : $this->detectShippedFilters($html),
            'html_table_rows' => count($this->parseHtmlTable($html)),
            'discovery' => $discovery,
            'html_url_hints' => array_slice($this->buildCandidateListUrls($html), 0, 10),
        ];
    }

    public function analyzeSavedProbeHtml()
    {
        $path = config('lianhua_express.probe_html_path');
        if (!is_file($path)) {
            throw new RuntimeException('找不到已保存的 HTML：' . $path . '，请先运行 php artisan lianhua:probe');
        }

        $html = (string) file_get_contents($path);

        return [
            'html_path' => $path,
            'url_hints' => $this->buildCandidateListUrls($html),
            'filters' => $this->detectShippedFilters($html),
            'table_rows' => count($this->parseHtmlTable($html)),
        ];
    }

    protected function fetchRecordsFromApi($listUrl, array $filtersOverride = null)
    {
        $pageSize = (int) data_get(config('lianhua_express.list_params'), 'pageSize', 200);
        $pageNumber = 1;
        $allRows = [];
        $filters = $filtersOverride !== null
            ? $filtersOverride
            : (array) config('lianhua_express.shipped_filter');

        while ($pageNumber <= 50) {
            $params = array_merge(
                (array) config('lianhua_express.list_params'),
                $filters,
                [
                    'pageNumber' => $pageNumber,
                    'pageSize' => $pageSize,
                    'offset' => ($pageNumber - 1) * $pageSize,
                    'limit' => $pageSize,
                ]
            );

            $response = $this->client->post(ltrim($listUrl, '/'), [
                'form_params' => $params,
                'headers' => [
                    'X-Requested-With' => 'XMLHttpRequest',
                    'Referer' => $this->absoluteUrl('/Member/StoragePreSearch'),
                ],
            ]);

            $body = (string) $response->getBody();
            $rows = $this->extractRowsFromResponseBody($body);

            if (empty($rows)) {
                break;
            }

            foreach ($rows as $row) {
                $normalized = $this->normalizeRecord($row);
                if ($normalized !== null) {
                    $allRows[] = $normalized;
                }
            }

            if (count($rows) < $pageSize) {
                break;
            }

            $pageNumber++;
        }

        return $allRows;
    }

    protected function extractRowsFromResponseBody($body)
    {
        $payload = json_decode($body, true);
        if (!is_array($payload)) {
            return [];
        }

        if (isset($payload['rows']) && is_array($payload['rows'])) {
            return $payload['rows'];
        }

        if (isset($payload['Rows']) && is_array($payload['Rows'])) {
            return $payload['Rows'];
        }

        if (isset($payload['Data']['rows']) && is_array($payload['Data']['rows'])) {
            return $payload['Data']['rows'];
        }

        if (isset($payload['data']['rows']) && is_array($payload['data']['rows'])) {
            return $payload['data']['rows'];
        }

        if ($this->isListArray($payload)) {
            return $payload;
        }

        return [];
    }

    protected function isListArray(array $payload)
    {
        if ($payload === []) {
            return false;
        }

        return array_keys($payload) === range(0, count($payload) - 1);
    }

    protected function normalizeRecord(array $row)
    {
        $map = (array) config('lianhua_express.field_map');
        $recipient = $this->pickField($row, [
            data_get($map, 'recipient'),
            'ReceiverName',
            'ConsigneeName',
            'ReceiveName',
            'Recipient',
            '收件人',
        ]);
        $phone = $this->pickField($row, [
            data_get($map, 'phone'),
            'ReceiverPhone',
            'Phone',
            'Mobile',
            'Tel',
            '联系电话',
        ]);
        $tracking = $this->pickField($row, [
            data_get($map, 'tracking'),
            'SendNo',
            'TrackingNo',
            'ExpressNo',
            'SendBillNo',
            'BillNo',
            '发货单号',
        ]);
        $shippingMethod = $this->pickField($row, [
            data_get($map, 'shipping_method'),
            'SendType',
            'ExpressType',
            'ShippingMethod',
            '寄送方式',
        ]);
        $status = $this->pickField($row, [
            data_get($map, 'status'),
            'Status',
            'PreStateName',
            'SendStatus',
            '状态',
        ]);

        $tracking = strtoupper(trim($tracking));
        if ($tracking === '') {
            return null;
        }

        return [
            'recipient' => trim($recipient),
            'phone' => trim($phone),
            'tracking' => $tracking,
            'shipping_method' => trim($shippingMethod),
            'status' => trim($status),
            'raw' => $row,
        ];
    }

    protected function pickField(array $row, array $candidates)
    {
        foreach ($candidates as $key) {
            if ($key === null || $key === '') {
                continue;
            }

            $value = data_get($row, $key);
            if ($value !== null && trim((string) $value) !== '') {
                return (string) $value;
            }
        }

        foreach ($row as $key => $value) {
            foreach ($candidates as $candidate) {
                if ($candidate !== null && strcasecmp((string) $key, (string) $candidate) === 0) {
                    if ($value !== null && trim((string) $value) !== '') {
                        return (string) $value;
                    }
                }
            }
        }

        return '';
    }

    protected function filterShippedRecords(array $records)
    {
        $pattern = (string) config('lianhua_express.tracking_pattern', '/^EN\d{9}JP$/i');
        $allowedMethods = (array) config('lianhua_express.only_shipping_methods', ['EMS']);
        $statusNeedle = trim((string) data_get(config('lianhua_express.shipped_filter'), 'Status', '已发货'));

        $filtered = [];

        foreach ($records as $record) {
            if ($statusNeedle !== '' && stripos($record['status'], $statusNeedle) === false && $record['status'] !== '') {
                continue;
            }

            if (!empty($allowedMethods)) {
                $methodMatched = false;
                foreach ($allowedMethods as $method) {
                    if ($method !== '' && stripos($record['shipping_method'], $method) !== false) {
                        $methodMatched = true;
                        break;
                    }
                }
                if (!$methodMatched && $record['shipping_method'] !== '') {
                    continue;
                }
            }

            if ($pattern !== '' && !preg_match($pattern, $record['tracking'])) {
                continue;
            }

            $filtered[] = $record;
        }

        return $filtered;
    }

    protected function parseHtmlTable($html)
    {
        if (trim($html) === '') {
            return [];
        }

        libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        $loaded = $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html);
        libxml_clear_errors();

        if (!$loaded) {
            return [];
        }

        $xpath = new \DOMXPath($dom);
        $tables = $xpath->query('//table');
        $records = [];

        foreach ($tables as $table) {
            $headerCells = $xpath->query('.//tr[1]//th|.//tr[1]//td', $table);
            if ($headerCells->length === 0) {
                continue;
            }

            $headers = [];
            foreach ($headerCells as $index => $cell) {
                $headers[$index] = $this->normalizeHeader($cell->textContent);
            }

            $recipientIndex = $this->findColumnIndex($headers, ['收件人', 'receiver', 'consignee']);
            $phoneIndex = $this->findColumnIndex($headers, ['联系电话', 'phone', 'mobile', '电话']);
            $trackingIndex = $this->findColumnIndex($headers, ['发货单号', 'tracking', 'sendno', '运单']);
            $methodIndex = $this->findColumnIndex($headers, ['寄送方式', 'sendtype', 'shipping']);
            $statusIndex = $this->findColumnIndex($headers, ['状态', 'status']);

            if ($recipientIndex === null || $trackingIndex === null) {
                continue;
            }

            $rows = $xpath->query('.//tr[position()>1]', $table);
            foreach ($rows as $row) {
                $cells = $xpath->query('./td', $row);
                if ($cells->length === 0) {
                    continue;
                }

                $record = [
                    'recipient' => $this->cellText($cells, $recipientIndex),
                    'phone' => $phoneIndex !== null ? $this->cellText($cells, $phoneIndex) : '',
                    'tracking' => strtoupper($this->cellText($cells, $trackingIndex)),
                    'shipping_method' => $methodIndex !== null ? $this->cellText($cells, $methodIndex) : '',
                    'status' => $statusIndex !== null ? $this->cellText($cells, $statusIndex) : '',
                    'raw' => [],
                ];

                if ($record['tracking'] !== '') {
                    $records[] = $record;
                }
            }
        }

        return $records;
    }

    protected function normalizeHeader($text)
    {
        return strtolower(preg_replace('/\s+/u', '', trim((string) $text)));
    }

    protected function findColumnIndex(array $headers, array $needles)
    {
        foreach ($headers as $index => $header) {
            foreach ($needles as $needle) {
                $needle = strtolower(preg_replace('/\s+/u', '', $needle));
                if ($needle !== '' && strpos($header, $needle) !== false) {
                    return $index;
                }
            }
        }

        return null;
    }

    protected function cellText(\DOMNodeList $cells, $index)
    {
        $item = $cells->item($index);
        if ($item === null) {
            return '';
        }

        return trim(preg_replace('/\s+/u', ' ', $item->textContent));
    }

    protected function resolveListUrlFromHtml($html)
    {
        $configured = trim((string) config('lianhua_express.list_url'));
        if ($configured !== '') {
            return $configured;
        }

        if ($this->detectedListUrl) {
            return $this->detectedListUrl;
        }

        $candidates = $this->buildCandidateListUrls($html);
        if (!empty($candidates)) {
            return $candidates[0];
        }

        return '';
    }

    protected function buildCandidateListUrls($html)
    {
        $defaults = [
            '/Member/GetStoragePreSearchList',
            '/Member/StoragePreSearch/GetList',
            '/Member/StoragePreSearch/GetPageList',
            '/Member/StoragePreSearch/Query',
            '/Member/StoragePreSearch/GetDataList',
            '/Member/StoragePreSearch/List',
            '/Member/StoragePreSearch/GetGridData',
            '/Member/GetStoragePreList',
            '/Member/QueryStoragePreList',
            '/Member/GetStoragePreSearchPageList',
            '/Member/StoragePreSearchQuery',
            '/Member/GetStoragePreSearchData',
            '/Member/SearchStoragePreList',
            '/Member/GetPreSearchList',
            '/Member/StoragePreSearchData',
            '/Member/GetStoragePreSearchGrid',
            '/Member/LoadStoragePreSearchList',
            '/Member/StoragePreSearch/GetStoragePreSearchList',
            '/Member/StoragePreSearch/LoadList',
            '/Member/StoragePreSearch/Search',
            '/Member/StoragePreSearch/GetStoragePreSearchPageData',
            '/Ajax/GetStoragePreSearchList',
            '/Ajax/StoragePreSearchList',
            '/Ajax/QueryStoragePreList',
        ];

        $fromHtml = $this->extractMemberUrlsFromHtml($html);
        $urls = array_values(array_unique(array_merge($fromHtml, $defaults)));
        $scored = [];

        foreach ($urls as $url) {
            $score = $this->scoreUrlCandidate($url);
            if ($score < 0) {
                continue;
            }
            $scored[$url] = $score;
        }

        arsort($scored);

        return array_keys($scored);
    }

    protected function extractMemberUrlsFromHtml($html)
    {
        $urls = [];

        if (preg_match_all('/<script[^>]*>([\s\S]*?)<\/script>/i', $html, $scripts)) {
            foreach ($scripts[1] as $script) {
                $relevant = stripos($script, 'StoragePreSearch') !== false
                    || stripos($script, 'bootstrapTable') !== false
                    || stripos($script, '预报') !== false
                    || stripos($script, '发货单号') !== false
                    || stripos($script, '已发货') !== false;

                if (!$relevant) {
                    continue;
                }

                if (preg_match_all('/[\'"](\/Member\/[^\'"\?\#]+)[\'"]/', $script, $matches)) {
                    $urls = array_merge($urls, $matches[1]);
                }
            }
        }

        if (preg_match_all('/bootstrapTable\s*\(\s*\{([\s\S]{0,3000}?)\}\s*\)/', $html, $blocks)) {
            foreach ($blocks[1] as $block) {
                if (preg_match('/url\s*:\s*[\'"]([^\'"]+)[\'"]/', $block, $match)) {
                    $urls[] = $match[1];
                }
            }
        }

        return array_values(array_unique($urls));
    }

    protected function scoreUrlCandidate($url)
    {
        $url = strtolower((string) $url);
        $blocklist = [
            'export', 'meituan', 'productprice', 'wechat', 'login', 'register',
            'delete', 'save', 'upload', 'download', 'clone', 'detail', 'print',
            'excel', 'pdf', 'image', 'photo', 'password', 'captcha',
        ];

        foreach ($blocklist as $bad) {
            if (strpos($url, $bad) !== false) {
                return -100;
            }
        }

        $score = 0;
        if (strpos($url, 'storagepresearch') !== false) {
            $score += 120;
        }
        if (strpos($url, 'storagepre') !== false) {
            $score += 90;
        }
        if (strpos($url, 'presearch') !== false) {
            $score += 70;
        }
        if (strpos($url, 'storage') !== false) {
            $score += 35;
        }
        if (strpos($url, 'pre') !== false) {
            $score += 15;
        }
        foreach (['list', 'query', 'grid', 'data', 'search', 'load'] as $hint) {
            if (strpos($url, $hint) !== false) {
                $score += 12;
            }
        }

        return $score;
    }

    protected function buildShippedFilterSets($html, $quick = false, $extended = false)
    {
        $sets = [];
        $detected = $this->detectShippedFilters($html);
        if (!empty($detected)) {
            $sets[] = $detected;
        }

        $configured = (array) config('lianhua_express.shipped_filter');
        if (!empty($configured)) {
            $sets[] = $configured;
        }

        $sets[] = [];
        $sets[] = ['PreState' => 4];
        $sets[] = ['PreState' => 3];
        $sets[] = ['PreStatus' => 4];
        $sets[] = ['Status' => '已发货'];

        if (!$quick && $extended) {
            foreach ([2, 3, 4, 5] as $state) {
                foreach (['PreState', 'PreStatus', 'SearchState', 'State', 'SendState', 'TabStatus'] as $key) {
                    $sets[] = [$key => $state];
                }
            }
        }

        $unique = [];
        foreach ($sets as $set) {
            ksort($set);
            $hash = json_encode($set, JSON_UNESCAPED_UNICODE);
            $unique[$hash] = $set;
        }

        return array_values($unique);
    }

    protected function scoreEndpointResponse($url, array $filters, $probe = false)
    {
        $pageSize = 10;
        $params = array_merge(
            (array) config('lianhua_express.list_params'),
            $filters,
            [
                'pageNumber' => 1,
                'pageSize' => $pageSize,
                'offset' => 0,
                'limit' => $pageSize,
            ]
        );

        $options = [
            'form_params' => $params,
            'headers' => [
                'X-Requested-With' => 'XMLHttpRequest',
                'Referer' => $this->absoluteUrl('/Member/StoragePreSearch'),
            ],
        ];

        if ($probe) {
            $options['timeout'] = 8;
            $options['connect_timeout'] = 5;
        }

        $response = $this->client->post(ltrim($url, '/'), $options);

        $body = (string) $response->getBody();
        if ($body === '' || stripos($body, '<!DOCTYPE') !== false || stripos($body, '<html') !== false) {
            return [
                'score' => 0,
                'row_count' => 0,
                'tracking_count' => 0,
                'sample' => [],
            ];
        }

        $rows = $this->extractRowsFromResponseBody($body);
        $score = 0;
        $trackingCount = 0;
        $sample = [];
        $pattern = (string) config('lianhua_express.tracking_pattern', '/^EN\d{9}JP$/i');

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $normalized = $this->normalizeRecord($row);
            if ($normalized === null) {
                continue;
            }

            $rowScore = 1;
            if ($pattern !== '' && preg_match($pattern, $normalized['tracking'])) {
                $rowScore += 25;
                $trackingCount++;
            }
            if ($normalized['recipient'] !== '') {
                $rowScore += 4;
            }
            if (stripos($normalized['status'], '发货') !== false) {
                $rowScore += 3;
            }
            if (stripos($normalized['shipping_method'], 'EMS') !== false) {
                $rowScore += 2;
            }

            $score += $rowScore;

            if (count($sample) < 3 && ($trackingCount > 0 || $normalized['recipient'] !== '')) {
                $sample[] = [
                    'recipient' => $normalized['recipient'],
                    'tracking' => $normalized['tracking'],
                    'status' => $normalized['status'],
                ];
            }
        }

        if ($this->scoreUrlCandidate($url) > 0) {
            $score += min(30, $this->scoreUrlCandidate($url) / 4);
        }

        return [
            'score' => $score,
            'row_count' => count($rows),
            'tracking_count' => $trackingCount,
            'sample' => $sample,
        ];
    }

    protected function loadDiscoveryCache()
    {
        $path = config('lianhua_express.discovery_cache_path');
        if (!is_file($path)) {
            return [];
        }

        $payload = json_decode((string) file_get_contents($path), true);

        return is_array($payload) ? $payload : [];
    }

    protected function saveDiscoveryCache($url, array $filters)
    {
        $path = config('lianhua_express.discovery_cache_path');
        $payload = [
            'url' => $url,
            'filters' => $filters,
            'discovered_at' => date('c'),
        ];

        file_put_contents($path, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }

    protected function resolveListUrl($html)
    {
        return $this->resolveListUrlFromHtml($html);
    }

    protected function detectShippedFilters($html)
    {
        $filters = [];

        if (preg_match('/已发货[^"\';]{0,160}?(PreState|PreStatus|SearchState|State|SendState|TabStatus)[^0-9]{0,10}(\d+)/u', $html, $matches)) {
            $filters[$matches[1]] = $matches[2];
        }

        if (preg_match('/(PreState|PreStatus|SearchState|State|SendState|TabStatus)[^0-9]{0,10}(\d+)[^"\';]{0,160}?已发货/u', $html, $matches)) {
            $filters[$matches[1]] = $matches[2];
        }

        if (preg_match('/已发货[^"\']*[\'"](\w+)[\'"]\s*:\s*[\'"]?(\d+)[\'"]?/u', $html, $matches)) {
            $filters[$matches[1]] = $matches[2];
        }

        if (preg_match('/PreState[^0-9]{0,20}(\d+)/i', $html, $matches)) {
            $filters['PreState'] = $matches[1];
        }

        return $filters;
    }

    protected function looksLikeLoginPage($html)
    {
        return stripos($html, '/Home/UserLogin') !== false
            && stripos($html, 'txtAccount') !== false
            && stripos($html, 'txtPassword') !== false;
    }

    protected function absoluteUrl($path)
    {
        return rtrim((string) config('lianhua_express.base_url'), '/') . $path;
    }
}
