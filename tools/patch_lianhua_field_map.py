#!/usr/bin/env python3
from pathlib import Path
import re

ROOT = Path(__file__).resolve().parents[1]

config = ROOT / 'config/lianhua_express.php'
text = config.read_text(encoding='utf-8')
old = """    'field_map' => [
        'recipient' => env('LIANHUA_FIELD_RECIPIENT', 'sName'),
        'phone' => env('LIANHUA_FIELD_PHONE', 'sPhone'),
        'tracking' => env('LIANHUA_FIELD_TRACKING', 'sEmsNumber'),
        'shipping_method' => env('LIANHUA_FIELD_SHIPPING_METHOD', 'sTransport'),
        'status' => env('LIANHUA_FIELD_STATUS', 'sState'),
    ],"""
new = """    'field_map' => [
        'recipient' => env('LIANHUA_FIELD_RECIPIENT', 'ReceiverName'),
        'phone' => env('LIANHUA_FIELD_PHONE', 'ReceiverPhone'),
        'tracking' => env('LIANHUA_FIELD_TRACKING', 'SendNumber'),
        'shipping_method' => env('LIANHUA_FIELD_SHIPPING_METHOD', 'TransportCompany'),
        'status' => env('LIANHUA_FIELD_STATUS', 'State'),
    ],"""
if old not in text:
    raise SystemExit('config field_map block not found')
text = text.replace(old, new)
text = re.sub(
    r"env\('LIANHUA_SHIPPED_S_STATE', '[^']*'\)",
    "env('LIANHUA_SHIPPED_S_STATE', '已发货')",
    text,
    count=1,
)
config.write_text(text, encoding='utf-8')
print('config ok')

client = ROOT / 'app/Services/Lianhua/LianhuaExpressClient.php'
text = client.read_text(encoding='utf-8')
idx = text.find('protected function normalizeRecord')
if idx < 0:
    raise SystemExit('normalizeRecord not found')
block_start = text.find('        $tracking = $this->pickField', idx)
block_end = text.find('        if ($recipient === \'\') {', block_start)
if block_start < 0 or block_end < 0:
    raise SystemExit('tracking block bounds not found')

new_section = """        $tracking = $this->pickField($row, [
            data_get($map, 'tracking'),
            'SendNumber',
            'SendNo',
            'TrackingNo',
            'ExpressNo',
            'SendBillNo',
            'BillNo',
            'MailNo',
            'EMSNo',
            'SendOrderNo',
            'WaybillNo',
            'ExpressBillNo',
            'LogisticsNo',
            'sEmsNumber',
            'EMSNumber',
        ]);
        $shippingMethod = $this->pickField($row, [
            data_get($map, 'shipping_method'),
            'TransportCompany',
            'ShipperCode',
            'sTransport',
            'TransportName',
            'SendType',
            'ExpressType',
            'ShippingMethod',
        ]);
        $status = $this->pickField($row, [
            data_get($map, 'status'),
            'State',
            'sState',
            'Status',
            'PreStateName',
            'SendStatus',
        ]);

        $pattern = (string) config('lianhua_express.tracking_pattern', '/^EN\\d{9}JP$/i');
        if ($tracking === '' || ($pattern !== '' && !preg_match($pattern, strtoupper(trim($tracking))))) {
            $tracking = $this->inferTrackingFromRow($row);
        }

"""
text = text[:block_start] + new_section + text[block_end:]
client.write_text(text, encoding='utf-8')
print('client ok')
