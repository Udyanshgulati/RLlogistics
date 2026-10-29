<?php

declare(strict_types=1);

namespace Commerce\Hub\Controllers;

use Commerce\Core\Catalog\CatalogException;
use Commerce\Core\Catalog\SkuPolicy;
use Commerce\Hub\Http\HttpError;
use Commerce\Hub\Http\Request;
use Commerce\Hub\Http\Response;

/**
 * Central products and their SKUs. Descriptions, photos and prices stay in each storefront;
 * this screen owns identity, sizes, shipping attributes and the link from storefront listings to stock.
 */
final class ProductsController extends Controller
{
    public function index(Request $req): Response
    {
        $scope = $this->app->scope('products.view');
        $where = [$this->sqlScope($scope, 'p.owner_brand_id')];
        $params = [];
        if (($q = $req->q('q')) !== '') {
            $where[] = '(p.name LIKE ? OR p.code LIKE ?)';
            $like = '%' . addcslashes($q, '%_\\') . '%';
            array_push($params, $like, $like);
        }
        $products = $this->db->all(
            'SELECT p.*, COUNT(DISTINCT i.id) AS skus,
                    (SELECT COUNT(*) FROM listings l JOIN inventory_items ii ON ii.id = l.inventory_item_id WHERE ii.product_id = p.id AND l.status = \'mapped\') AS links,
                    (SELECT COUNT(DISTINCT s.brand_id) FROM listings l JOIN inventory_items ii ON ii.id = l.inventory_item_id JOIN stores s ON s.id = l.store_id WHERE ii.product_id = p.id AND l.status = \'mapped\') AS selling_brands
             FROM products p LEFT JOIN inventory_items i ON i.product_id = p.id WHERE ' . implode(' AND ', $where) . ' GROUP BY p.id ORDER BY p.name LIMIT 500',
            $params,
        );
        $unlinked = $this->app->user->can('mapping.manage', anyBrand: true)
            ? $this->db->all("SELECT l.*, s.name AS store_name, s.brand_id FROM listings l JOIN stores s ON s.id = l.store_id WHERE l.status = 'unmapped' AND " . $this->sqlScope($scope, 's.brand_id') . ' ORDER BY s.id, l.wc_name LIMIT 200')
            : [];
        $suggestions = [];
        foreach ($this->app->services->catalog()->suggestions() as $s) {
            $suggestions[$s['listing_id']] = $s;
        }
        return $this->app->page($req, 'products/index', ['title' => 'Products', 'nav' => 'products', 'products' => $products, 'unlinked' => $unlinked, 'suggestions' => $suggestions, 'q' => $q, 'onlyUnlinked' => $req->q('unlinked') === '1']);
    }

    public function newForm(Request $req): Response
    {
        $this->app->user->require('products.manage', anyBrand: true);
        $brands = array_filter($this->app->services->brands(), fn ($b) => $this->app->user->can('products.manage', (int) $b['id']));
        $prefixes = array_column($this->db->all('SELECT id, sku_prefix FROM brands'), 'sku_prefix', 'id');
        return $this->app->page($req, 'products/new', ['title' => 'Add product', 'nav' => 'products', 'brands' => $brands, 'prefixes' => $prefixes, 'sizes' => $this->app->services->skuPolicy()->sizeRun(), 'old' => $this->app->session->get('old_product', [])]);
    }

    public function create(Request $req): Response
    {
        $brand = $req->int('brand_id', 0, true);
        $this->app->user->require('products.manage', $brand);
        $options = match ($req->p('variants')) {
            'sizes' => array_values(array_intersect($this->app->services->skuPolicy()->sizeRun(), (array) ($req->post['sizes'] ?? []))),
            'custom' => array_values(array_filter(array_map('trim', explode(',', $req->p('custom_options'))))),
            default => [],
        };
        if ($req->p('variants') === 'sizes' && $options === []) {
            throw new CatalogException('Choose at least one size.');
        }
        $this->app->session->set('old_product', $req->post);
        $ids = $this->app->services->catalog()->createProduct($brand, $req->p('code'), $req->p('name'), $req->p('category') ?: null, $options, $this->actor(), $req->p('variants') === 'sizes' ? 'apparel_size' : 'custom');
        $this->app->session->remove('old_product');
        $productId = (int) $this->db->value('SELECT product_id FROM inventory_items WHERE id = ?', [reset($ids)]);
        $this->app->flash('success', 'Product created with ' . count($ids) . ' SKU' . (count($ids) === 1 ? '' : 's') . ': ' . implode(', ', array_keys($ids)) . '. Stock starts at 0 — use Add stock to record what is on the shelf.');
        return Response::redirect("/products/{$productId}");
    }

    public function show(Request $req, array $p): Response
    {
        $product = $this->db->one('SELECT * FROM products WHERE id = ?', [(int) $p['id']]) ?? throw new HttpError(404, 'This product does not exist.');
        $items = $this->db->all('SELECT id FROM inventory_items WHERE product_id = ? ORDER BY id', [$product['id']]);
        $brands = [(int) $product['owner_brand_id']];
        foreach ($items as $i) {
            $brands = array_merge($brands, $this->brandsOfItem((int) $i['id']));
        }
        $this->requireForAny('products.view', array_unique($brands));
        $rows = array_map(fn ($i) => $this->app->services->inventory()->one((int) $i['id']) + $this->db->one('SELECT weight_g, length_mm, width_mm, height_mm FROM inventory_items WHERE id = ?', [$i['id']]), $items);
        $listings = $this->db->all('SELECT l.*, s.name AS store_name, s.brand_id, i.sku FROM listings l JOIN stores s ON s.id = l.store_id JOIN inventory_items i ON i.id = l.inventory_item_id WHERE i.product_id = ? ORDER BY i.id, s.id', [$product['id']]);
        $unlinked = $this->app->user->can('mapping.manage', anyBrand: true) ? $this->db->all("SELECT l.*, s.name AS store_name, s.brand_id FROM listings l JOIN stores s ON s.id = l.store_id WHERE l.status = 'unmapped' ORDER BY s.id, l.wc_name LIMIT 200") : [];
        return $this->app->page($req, 'products/show', [
            'title' => $product['name'], 'nav' => 'products', 'product' => $product, 'items' => $rows, 'listings' => $listings, 'unlinked' => $unlinked,
            'stores' => $this->db->all('SELECT id, name, brand_id FROM stores ORDER BY id'), 'confirm' => $this->app->session->get('confirm_map'),
        ]);
    }

    public function addOption(Request $req, array $p): Response
    {
        $product = $this->db->one('SELECT * FROM products WHERE id = ?', [(int) $p['id']]) ?? throw new HttpError(404, 'This product does not exist.');
        $this->app->user->require('products.manage', (int) $product['owner_brand_id']);
        $this->app->services->catalog()->addOption((int) $product['id'], $req->p('option'), $this->actor());
        $this->app->flash('success', 'Option added.');
        return Response::redirect("/products/{$product['id']}");
    }

    /** Saves barcode, weight, dimensions and reorder level for every SKU of the product. */
    public function updateItems(Request $req, array $p): Response
    {
        $product = $this->db->one('SELECT * FROM products WHERE id = ?', [(int) $p['id']]) ?? throw new HttpError(404, 'This product does not exist.');
        $this->app->user->require('products.manage', (int) $product['owner_brand_id']);
        $num = function (mixed $v, string $label): ?int {
            $v = is_string($v) ? trim($v) : '';
            if ($v === '') {
                return null;
            }
            if (!preg_match('/^\d{1,9}$/', $v)) {
                throw new CatalogException("{$label} must be a whole number.");
            }
            return (int) $v;
        };
        $this->db->transaction(function () use ($req, $product, $num) {
            foreach ((array) ($req->post['items'] ?? []) as $id => $f) {
                $item = $this->db->one('SELECT id FROM inventory_items WHERE id = ? AND product_id = ?', [(int) $id, $product['id']]);
                if ($item === null || !is_array($f)) {
                    continue;
                }
                $barcode = trim((string) ($f['barcode'] ?? '')) ?: null;
                $this->db->run(
                    'UPDATE inventory_items SET barcode = ?, weight_g = ?, length_mm = ?, width_mm = ?, height_mm = ?, reorder_level = COALESCE(?, reorder_level), updated_at = ? WHERE id = ?',
                    [$barcode, $num($f['weight_g'] ?? '', 'Weight'), $num($f['length_mm'] ?? '', 'Length'), $num($f['width_mm'] ?? '', 'Width'), $num($f['height_mm'] ?? '', 'Height'), $num($f['reorder_level'] ?? '', 'Reorder level'), $this->app->clock->now(), $item['id']],
                );
            }
            $hs = strtoupper(preg_replace('/\s+/', '', $req->p('hs_code')));
            $origin = strtoupper($req->p('origin_country'));
            if ($hs !== '' && !preg_match('/^\d{6,10}$/', $hs)) {
                throw new CatalogException('HS codes are 6 to 10 digits.');
            }
            if ($origin !== '' && !preg_match('/^[A-Z]{2}$/', $origin)) {
                throw new CatalogException('Country of origin is a two-letter code, e.g. IN.');
            }
            $status = in_array($req->p('status'), ['draft', 'active', 'archived'], true) ? $req->p('status') : $product['status'];
            $this->db->run('UPDATE products SET hs_code = ?, origin_country = ?, category = ?, status = ?, updated_at = ? WHERE id = ?', [$hs ?: null, $origin ?: null, $req->p('category') ?: null, $status, $this->app->clock->now(), $product['id']]);
        });
        $this->app->services->audit()->log($this->actor(), 'product.update', 'product', $product['id']);
        $this->app->flash('success', 'Product details saved.');
        return Response::redirect("/products/{$product['id']}");
    }

    /** Registers a storefront product by hand (before the connector's catalogue sync has run). */
    public function addListing(Request $req): Response
    {
        $store = $req->int('store_id', 0, true);
        $brand = (int) $this->db->value('SELECT brand_id FROM stores WHERE id = ?', [$store]);
        $this->app->user->require('mapping.manage', $brand ?: null);
        $wcProduct = $req->int('wc_product_id', 0, true);
        if ($wcProduct <= 0) {
            throw new CatalogException('Enter the WooCommerce product ID (shown in the product list in WordPress).');
        }
        $id = $this->app->services->catalog()->recordListing($store, $wcProduct, max(0, (int) $req->int('wc_variation_id', 0, true)), $req->p('wc_sku') ?: null, $req->p('wc_name') ?: null);
        $item = $req->int('item_id', null, true);
        if ($item !== null) {
            return $this->map($req->withPost(['item_id' => (string) $item] + $req->post), ['id' => (string) $id]);
        }
        $this->app->flash('success', 'Storefront product added. Link it to a SKU to start sharing stock with the storefront.');
        return Response::redirect($this->app->back($req));
    }

    public function map(Request $req, array $p): Response
    {
        $listing = $this->db->one('SELECT l.*, s.brand_id FROM listings l JOIN stores s ON s.id = l.store_id WHERE l.id = ?', [(int) $p['id']]) ?? throw new HttpError(404, 'Unknown storefront product.');
        $this->app->user->require('mapping.manage', (int) $listing['brand_id']);
        $item = $req->int('item_id', 0, true);
        try {
            $this->app->services->catalog()->mapListing((int) $listing['id'], $item, $this->actor(), $req->p('confirm_shared') === '1', $req->p('confirm_sku') === '1');
        } catch (CatalogException $e) {
            if ($e->needsConfirmation !== null) {
                // Ask, with the consequences spelled out, instead of failing.
                $this->app->session->set('confirm_map', ['listing' => (int) $listing['id'], 'item' => $item, 'kind' => $e->needsConfirmation, 'message' => $e->getMessage(),
                    'shared' => $req->p('confirm_shared') === '1' || $e->needsConfirmation === 'shared', 'sku' => $req->p('confirm_sku') === '1' || $e->needsConfirmation === 'sku_mismatch']);
                $productId = (int) $this->db->value('SELECT product_id FROM inventory_items WHERE id = ?', [$item]);
                return Response::redirect("/products/{$productId}#confirm");
            }
            throw $e;
        }
        $this->app->session->remove('confirm_map');
        $sku = $this->db->value('SELECT sku FROM inventory_items WHERE id = ?', [$item]);
        $this->app->flash('success', "Linked to {$sku}. The storefront's stock quantity will be updated from the central stock within a minute.");
        return Response::redirect('/products/' . $this->db->value('SELECT product_id FROM inventory_items WHERE id = ?', [$item]));
    }

    public function unmap(Request $req, array $p): Response
    {
        $listing = $this->db->one('SELECT l.*, s.brand_id FROM listings l JOIN stores s ON s.id = l.store_id WHERE l.id = ?', [(int) $p['id']]) ?? throw new HttpError(404, 'Unknown storefront product.');
        $this->app->user->require('mapping.manage', (int) $listing['brand_id']);
        $productId = $this->db->value('SELECT product_id FROM inventory_items WHERE id = ?', [$listing['inventory_item_id']]);
        $this->app->services->catalog()->unmapListing((int) $listing['id'], $this->actor(), $req->p('reason'));
        $this->app->flash('success', 'Unlinked. The storefront will show this product as out of stock until it is linked again.');
        return Response::redirect($productId ? "/products/{$productId}" : '/products');
    }
}
