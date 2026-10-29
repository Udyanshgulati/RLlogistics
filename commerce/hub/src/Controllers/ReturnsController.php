<?php

declare(strict_types=1);

namespace Commerce\Hub\Controllers;

use Commerce\Hub\Http\HttpError;
use Commerce\Hub\Http\Request;
use Commerce\Hub\Http\Response;

final class ReturnsController extends Controller
{
    public function index(Request $req): Response
    {
        $scope = $this->app->scope('orders.view');
        $where = [$this->sqlScope($scope, 's.brand_id')];
        $params = [];
        if (($st = $req->q('status')) !== '') {
            $where[] = 'r.status = ?';
            $params[] = $st;
        }
        $rows = $this->db->all(
            'SELECT r.*, o.order_number, o.id AS order_id, s.brand_id, (SELECT SUM(qty) FROM return_lines WHERE return_id = r.id) AS units
             FROM returns r JOIN orders o ON o.id = r.order_id JOIN stores s ON s.id = o.store_id WHERE ' . implode(' AND ', $where) . ' ORDER BY r.id DESC LIMIT 200',
            $params,
        );
        return $this->app->page($req, 'returns/index', ['title' => 'Returns & exchanges', 'nav' => 'returns', 'rows' => $rows, 'status' => $req->q('status')]);
    }

    public function show(Request $req, array $p): Response
    {
        $r = $this->db->one('SELECT r.*, o.order_number, o.currency, o.total_minor, o.refunded_minor FROM returns r JOIN orders o ON o.id = r.order_id WHERE r.id = ?', [(int) $p['id']]) ?? throw new HttpError(404, 'This return does not exist.');
        $brand = $this->brandOfOrder((int) $r['order_id']);
        $this->app->user->require('orders.view', $brand);
        $lines = $this->db->all('SELECT rl.*, ol.name, ol.sku, ol.option_label, ol.unit_price_minor FROM return_lines rl JOIN order_lines ol ON ol.id = rl.order_line_id WHERE rl.return_id = ? ORDER BY rl.id', [$r['id']]);
        $suggested = array_sum(array_map(fn ($l) => (int) $l['unit_price_minor'] * (int) $l['qty'], $lines));
        return $this->app->page($req, 'returns/show', ['title' => $r['rma_number'], 'nav' => 'returns', 'r' => $r, 'lines' => $lines, 'brand' => $brand, 'suggested' => $suggested]);
    }

    public function receive(Request $req, array $p): Response
    {
        [$line, $brand] = $this->line((int) $p['id']);
        $this->app->user->require('returns.process', $brand);
        $this->app->services->returns()->receive((int) $line['id'], $req->int('received', -1, true), $this->actor());
        $this->app->flash('success', 'Receipt recorded. The items are awaiting inspection and are not for sale yet.');
        return Response::redirect('/returns/' . $line['return_id']);
    }

    public function inspect(Request $req, array $p): Response
    {
        [$line, $brand] = $this->line((int) $p['id']);
        $this->app->user->require('returns.process', $brand);
        $this->app->services->returns()->inspect((int) $line['id'], $req->int('sellable', -1, true), $req->int('damaged', -1, true), $this->actor());
        $this->app->flash('success', 'Inspection recorded. Approved items are back in available stock; damaged items are held.');
        return Response::redirect('/returns/' . $line['return_id']);
    }

    public function refund(Request $req, array $p): Response
    {
        $r = $this->db->one('SELECT * FROM returns WHERE id = ?', [(int) $p['id']]) ?? throw new HttpError(404, 'This return does not exist.');
        $this->app->user->require('refunds.request', $this->brandOfOrder((int) $r['order_id']));
        $this->app->services->returns()->requestRefund((int) $r['id'], $req->p('amount'), $this->actor());
        $this->app->flash('success', 'Refund sent to the storefront. It shows as Refunded only after the payment provider confirms it.');
        return Response::redirect('/returns/' . $r['id']);
    }

    private function line(int $id): array
    {
        $l = $this->db->one('SELECT rl.*, r.order_id FROM return_lines rl JOIN returns r ON r.id = rl.return_id WHERE rl.id = ?', [$id]) ?? throw new HttpError(404, 'Unknown return line.');
        return [$l, $this->brandOfOrder((int) $l['order_id'])];
    }
}
