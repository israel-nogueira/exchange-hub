<?php

namespace Exchanges\Exchanges\Fake;

use Exchanges\Storage\JsonStorage;

class FakeOrderMatcher
{
    public function __construct(
        private JsonStorage     $storage,
        private FakePriceEngine $engine,
        private FakeConfig      $config,
    ) {}

    /**
     * Verifica ordens limit abertas e executa as que cruzaram o preço.
     * Chamado automaticamente a cada getTicker().
     */
    public function checkAndExecute(string $symbol): void
    {
        if (!$this->config->autoExecuteLimitOrders) {
            return;
        }

        $openOrders = $this->storage->read('trading/open_orders') ?? [];
        $price      = $this->engine->getPrice($symbol);
        $changed    = false;

        foreach ($openOrders as &$order) {
            if ($order['symbol'] !== $symbol)         continue;
            if ($order['status'] !== 'OPEN')           continue;
            if ($order['type']   === 'MARKET')         continue;

            $shouldExecute = match($order['type']) {
                'LIMIT'        => $this->checkLimit($order, $price),
                'STOP_LIMIT'   => $this->checkStopLimit($order, $price),
                'STOP_MARKET'  => $this->checkStopMarket($order, $price),
                default        => false,
            };

            if ($shouldExecute) {
                $this->execute($order, $price);
                $changed = true;
            }
        }
        unset($order);

        if ($changed) {
            $this->storage->write('trading/open_orders', $openOrders);
        }
    }

    /**
     * Executa uma ordem OCO — cancela a outra quando uma for ativada.
     */
    public function checkOCO(string $symbol): void
    {
        $openOrders = $this->storage->read('trading/open_orders') ?? [];
        $price      = $this->engine->getPrice($symbol);

        $ocoGroups = [];
        foreach ($openOrders as $order) {
            if (!empty($order['oco_group_id'])) {
                $ocoGroups[$order['oco_group_id']][] = $order['id'];
            }
        }

        foreach ($ocoGroups as $groupId => $orderIds) {
            foreach ($openOrders as &$order) {
                if (!in_array($order['id'], $orderIds)) continue;
                if ($this->checkLimit($order, $price) || $this->checkStopLimit($order, $price)) {
                    // Executa esta, cancela a outra do mesmo grupo
                    $this->execute($order, $price);
                    foreach ($openOrders as &$other) {
                        if ($other['oco_group_id'] === $groupId && $other['id'] !== $order['id']) {
                            $other['status']     = 'CANCELLED';
                            $other['updated_at'] = time() * 1000;
                            $this->moveToHistory($other);
                        }
                    }
                    unset($other);
                    break;
                }
            }
            unset($order);
        }

        $this->storage->write('trading/open_orders', $openOrders);
    }

    // ─── Verificações ────────────────────────────────────────────────────────

    private function checkLimit(array $order, float $price): bool
    {
        return match($order['side']) {
            'BUY'  => $price <= $order['price'],  // compra quando preço cai até o limite
            'SELL' => $price >= $order['price'],  // vende quando preço sobe até o limite
            default => false,
        };
    }

    private function checkStopLimit(array $order, float $price): bool
    {
        // Stop foi ativado e preço cruzou o limit
        if (!($order['stop_triggered'] ?? false)) {
            $triggered = match($order['side']) {
                'BUY'  => $price >= $order['stop_price'],
                'SELL' => $price <= $order['stop_price'],
                default => false,
            };
            if ($triggered) {
                $order['stop_triggered'] = true;
            }
            return false;
        }
        return $this->checkLimit($order, $price);
    }

    private function checkStopMarket(array $order, float $price): bool
    {
        return match($order['side']) {
            'BUY'  => $price >= $order['stop_price'],
            'SELL' => $price <= $order['stop_price'],
            default => false,
        };
    }

    // ─── Execução ─────────────────────────────────────────────────────────────

    public function execute(array &$order, float $price): void
    {
        $execPrice   = $order['type'] === 'MARKET' ? $price : ($order['price'] > 0 ? $order['price'] : $price);
        $fee         = $order['quantity'] * $execPrice * $this->config->takerFee;
        $quoteAmount = $order['quantity'] * $execPrice;

        // Atualiza ordem
        $order['status']       = 'FILLED';
        $order['avg_price']    = $execPrice;
        $order['executed_qty'] = $order['quantity'];
        $order['fee']          = round($fee, 8);
        $order['updated_at']   = time() * 1000;

        // Atualiza saldos
        $this->updateBalances($order, $execPrice, $fee);

        // Registra trade
        $this->recordTrade($order, $execPrice, $fee);

        // Move para histórico
        $this->moveToHistory($order);
    }

    private function updateBalances(array $order, float $execPrice, float $fee): void
    {
        $balances = $this->storage->read('account/balances') ?? [];
        [$base, $quote] = $this->splitSymbol($order['symbol']);

        if ($order['side'] === 'BUY') {
            // Debita quote (USDT), credita base (BTC)
            $cost = $order['quantity'] * $execPrice + $fee;
            $balances[$quote]['free']   = max(0, ($balances[$quote]['free']   ?? 0) - $cost);
            $balances[$quote]['locked'] = max(0, ($balances[$quote]['locked'] ?? 0) - $cost);
            $balances[$base]['free']    = ($balances[$base]['free'] ?? 0) + $order['quantity'];
        } else {
            // Debita base (BTC), credita quote (USDT)
            $earned = ($order['quantity'] * $execPrice) - $fee;
            $balances[$base]['free']    = max(0, ($balances[$base]['free']   ?? 0) - $order['quantity']);
            $balances[$base]['locked']  = max(0, ($balances[$base]['locked'] ?? 0) - $order['quantity']);
            $balances[$quote]['free']   = ($balances[$quote]['free'] ?? 0) + $earned;
        }

        $this->storage->write('account/balances', $balances);
    }

    private function recordTrade(array $order, float $execPrice, float $fee): void
    {
        [$base, $quote] = $this->splitSymbol($order['symbol']);
        $trade = [
            'id'         => 'TRD-' . bin2hex(random_bytes(6)),
            'order_id'   => $order['id'],
            'symbol'     => $order['symbol'],
            'side'       => $order['side'],
            'price'      => $execPrice,
            'quantity'   => $order['quantity'],
            'quote_qty'  => $order['quantity'] * $execPrice,
            'fee'        => $fee,
            'fee_asset'  => $order['side'] === 'BUY' ? $quote : $base,
            'is_maker'   => $order['type'] === 'LIMIT',
            'timestamp'  => time() * 1000,
        ];
        $this->storage->append('trading/my_trades', $trade);
        $this->storage->append('market/trades', array_merge($trade, ['is_public' => true]));
    }

    private function moveToHistory(array $order): void
    {
        $this->storage->append('trading/order_history', $order);
        $open = $this->storage->read('trading/open_orders') ?? [];
        $open = array_values(array_filter($open, fn($o) => $o['id'] !== $order['id']));
        $this->storage->write('trading/open_orders', $open);
    }

    private function splitSymbol(string $symbol): array
    {
        $quotes = ['USDT', 'USDC', 'BRL', 'BUSD', 'BTC', 'ETH', 'BNB'];
        foreach ($quotes as $q) {
            if (str_ends_with($symbol, $q)) {
                return [str_replace($q, '', $symbol), $q];
            }
        }
        return [substr($symbol, 0, -4), substr($symbol, -4)];
    }
}
