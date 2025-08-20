<?php
/*
Plugin Name: Easy Farm Cart to Square Sync
Description: Easy Farm Cart is the source of truth for title, description, and price. Automatically updates Square on product.updated. Inventory sync is bi-directional (latest authoritative event wins). Includes batch sync, retry/backoff, logging, correct unlimited handling, catalog statistics & auto-create of missing Square SKUs. Tabbed UI (Connections vs Diagnostics). Added option to compute catalog stats for enabled products only.
Version: 1.15.1
Author: Your Name
*/

if (!defined('ABSPATH')) exit;

if (!defined('EC2SQ_VERSION'))          define('EC2SQ_VERSION', '1.15.1');
if (!defined('EC2SQ_PLUGIN_TITLE'))     define('EC2SQ_PLUGIN_TITLE', 'Easy Farm Cart ↔ Square Sync');
if (!defined('EFC_SQUARE_REST_NS'))     define('EFC_SQUARE_REST_NS', 'efc-square-sync/v1');
if (!defined('EFC_SQUARE_LEGACY_NS'))   define('EFC_SQUARE_LEGACY_NS', 'ecwid-square-sync/v1');

if (!defined('EC2SQ_MAX_HTTP_RETRIES')) define('EC2SQ_MAX_HTTP_RETRIES', 4);
if (!defined('EC2SQ_BACKOFF_BASE_MS'))  define('EC2SQ_BACKOFF_BASE_MS', 350);
if (!defined('EC2SQ_HTTP_TIMEOUT'))     define('EC2SQ_HTTP_TIMEOUT', 25);
if (!defined('EC2SQ_AUTOCREATE_LIMIT_DEFAULT')) define('EC2SQ_AUTOCREATE_LIMIT_DEFAULT', 50);

// ... rest of plugin code (unchanged) ...

/* PATCHED: Filter SKU mapping to only include SKUs present at the configured location */
function ec2sq_get_square_sku_map($square_token, $use_cache = true) {
    $key = 'ec2sq_sku_map_' . md5($square_token);
    $square_location_id = get_option('square_location_id');
    if ($use_cache) {
        $cached = get_transient($key);
        if (is_array($cached)) return $cached;
    }
    $items = $use_cache ? ec2sq_get_cached_square_items($square_token, 60, false) : ecwid_square_get_all_square_items($square_token, false);
    $map = [];
    if ($items !== false) {
        foreach ($items as $item) {
            if (!isset($item['variations'])) continue;
            foreach ($item['variations'] as $variation) {
                $s = $variation['item_variation_data']['sku'] ?? '';
                $var_present_all = $variation['present_at_all_locations'] ?? false;
                $var_present_ids = $variation['present_at_location_ids'] ?? [];
                $var_is_present = $var_present_all || in_array($square_location_id, $var_present_ids, true);
                if ($s && $var_is_present) {
                    $map[$s] = [
                        'variation_id' => $variation['id'],
                        'item_id' => $item['id']
                    ];
                }
            }
        }
    }
    if ($use_cache) set_transient($key, $map, 60);
    return $map;
}

// ... rest of plugin code (unchanged) ...
