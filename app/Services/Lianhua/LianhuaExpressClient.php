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
        $listUrl = $this->resolveListUrl($pageHtml);

        if ($listUrl !== '') {
            $records = $this->fetchRecordsFromApi($listUrl);
            if (!empty($records)) {
                return $this->filterShippedRecords($records);
            }
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

    public function saveProbeArtifacts($html = null)
    {
        $html = $html ?: $this->fetchStoragePreSearchPage();
        $path = config('lianhua_express.probe_html_path');

        file_put_contents($path, $html);

        return [
            'html_path' => $path,
            'detected_list_url' => $this->resolveListUrl($html),
            'detected_filters' => $this->detectShippedFilters($html),
            'html_table_rows' => count($this->parseHtmlTable($html)),
        ];
    }

    protected function fetchRecordsFromApi($listUrl)
    {
        $pageSize = (int) data_get(config('lianhua_express.list_params'), 'pageSize', 200);
        $pageNumber = 1;
        $allRows = [];

        while ($pageNumber <= 50) {
            $params = array_merge(
                (array) config('lianhua_express.list_params'),
                (array) config('lianhua_express.shipped_filter'),
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

    protected function resolveListUrl($html)
    {
        $configured = trim((string) config('lianhua_express.list_url'));
        if ($configured !== '') {
            return $configured;
        }

        if ($this->detectedListUrl) {
            return $this->detectedListUrl;
        }

        $patterns = [
            '/bootstrapTable\s*\(\s*\{[^}]*url\s*:\s*[\'"]([^\'"]+)[\'"]/s',
            '/data-url=["\']([^"\']+)["\']/i',
            '/url\s*:\s*[\'"](\/Member\/[^\'"]+)[\'"]/i',
            '/mif\.ajax\s*\(\s*[\'"](\/Member\/[^\'"]+)[\'"]/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $html, $matches)) {
                $this->detectedListUrl = $matches[1];
                return $this->detectedListUrl;
            }
        }

        return '';
    }

    protected function detectShippedFilters($html)
    {
        $filters = [];

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
