<?php
namespace IsraelNogueira\ExchangeHub\Exchanges\Fake;

use IsraelNogueira\ExchangeHub\Storage\JsonStorage;

class FakeOrderMatcher
{
    public function __construct(
        private JsonStorage     $storage,
        private FakePriceEngine $engine,
        private FakeConfig      $config,
    ) {}

    public function checkAndExecute(string $symbol): void
    {
        if (!$this->config->autoExecuteLimitOrders) return;

        $openOrders = $this->storage->read('trading/open_orders') ?? [];
        $price      = $this->engine->getPrice($symbol);
        $changed    = false;

        foreach ($openOrders as &$order) {
            if ($order['symbol'] !== $symbol) continue;
            if ($order['status'] !== 'OPEN')   continue;
            if ($order['type']   === 'MARKET') continue;

            $shouldExecute = match($order['type']) {
                'LIMIT'       => $this->checkLimit($order, $price),
                'STOP_LIMIT'  => $this->checkStopLimit($order, $price),
                'STOP_MARKET' => $this->checkStopLimit($order, $price),
                default       => false,
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

    private function checkLimit(array $order, float $price): bool
    {
        $limitPrice = (float)$order['price'];
        return $order['side'] === 'BUY'
            ? $price <= $limitPrice
            : $price >= $limitPrice;
    }

    private function checkStopLimit(array $order, float $price): bool
    {
        if ($order['stop_triggered'] ?? false) {
            return $this->checkLimit($order, $price);
        }
        $stopPrice = (float)$order['stop_price'];
        $triggered = $order['side'] === 'BUY' ? $price >= $stopPrice : $price <= $stopPrice;
        if ($triggered) {
            $order['stop_triggered'] = true;
        }
        return false;
    }

    public function execute(array &$order, float $execPrice): void
    {
        $fee      = $order['quantity'] * $execPrice * $this->config->makerFee;
        $quoteQty = $order['quantity'] * $execPrice;

        $order['status']       = 'FILLED';
        $order['executed_qty'] = $order['quantity'];
        $order['avg_price']    = $execPrice;
        $order['fee']          = round($fee, 8);
        $order['updated_at']   = time() * 1000;

        // Atualiza saldo
        $balances = $this->storage->read('account/balances') ?? [];
        [$base, $quote] = $this->splitSymbol($order['symbol']);

        if ($order['side'] === 'BUY') {
            $balances[$base]['locked']  = max(0, ($balances[$base]['locked'] ?? 0));
            $balances[$base]['free']    = ($balances[$base]['free'] ?? 0) + $order['quantity'];
            $balances[$quote]['locked'] = max(0, ($balances[$quote]['locked'] ?? 0) - $quoteQty);
            $balances[$quote]['free']   = max(0, ($balances[$quote]['free'] ?? 0) - $fee);
        } else {
            $balances[$base]['locked']  = max(0, ($balances[$base]['locked'] ?? 0) - $order['quantity']);
            $balances[$quote]['free']   = ($balances[$quote]['free'] ?? 0) + $quoteQty - $fee;
        }
        $this->storage->write('account/balances', $balances);

        // Registra trade
        $trade = [
            'id'        => 'TRD-' . bin2hex(random_bytes(8)),
            'order_id'  => $order['id'],
            'symbol'    => $order['symbol'],
            'side'      => $order['side'],
            'price'     => $execPrice,
            'quantity'  => $order['quantity'],
            'quote_qty' => $quoteQty,
            'fee'       => round($fee, 8),
            'fee_asset' => $quote,
            'is_maker'  => true,
            'timestamp' => time() * 1000,
        ];
        $this->storage->append('trading/trade_history', $trade);

        // Move para histórico de ordens
        $hist = $this->storage->read('trading/order_history') ?? [];
        $hist[] = $order;
        $this->storage->write('trading/order_history', $hist);
    }

    private function splitSymbol(string $symbol): array
    {
        $fiats = ['USDT','USDC','BRL','BUSD','EUR','USD'];
        foreach ($fiats as $fiat) {
            if (str_ends_with($symbol, $fiat)) {
                return [str_replace($fiat, '', $symbol), $fiat];
            }
        }
        return [substr($symbol, 0, 3), substr($symbol, 3)];
    }
}
