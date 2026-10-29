<?php

declare(strict_types=1);

namespace Commerce\Hub;

use Commerce\Core\Access\Forbidden;
use Commerce\Core\Access\Principal;
use Commerce\Core\Catalog\CatalogException;
use Commerce\Core\Db\Database;
use Commerce\Core\Inventory\LedgerException;
use Commerce\Core\Orders\FulfilmentException;
use Commerce\Core\Support\Clock;
use Commerce\Hub\Http\HttpError;
use Commerce\Hub\Http\Request;
use Commerce\Hub\Http\Response;
use Commerce\Hub\Http\Session;

/**
 * The operations dashboard and the storefront API, in one small PHP app.
 * Every request: route → (dashboard) signed-in check → CSRF check on POST → controller, which checks permissions itself.
 */
final class App
{
    public readonly Services $services;
    public ?Principal $user = null;
    private array $routes = [];

    public function __construct(public readonly Database $db, public readonly Clock $clock, public readonly Session $session, public readonly array $env)
    {
        $this->services = new Services($this);
        $this->routes();
    }

    public function isProduction(): bool
    {
        return ($this->env['APP_ENV'] ?? 'production') === 'production';
    }

    public function handle(Request $req): Response
    {
        try {
            $res = $this->dispatch($req);
        } catch (Forbidden $e) {
            $res = $this->page($req, 'error', ['title' => 'Not allowed', 'message' => $e->getMessage()], 403);
        } catch (HttpError $e) {
            $res = str_starts_with($req->path, '/api/')
                ? Response::json(['error' => $e->getMessage()], $e->status)
                : $this->page($req, 'error', ['title' => $e->status === 404 ? 'Not found' : 'Something is not right', 'message' => $e->getMessage()], $e->status);
        } catch (LedgerException | CatalogException | FulfilmentException $e) {
            // Business-rule refusals are written for staff: show them where the person was.
            $this->flash('error', $e->getMessage());
            $res = Response::redirect($this->back($req));
        } catch (\Throwable $e) {
            $incident = bin2hex(random_bytes(4));
            error_log(sprintf('[hub] incident %s: %s in %s:%d', $incident, $e->getMessage(), $e->getFile(), $e->getLine()));
            $res = str_starts_with($req->path, '/api/')
                ? Response::json(['error' => 'Internal error', 'incident' => $incident], 500)
                : $this->page($req, 'error', ['title' => 'Something went wrong', 'message' => "The change was not saved. If this keeps happening, give this reference to your administrator: {$incident}."], 500);
        }
        return $this->secure($res);
    }

    private function dispatch(Request $req): Response
    {
        foreach ($this->routes as [$method, $pattern, $handler, $public]) {
            if ($method !== $req->method || !preg_match($pattern, $req->path, $m)) {
                continue;
            }
            $params = array_filter($m, 'is_string', ARRAY_FILTER_USE_KEY);
            if (!str_starts_with($req->path, '/api/')) {
                if (!$public) {
                    $uid = $this->session->get('user_id');
                    $this->user = $uid ? Principal::load($this->db, (int) $uid) : null;
                    if ($this->user === null) {
                        $this->session->remove('user_id');
                        return Response::redirect('/login?next=' . rawurlencode($req->path));
                    }
                }
                if ($req->method === 'POST' && !hash_equals((string) $this->session->get('csrf'), $req->p('_csrf'))) {
                    throw new HttpError(419, 'This form expired. Go back, refresh the page and try again.');
                }
            }
            return $handler($req, $params);
        }
        throw new HttpError(404, 'This page does not exist.');
    }

    private function routes(): void
    {
        $c = fn (string $class) => new $class($this);
        $r = function (string $method, string $path, callable $handler, bool $public = false) {
            $pattern = '#^' . preg_replace('#\{(\w+)\}#', '(?P<$1>\d+)', $path) . '$#';
            $this->routes[] = [$method, $pattern, $handler, $public];
        };
        $auth = $c(Controllers\AuthController::class);
        $r('GET', '/login', $auth->loginForm(...), true);
        $r('POST', '/login', $auth->login(...), true);
        $r('POST', '/logout', $auth->logout(...));
        $r('GET', '/setup', $auth->setupForm(...), true);
        $r('POST', '/setup', $auth->setup(...), true);

        $ov = $c(Controllers\OverviewController::class);
        $r('GET', '/', $ov->index(...));
        $r('GET', '/search', $ov->search(...));
        $r('GET', '/help', $ov->help(...));
        $r('POST', '/context', $ov->setContext(...));
        $r('POST', '/views', $ov->saveView(...));

        $inv = $c(Controllers\InventoryController::class);
        $r('GET', '/inventory', $inv->index(...));
        $r('GET', '/inventory/export', $inv->export(...));
        $r('GET', '/inventory/import', $inv->importForm(...));
        $r('POST', '/inventory/import', $inv->importPreview(...));
        $r('POST', '/inventory/import/confirm', $inv->importConfirm(...));
        $r('GET', '/inventory/incoming', $inv->incoming(...));
        $r('POST', '/inventory/incoming', $inv->createPurchaseOrder(...));
        $r('POST', '/inventory/incoming/{id}/ordered', $inv->markOrdered(...));
        $r('POST', '/inventory/incoming/lines/{id}/receive', $inv->receiveLine(...));
        $r('POST', '/inventory/bulk-reorder', $inv->bulkReorder(...));
        $r('GET', '/inventory/{id}', $inv->show(...));
        $r('GET', '/inventory/{id}/adjust', $inv->adjustForm(...));
        $r('POST', '/inventory/{id}/adjust', $inv->adjust(...));
        $r('POST', '/inventory/{id}/count', $inv->count(...));
        $r('POST', '/inventory/{id}/reorder', $inv->reorder(...));
        $r('POST', '/inventory/movements/{id}/undo', $inv->undo(...));

        $prod = $c(Controllers\ProductsController::class);
        $r('GET', '/products', $prod->index(...));
        $r('GET', '/products/new', $prod->newForm(...));
        $r('POST', '/products', $prod->create(...));
        $r('GET', '/products/{id}', $prod->show(...));
        $r('POST', '/products/{id}/options', $prod->addOption(...));
        $r('POST', '/products/{id}/items', $prod->updateItems(...));
        $r('POST', '/listings', $prod->addListing(...));
        $r('POST', '/listings/{id}/map', $prod->map(...));
        $r('POST', '/listings/{id}/unmap', $prod->unmap(...));

        $ord = $c(Controllers\OrdersController::class);
        $r('GET', '/orders', $ord->index(...));
        $r('GET', '/orders/picklist', $ord->pickList(...));
        $r('GET', '/orders/{id}', $ord->show(...));
        $r('POST', '/orders/{id}/action', $ord->action(...));
        $r('POST', '/orders/{id}/shipments', $ord->createShipment(...));
        $r('POST', '/shipments/{id}/dispatch', $ord->dispatch(...));
        $r('POST', '/shipments/{id}/delivered', $ord->delivered(...));
        $r('POST', '/orders/{id}/returns', $ord->openReturn(...));
        $r('GET', '/shipments', $ord->shipments(...));

        $ret = $c(Controllers\ReturnsController::class);
        $r('GET', '/returns', $ret->index(...));
        $r('GET', '/returns/{id}', $ret->show(...));
        $r('POST', '/returns/lines/{id}/receive', $ret->receive(...));
        $r('POST', '/returns/lines/{id}/inspect', $ret->inspect(...));
        $r('POST', '/returns/{id}/refund', $ret->refund(...));

        $misc = $c(Controllers\AdminController::class);
        $r('GET', '/transfers', $misc->transfers(...));
        $r('GET', '/reports', $misc->reports(...));
        $r('GET', '/reports/export', $misc->reportExport(...));
        $r('GET', '/team', $misc->team(...));
        $r('POST', '/team', $misc->invite(...));
        $r('POST', '/team/{id}/roles', $misc->setRoles(...));
        $r('POST', '/team/{id}/status', $misc->setStatus(...));
        $r('GET', '/settings', $misc->settings(...));
        $r('POST', '/settings/stores/{id}', $misc->updateStore(...));
        $r('POST', '/settings/warehouse', $misc->updateWarehouse(...));
        $r('GET', '/health', $misc->health(...));
        $r('POST', '/health/inbox/{id}/retry', $misc->retryInbox(...));
        $r('POST', '/health/outbox/{id}/retry', $misc->retryOutbox(...));
        $r('POST', '/health/reconcile', $misc->reconcile(...));

        $api = $c(Controllers\ApiController::class);
        $r('POST', '/api/v1/reservations', $api->reserve(...), true);
        $r('POST', '/api/v1/webhooks/woocommerce/{id}', $api->wooWebhook(...), true);
        $r('GET', '/api/v1/markets', $api->markets(...), true);
        $r('GET', '/api/v1/health', $api->health(...), true);
    }

    // ── Helpers used by controllers ─────────────────────────────────────────

    public function page(Request $req, string $template, array $vars = [], int $status = 200): Response
    {
        return Response::html($this->services->view()->render($template, $vars + ['req' => $req, 'app' => $this]), $status);
    }

    public function flash(string $type, string $message, ?array $undo = null): void
    {
        $this->session->set('flash', ['type' => $type, 'message' => $message, 'undo' => $undo]);
    }

    public function takeFlash(): ?array
    {
        $f = $this->session->get('flash');
        $this->session->remove('flash');
        return $f;
    }

    public function csrf(): string
    {
        $t = $this->session->get('csrf');
        if (!is_string($t) || strlen($t) !== 64) {
            $t = bin2hex(random_bytes(32));
            $this->session->set('csrf', $t);
        }
        return $t;
    }

    /** The brand chosen in the top bar, if the user may see it. */
    public function brandFilter(): ?int
    {
        $b = $this->session->get('brand');
        return is_int($b) ? $b : null;
    }

    /** Brands whose data the user may see for $permission, narrowed by the top-bar brand filter. null = all. */
    public function scope(string $permission): ?array
    {
        $this->user->require($permission, anyBrand: true);
        $scope = $this->user->brandScope($permission);
        $filter = $this->brandFilter();
        if ($filter === null) {
            return $scope;
        }
        return $scope === null || in_array($filter, $scope, true) ? [$filter] : [];
    }

    public function back(Request $req): string
    {
        $ref = $req->header('referer');
        $path = $ref ? (string) parse_url($ref, PHP_URL_PATH) : '/';
        $query = $ref ? (string) parse_url($ref, PHP_URL_QUERY) : '';
        return ($path ?: '/') . ($query !== '' ? '?' . $query : '');
    }

    private function secure(Response $res): Response
    {
        $res->headers += [
            'Content-Security-Policy' => "default-src 'self'; img-src 'self' data: https:; style-src 'self'; script-src 'self'; form-action 'self'; frame-ancestors 'none'; base-uri 'none'",
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'same-origin',
            'X-Frame-Options' => 'DENY',
            'Cache-Control' => 'no-store',
            'Permissions-Policy' => 'geolocation=(), camera=(), microphone=()',
        ];
        if (!$this->isProduction() && ($this->env['DEMO'] ?? '') === '1') {
            $res->headers['X-Robots-Tag'] = 'noindex';
        }
        return $res;
    }
}
